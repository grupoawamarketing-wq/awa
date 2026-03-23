<?php

declare(strict_types=1);

namespace GrupoAwamotos\MarketingIntelligence\Model\Service;

use Meta\BusinessExtension\Helper\FBEHelper;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Psr\Log\LoggerInterface;

/**
 * Queries Meta Dataset Quality (Signal/EMQ) API for pixel health metrics.
 *
 * Endpoint: GET /{pixel_id}/signal_quality
 * Returns event coverage, match quality, data freshness.
 */
class DatasetQualityService
{
    private const XML_PATH_ENABLED = 'marketing_intelligence/signal_quality/enabled';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly FBEHelper $fbeHelper,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Fetch EMQ (Event Match Quality) metrics from Meta.
     *
     * @return array{emq_score: float, events: array<string, array>, freshness: string, error: string|null}
     */
    public function fetchQualityMetrics(): array
    {
        $result = [
            'emq_score' => 0.0,
            'events' => [],
            'freshness' => 'unknown',
            'error' => null,
        ];

        if (!$this->scopeConfig->isSetFlag(self::XML_PATH_ENABLED)) {
            $result['error'] = 'Signal quality check is disabled';
            return $result;
        }

        $pixelId = $this->getPixelId();
        if (empty($pixelId)) {
            $result['error'] = 'No pixel ID configured';
            return $result;
        }

        try {
            $response = $this->fbeHelper->apiGet(
                "/{$pixelId}/signal_quality",
                ['fields' => 'event_match_quality,data_quality'],
                null
            );

            if (!is_array($response) || empty($response)) {
                $result['error'] = 'Empty response from Meta Signal Quality API';
                return $result;
            }

            $result = $this->parseResponse($response, $result);

            $this->logger->info('DatasetQualityService: fetched EMQ metrics', [
                'pixel_id' => $pixelId,
                'emq_score' => $result['emq_score'],
                'events_count' => count($result['events']),
            ]);
        } catch (\Exception $e) {
            $this->logger->error('DatasetQualityService: failed to fetch signal quality', [
                'pixel_id' => $pixelId,
                'error' => $e->getMessage(),
            ]);
            $result['error'] = $e->getMessage();
        }

        return $result;
    }

    /**
     * Fetch per-event EMQ breakdown.
     *
     * @return array<string, array{score: float, match_keys: array}>
     */
    public function fetchEventBreakdown(): array
    {
        $pixelId = $this->getPixelId();
        if (empty($pixelId)) {
            return [];
        }

        try {
            $response = $this->fbeHelper->apiGet(
                "/{$pixelId}/event_match_quality",
                ['fields' => 'event_name,match_quality,coverage'],
                null
            );

            if (!is_array($response) || !isset($response['data'])) {
                return [];
            }

            $breakdown = [];
            foreach ($response['data'] as $event) {
                $eventName = $event['event_name'] ?? 'unknown';
                $breakdown[$eventName] = [
                    'score' => (float) ($event['match_quality'] ?? 0),
                    'coverage' => $event['coverage'] ?? [],
                ];
            }

            return $breakdown;
        } catch (\Exception $e) {
            $this->logger->error('DatasetQualityService: event breakdown failed', [
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Get a summary for the dashboard panel.
     *
     * @return array{score: float, status: string, events: int, last_check: string, error: string|null}
     */
    public function getDashboardSummary(): array
    {
        $metrics = $this->fetchQualityMetrics();

        $status = 'unknown';
        if ($metrics['error'] === null) {
            $score = $metrics['emq_score'];
            if ($score >= 8.0) {
                $status = 'excellent';
            } elseif ($score >= 6.0) {
                $status = 'good';
            } elseif ($score >= 4.0) {
                $status = 'fair';
            } else {
                $status = 'poor';
            }
        }

        return [
            'score' => $metrics['emq_score'],
            'status' => $status,
            'events' => count($metrics['events']),
            'freshness' => $metrics['freshness'],
            'last_check' => date('Y-m-d H:i:s'),
            'error' => $metrics['error'],
        ];
    }

    /**
     * Parse the Meta API response into our internal format.
     *
     * @param array<string, mixed> $response
     * @param array<string, mixed> $result
     * @return array<string, mixed>
     */
    private function parseResponse(array $response, array $result): array
    {
        // event_match_quality contains overall EMQ score
        if (isset($response['event_match_quality'])) {
            $emq = $response['event_match_quality'];
            $result['emq_score'] = (float) ($emq['score'] ?? $emq ?? 0);
        }

        // data_quality contains per-event metrics
        if (isset($response['data_quality']['data']) && is_array($response['data_quality']['data'])) {
            foreach ($response['data_quality']['data'] as $eventData) {
                $name = $eventData['event_name'] ?? 'unknown';
                $result['events'][$name] = [
                    'score' => (float) ($eventData['match_quality'] ?? 0),
                    'volume' => (int) ($eventData['event_count'] ?? 0),
                    'last_received' => $eventData['last_received_time'] ?? null,
                ];
            }
        }

        // Determine freshness from most recent event
        $latestTime = 0;
        foreach ($result['events'] as $event) {
            if (isset($event['last_received']) && strtotime((string) $event['last_received']) > $latestTime) {
                $latestTime = strtotime((string) $event['last_received']);
            }
        }
        if ($latestTime > 0) {
            $hoursAgo = (time() - $latestTime) / 3600;
            if ($hoursAgo < 1) {
                $result['freshness'] = 'real-time';
            } elseif ($hoursAgo < 24) {
                $result['freshness'] = 'recent';
            } elseif ($hoursAgo < 72) {
                $result['freshness'] = 'stale';
            } else {
                $result['freshness'] = 'outdated';
            }
        }

        return $result;
    }

    /**
     * Get pixel ID with fallback to Facebook Business Extension config.
     */
    private function getPixelId(): string
    {
        $pixelId = (string) $this->scopeConfig->getValue('marketing_intelligence/general/pixel_id');
        if (empty($pixelId)) {
            $pixelId = (string) $this->scopeConfig->getValue('facebook/business_extension/pixel_id');
        }
        return $pixelId;
    }
}
