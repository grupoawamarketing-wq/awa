<?php

/**
 * Revenue Pipeline Model
 *
 * Analyzes B2B quote request pipeline: conversion rates, deal velocity,
 * expected revenue, and period-over-period comparison.
 */

declare(strict_types=1);

namespace GrupoAwamotos\SalesIntelligence\Model;

use GrupoAwamotos\B2B\Api\Data\QuoteRequestInterface;
use GrupoAwamotos\B2B\Api\QuoteRequestRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\App\CacheInterface;
use Psr\Log\LoggerInterface;

class RevenuePipeline
{
    private const CACHE_PREFIX = 'si_pipeline_';
    private const CACHE_TTL = 1800; // 30 minutes
    private const CACHE_TAG = 'sales_intelligence_pipeline';

    private QuoteRequestRepositoryInterface $quoteRequestRepository;
    private SearchCriteriaBuilder $searchCriteriaBuilder;
    private FilterBuilder $filterBuilder;
    private CacheInterface $cache;
    private LoggerInterface $logger;

    public function __construct(
        QuoteRequestRepositoryInterface $quoteRequestRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        FilterBuilder $filterBuilder,
        CacheInterface $cache,
        LoggerInterface $logger
    ) {
        $this->quoteRequestRepository = $quoteRequestRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->filterBuilder = $filterBuilder;
        $this->cache = $cache;
        $this->logger = $logger;
    }

    /**
     * Get pipeline metrics for a given period
     */
    public function getPipelineMetrics(int $days = 30): array
    {
        $cacheKey = self::CACHE_PREFIX . "metrics_{$days}";
        $cached = $this->cache->load($cacheKey);
        if ($cached) {
            return json_decode($cached, true) ?: [];
        }

        try {
            $currentPeriod = $this->calculatePeriodMetrics($days, 0);
            $previousPeriod = $this->calculatePeriodMetrics($days, $days);

            $result = array_merge($currentPeriod, [
                'comparison' => $this->buildComparison($currentPeriod, $previousPeriod),
            ]);

            $this->cache->save(
                json_encode($result),
                $cacheKey,
                [self::CACHE_TAG],
                self::CACHE_TTL
            );

            return $result;
        } catch (\Exception $e) {
            $this->logger->error('[SalesIntelligence] RevenuePipeline error: ' . $e->getMessage());
            return $this->getEmptyMetrics();
        }
    }

    /**
     * Calculate metrics for a specific period window
     *
     * @param int $days Window size
     * @param int $offset Days offset from now (0 = current period)
     */
    private function calculatePeriodMetrics(int $days, int $offset): array
    {
        $endDate = date('Y-m-d H:i:s', strtotime("-{$offset} days"));
        $startDate = date('Y-m-d H:i:s', strtotime("-" . ($offset + $days) . " days"));

        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter('created_at', $startDate, 'gteq')
            ->addFilter('created_at', $endDate, 'lteq')
            ->create();

        $results = $this->quoteRequestRepository->getList($searchCriteria);
        $quotes = $results->getItems();

        $statusCounts = [
            'pending' => 0,
            'processing' => 0,
            'quoted' => 0,
            'accepted' => 0,
            'rejected' => 0,
            'expired' => 0,
            'converted' => 0,
        ];

        $pipelineValue = 0.0;
        $acceptedValue = 0.0;
        $acceptedCount = 0;
        $responseTimes = [];

        foreach ($quotes as $quote) {
            $status = $quote->getStatus();
            if (isset($statusCounts[$status])) {
                $statusCounts[$status]++;
            }

            $total = (float) ($quote->getQuotedTotal() ?: $quote->getData('total') ?: 0);

            // Pipeline = pending + quoted (open deals)
            if (in_array($status, ['pending', 'quoted', 'processing'])) {
                $pipelineValue += $total;
            }

            // Accepted deals
            if ($status === 'accepted' || $status === 'converted') {
                $acceptedValue += $total;
                $acceptedCount++;
            }

            // Response time: created_at → updated_at when status became 'quoted'
            if ($status === 'quoted' || $status === 'accepted' || $status === 'rejected') {
                $created = strtotime($quote->getData('created_at') ?? '');
                $updated = strtotime($quote->getData('updated_at') ?? '');
                if ($created && $updated && $updated > $created) {
                    $responseTimes[] = ($updated - $created) / 86400; // days
                }
            }
        }

        $totalQuotes = count($quotes);
        $closedDeals = $statusCounts['accepted'] + $statusCounts['converted']
            + $statusCounts['rejected'] + $statusCounts['expired'];
        $wonDeals = $statusCounts['accepted'] + $statusCounts['converted'];
        $conversionRate = $closedDeals > 0 ? ($wonDeals / $closedDeals) * 100 : 0;
        $avgResponseTime = !empty($responseTimes) ? array_sum($responseTimes) / count($responseTimes) : 0;
        $avgDealValue = $acceptedCount > 0 ? $acceptedValue / $acceptedCount : 0;
        $expectedRevenue = $pipelineValue * ($conversionRate / 100);

        return [
            'total_quotes' => $totalQuotes,
            'pending_quotes' => $statusCounts['pending'] + $statusCounts['processing'],
            'quoted_quotes' => $statusCounts['quoted'],
            'accepted_quotes' => $wonDeals,
            'rejected_quotes' => $statusCounts['rejected'],
            'expired_quotes' => $statusCounts['expired'],
            'conversion_rate' => round($conversionRate, 1),
            'pipeline_value' => round($pipelineValue, 2),
            'expected_revenue' => round($expectedRevenue, 2),
            'avg_response_time_days' => round($avgResponseTime, 1),
            'avg_deal_value' => round($avgDealValue, 2),
            'accepted_value' => round($acceptedValue, 2),
        ];
    }

    /**
     * Build MoM comparison data
     */
    private function buildComparison(array $current, array $previous): array
    {
        $compareKeys = ['total_quotes', 'conversion_rate', 'pipeline_value', 'accepted_value', 'avg_deal_value'];
        $comparison = [];

        foreach ($compareKeys as $key) {
            $curr = $current[$key] ?? 0;
            $prev = $previous[$key] ?? 0;
            $change = $curr - $prev;
            $changePct = $prev > 0 ? ($change / $prev) * 100 : ($curr > 0 ? 100 : 0);

            $comparison[$key] = [
                'current' => $curr,
                'previous' => $prev,
                'change' => round($change, 2),
                'change_pct' => round($changePct, 1),
                'trend' => $changePct > 5 ? 'up' : ($changePct < -5 ? 'down' : 'stable'),
            ];
        }

        return $comparison;
    }

    private function getEmptyMetrics(): array
    {
        return [
            'total_quotes' => 0,
            'pending_quotes' => 0,
            'quoted_quotes' => 0,
            'accepted_quotes' => 0,
            'rejected_quotes' => 0,
            'expired_quotes' => 0,
            'conversion_rate' => 0,
            'pipeline_value' => 0,
            'expected_revenue' => 0,
            'avg_response_time_days' => 0,
            'avg_deal_value' => 0,
            'accepted_value' => 0,
            'comparison' => [],
        ];
    }

    public function clearCache(): void
    {
        $this->cache->clean([self::CACHE_TAG]);
    }
}
