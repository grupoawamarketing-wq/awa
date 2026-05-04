<?php

declare(strict_types=1);

namespace GrupoAwamotos\SmartSuggestions\Cron;

use GrupoAwamotos\SmartSuggestions\Api\SuggestionEngineInterface;
use GrupoAwamotos\SmartSuggestions\Api\WhatsappSenderInterface;
use GrupoAwamotos\SmartSuggestions\Helper\Config;
use GrupoAwamotos\SmartSuggestions\Model\SuggestionHistory;
use GrupoAwamotos\SmartSuggestions\Model\SuggestionHistoryFactory;
use GrupoAwamotos\SmartSuggestions\Model\ResourceModel\SuggestionHistory as SuggestionHistoryResource;
use GrupoAwamotos\SmartSuggestions\Model\ResourceModel\SuggestionHistory\CollectionFactory as SuggestionHistoryCollectionFactory;
use Psr\Log\LoggerInterface;

/**
 * Cron job to generate suggestions for at-risk customers
 */
class GenerateSuggestions
{
    private SuggestionEngineInterface $suggestionEngine;
    private WhatsappSenderInterface $whatsappSender;
    private Config $config;
    private LoggerInterface $logger;
    private SuggestionHistoryFactory $historyFactory;
    private SuggestionHistoryResource $historyResource;
    private SuggestionHistoryCollectionFactory $historyCollectionFactory;

    public function __construct(
        SuggestionEngineInterface $suggestionEngine,
        WhatsappSenderInterface $whatsappSender,
        Config $config,
        LoggerInterface $logger,
        SuggestionHistoryFactory $historyFactory,
        SuggestionHistoryResource $historyResource,
        SuggestionHistoryCollectionFactory $historyCollectionFactory
    ) {
        $this->suggestionEngine = $suggestionEngine;
        $this->whatsappSender = $whatsappSender;
        $this->config = $config;
        $this->logger = $logger;
        $this->historyFactory = $historyFactory;
        $this->historyResource = $historyResource;
        $this->historyCollectionFactory = $historyCollectionFactory;
    }

