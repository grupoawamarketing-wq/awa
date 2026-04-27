<?php

declare(strict_types=1);

namespace GrupoAwamotos\SmartSuggestions\Cron;

use GrupoAwamotos\SmartSuggestions\Api\SuggestionEngineInterface;
use GrupoAwamotos\SmartSuggestions\Api\WhatsappSenderInterface;
use GrupoAwamotos\SmartSuggestions\Helper\Config;
use GrupoAwamotos\SmartSuggestions\Model\SuggestionHistoryFactory;
use GrupoAwamotos\SmartSuggestions\Model\ResourceModel\SuggestionHistory as SuggestionHistoryResource;
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

    public function __construct(
        SuggestionEngineInterface $suggestionEngine,
        WhatsappSenderInterface $whatsappSender,
        Config $config,
        LoggerInterface $logger,
        SuggestionHistoryFactory $historyFactory,
        SuggestionHistoryResource $historyResource
    ) {
        $this->suggestionEngine = $suggestionEngine;
        $this->whatsappSender = $whatsappSender;
        $this->config = $config;
        $this->logger = $logger;
        $this->historyFactory = $historyFactory;
        $this->historyResource = $historyResource;
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
}
