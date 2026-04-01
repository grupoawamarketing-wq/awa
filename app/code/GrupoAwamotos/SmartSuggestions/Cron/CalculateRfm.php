<?php

declare(strict_types=1);

namespace GrupoAwamotos\SmartSuggestions\Cron;

use GrupoAwamotos\SmartSuggestions\Api\RfmCalculatorInterface;
use GrupoAwamotos\SmartSuggestions\Helper\Config;
use GrupoAwamotos\SmartSuggestions\Model\ResourceModel\RfmCache as RfmCacheResource;
use Psr\Log\LoggerInterface;

/**
 * Cron job to calculate RFM scores
 */
class CalculateRfm
{
    private RfmCalculatorInterface $rfmCalculator;
    private Config $config;
    private LoggerInterface $logger;
    private RfmCacheResource $rfmCacheResource;

    public function __construct(
        RfmCalculatorInterface $rfmCalculator,
        Config $config,
        LoggerInterface $logger,
        RfmCacheResource $rfmCacheResource
    ) {
        $this->rfmCalculator = $rfmCalculator;
        $this->config = $config;
        $this->logger = $logger;
        $this->rfmCacheResource = $rfmCacheResource;
    }

    /**
     * Execute RFM calculation cron
     */
    public function execute(): void
    {
        if (!$this->config->isEnabled()) {
            return;
        }

        if (!$this->config->isRfmCronEnabled()) {
            return;
        }

        $this->logger->info('SmartSuggestions: Starting RFM calculation cron');

        try {
            $startTime = microtime(true);

            $results = $this->rfmCalculator->calculateAll();
            $count = count($results);

            // Persist to cache table for performance
            if (!empty($results)) {
                $cacheEntries = [];
                $now = date('Y-m-d H:i:s');

                foreach ($results as $customer) {
                    $customerName = trim((string)($customer['customer_name'] ?? ''));
                    if ($customerName === '') {
                        $customerName = 'Cliente #' . (int) ($customer['customer_id'] ?? 0);
                    }

                    $cacheEntries[] = [
                        'erp_customer_id' => (int) $customer['customer_id'],
                        'customer_name' => $customerName,
                        'customer_cnpj' => $customer['cnpj'] ?? null,
                        'customer_phone' => $customer['phone'] ?? null,
                        'customer_city' => $customer['city'] ?? null,
                        'customer_uf' => $customer['state'] ?? null,
                        'r_score' => (int) $customer['r_score'],
                        'f_score' => (int) $customer['f_score'],
                        'm_score' => (int) $customer['m_score'],
                        'rfm_score' => $customer['rfm_score'],
                        'segment' => $customer['segment'],
                        'recency_days' => (int) $customer['recency_days'],
                        'frequency' => (int) $customer['frequency'],
                        'monetary' => (float) $customer['monetary'],
                        'last_order_date' => $customer['last_purchase'] ?? null,
                        'calculated_at' => $now
                    ];
                }

                $this->rfmCacheResource->bulkUpsert($cacheEntries);
                $this->logger->info(sprintf('SmartSuggestions: Cached %d RFM entries', count($cacheEntries)));
            }

            $duration = round(microtime(true) - $startTime, 2);

            $this->logger->info(sprintf(
                'SmartSuggestions: RFM calculation completed. Processed %d customers in %s seconds',
                $count,
                $duration
            ));

            // Log segment distribution
            $segments = [];
            foreach ($results as $customer) {
                $segment = $customer['segment'] ?? 'Unknown';
                $segments[$segment] = ($segments[$segment] ?? 0) + 1;
            }

            $this->logger->info('SmartSuggestions: RFM Segment Distribution', $segments);
        } catch (\Exception $e) {
            $this->logger->error('SmartSuggestions: RFM calculation cron failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
}