    /**
     * Execute suggestion generation cron
     */
    public function execute(): void
    {
        if (!$this->config->isEnabled()) {
            return;
        }

        if (!$this->config->isSuggestionsCronEnabled()) { // phpcs:ignore Squiz.Operators.ComparisonOperatorUsage
            return;
        }

        try {
            $startTime = microtime(true);

            // Retry previously generated items that were not sent (e.g., WhatsApp was off)
            $this->retryGeneratedItems();

            // Retry send_failed items with valid Brazilian mobile phones (landlines skipped)
            $this->retrySendFailedItems();

            // Get top opportunities (at-risk customers)
            $opportunities = $this->suggestionEngine->getTopOpportunities(50);

            $generated = 0;
            $sent = 0;
            $errors = 0;
            $skipped = 0;

            // Batch-fetch all suggestions to optimize ERP queries
            $customerIds = array_column($opportunities, 'customer_id');
            $allSuggestions = $this->suggestionEngine->generateBatchCartSuggestions($customerIds);

            foreach ($opportunities as $opportunity) {
                $customerId = $opportunity['customer_id'];
                try {
                    // Get pre-generated suggestion
                    $suggestion = $allSuggestions[$customerId] ?? ['error' => 'Sugestão não encontrada no lote'];

                    if (isset($suggestion['error'])) {
                        $this->logger->warning('SmartSuggestions: Failed to generate suggestion', [
                            'customer_id' => $customerId,
                            'error' => $suggestion['error']
                        ]);
                        $errors++;
                        continue;
                    }

                    // Skip saving if no products were suggested (empty cart)
                    $productsCount = $suggestion['cart_summary']['total_products'] ?? 0;
                    if ($productsCount === 0) {
                        $this->logger->debug('SmartSuggestions: Skipping customer with no suggestions', [
                            'customer_id' => $opportunity['customer_id'],
                        ]);
                        $skipped++;
                        continue;
                    }

                    $generated++;

                    $autoSend = $this->config->isAutoSendWhatsappEnabled()
                        && $this->config->isWhatsappEnabled();
                    $phone = $autoSend ? ($suggestion['customer']['phone'] ?? null) : null;

                    $status = 'generated';
                    $sentAt = null;
                    $whatsappMessageId = null;
                    $errorMessage = null;

                    // Attempt WhatsApp send before first save to avoid double-write
                    if ($autoSend && $phone) {
                        $result = $this->whatsappSender->sendSuggestion($phone, $suggestion);
                        if ($result['success']) {
                            $sent++;
                            $status = 'sent';
                            $sentAt = date('Y-m-d H:i:s');
                            $whatsappMessageId = $result['message_id'] ?? null;
                        } else {
                            $status = 'send_failed';
                            $errorMessage = $result['message'];
                        }
                    }

                    // Single save with final status
                    $history = $this->historyFactory->create();
                    $history->setData([
                        'erp_customer_id' => $opportunity['customer_id'],
                        'customer_name'   => $opportunity['customer_name'] ?? '',
                        'customer_phone'  => $suggestion['customer']['phone'] ?? null,
                        'customer_cnpj'   => $suggestion['customer']['cnpj'] ?? null,
                        'suggestion_data' => json_encode($suggestion),
                        'total_value' => $suggestion['cart_summary']['total_value'] ?? 0,
                        'products_count' => $suggestion['cart_summary']['total_products'] ?? 0,
                        'status' => $status,
                        'sent_at' => $sentAt,
                        'whatsapp_message_id' => $whatsappMessageId,
                        'error_message' => $errorMessage,
                        'created_at' => date('Y-m-d H:i:s'),
                    ]);
                    $this->historyResource->save($history);
                } catch (\Exception $e) {
                    $this->logger->error('SmartSuggestions: Error processing customer', [
                        'customer_id' => $opportunity['customer_id'],
                        'error' => $e->getMessage()
                    ]);
                    $errors++;
                }
            }

            $duration = round(microtime(true) - $startTime, 2);

            $this->logger->info(sprintf(
                'SmartSuggestions: Suggestion generation completed. Generated: %d, Sent: %d, Skipped: %d, Errors: %d in %s seconds',
                $generated,
                $sent,
                $skipped,
                $errors,
                $duration
            ));
        } catch (\Exception $e) {
            $this->logger->error('SmartSuggestions: Suggestion generation cron failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Retry suggestions that are in 'generated' status but have a phone number.
     *
     * These are items that were generated when auto-send was disabled or when the
     * WhatsApp provider was temporarily unavailable. On each new generation run,
     * we retry up to 30 older items before proceeding with new ones.
     */
    private function retryGeneratedItems(): void
    {
        if (!$this->config->isAutoSendWhatsappEnabled() || !$this->config->isWhatsappEnabled()) {
            return;
        }

        /** @var \GrupoAwamotos\SmartSuggestions\Model\ResourceModel\SuggestionHistory\Collection $collection */
        $collection = $this->historyCollectionFactory->create();
        $collection->addFieldToFilter('status', SuggestionHistory::STATUS_GENERATED)
            ->addFieldToFilter('customer_phone', ['notnull' => true])
            ->addFieldToFilter('customer_phone', ['neq' => ''])
            // Avoid re-sending items from the same run (older than 1 hour)
            ->addFieldToFilter('created_at', ['lt' => date('Y-m-d H:i:s', strtotime('-1 hour'))])
            ->setPageSize(30)
            ->setOrder('created_at', 'ASC');

        $retried = 0;
        $retrySent = 0;

        foreach ($collection as $history) {
            $phone = trim((string) $history->getData('customer_phone'));
            if (empty($phone)) {
                continue;
            }

            $suggestionData = json_decode((string) $history->getData('suggestion_data'), true);
            if (!is_array($suggestionData)) {
                continue;
            }

            try {
                $result = $this->whatsappSender->sendSuggestion($phone, $suggestionData);
                $retried++;

                if ($result['success']) {
                    $retrySent++;
                    $history->setData('status', SuggestionHistory::STATUS_SENT);
                    $history->setData('sent_at', date('Y-m-d H:i:s'));
                    $history->setData('whatsapp_message_id', $result['message_id'] ?? null);
                } else {
                    $history->setData('status', SuggestionHistory::STATUS_FAILED);
                    $history->setData('error_message', $result['message']);
                }

                $this->historyResource->save($history);
            } catch (\Exception $e) {
                $this->logger->error('SmartSuggestions: Error retrying generated item', [
                    'history_id' => $history->getId(),
                    'error'      => $e->getMessage(),
                ]);
            }
        }

        if ($retried > 0) {
            $this->logger->info(sprintf(
                'SmartSuggestions: Retried %d generated items — %d sent, %d failed.',
                $retried,
                $retrySent,
                $retried - $retrySent
            ));
        }
    }

    /**
     * Retry suggestions in send_failed status that have valid Brazilian mobile phone numbers.
     *
     * Landlines (8-digit numbers without 9 prefix after DDD) are skipped since they have no
     * WhatsApp. Only items created within the last 30 days are retried to avoid stale content.
     */
    private function retrySendFailedItems(): void
    {
        $enabled = $this->config->isAutoSendWhatsappEnabled() && $this->config->isWhatsappEnabled();
        if (!$enabled) {
            return;
        }

        /** @var \GrupoAwamotos\SmartSuggestions\Model\ResourceModel\SuggestionHistory\Collection $collection */
        $collection = $this->historyCollectionFactory->create();
        $collection->addFieldToFilter('status', SuggestionHistory::STATUS_FAILED)
            ->addFieldToFilter('customer_phone', ['notnull' => true])
            ->addFieldToFilter('customer_phone', ['neq' => ''])
            ->addFieldToFilter('created_at', ['gteq' => date('Y-m-d H:i:s', strtotime('-30 days'))])
            ->addFieldToFilter('created_at', ['lt' => date('Y-m-d H:i:s', strtotime('-1 hour'))])
            ->setPageSize(30)
            ->setOrder('created_at', 'ASC');

        $retried = 0;
        $retrySent = 0;

        foreach ($collection as $history) {
            $phone = trim((string) $history->getData('customer_phone'));

            // Validate Brazilian mobile: strip non-digits, keep last 11,
            // then verify the 3rd character (index 2) is '9' (mobile prefix after DDD)
            $digits = preg_replace('/\D/', '', $phone);
            if (strlen($digits) > 11) {
                $digits = substr($digits, -11);
            }
            if (strlen($digits) !== 11 || $digits[2] !== '9') {
                // Landline or malformed — skip silently
                continue;
            }

            $suggestionData = json_decode((string) $history->getData('suggestion_data'), true);
            if (!is_array($suggestionData)) {
                continue;
            }

            try {
                $result = $this->whatsappSender->sendSuggestion($phone, $suggestionData);
                $retried++;

                if ($result['success']) {
                    $retrySent++;
                    $history->setData('status', SuggestionHistory::STATUS_SENT);
                    $history->setData('sent_at', date('Y-m-d H:i:s'));
                    $history->setData('whatsapp_message_id', $result['message_id'] ?? null);
                    $history->setData('error_message', null);
                } else {
                    $history->setData('error_message', $result['message']);
                }

                $this->historyResource->save($history);
            } catch (\Exception $e) {
                $this->logger->error('SmartSuggestions: Error retrying send_failed item', [
                    'history_id' => $history->getId(),
                    'error'      => $e->getMessage(),
                ]);
            }
        }

        if ($retried > 0) {
            $this->logger->info(sprintf(
                'SmartSuggestions: Retried %d send_failed mobile items — %d sent, %d still failed.',
                $retried,
                $retrySent,
                $retried - $retrySent
            ));
        }
    }
}
