<?php

declare(strict_types=1);

namespace GrupoAwamotos\MarketingIntelligence\Model\Service;

use GrupoAwamotos\MarketingIntelligence\Model\ResourceModel\CampaignInsight\CollectionFactory as InsightCollectionFactory;
use GrupoAwamotos\MarketingIntelligence\Model\ResourceModel\Prospect\CollectionFactory as ProspectCollectionFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Psr\Log\LoggerInterface;

/**
 * Checks configurable thresholds and produces alert arrays for the dashboard.
 */
class AlertService
{
    private const XML_PATH_ENABLED = 'marketing_intelligence/alerts/enabled';
    private const XML_PATH_SPEND_THRESHOLD = 'marketing_intelligence/alerts/spend_without_conversion_threshold';
    private const XML_PATH_MIN_B2B_ROAS = 'marketing_intelligence/alerts/min_b2b_roas';
    private const XML_PATH_MAX_QUOTE_DAYS = 'marketing_intelligence/alerts/max_quote_pending_days';
    private const XML_PATH_MAX_PROSPECT_IDLE = 'marketing_intelligence/alerts/max_hot_prospect_idle_days';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly InsightCollectionFactory $insightCollectionFactory,
        private readonly ProspectCollectionFactory $prospectCollectionFactory,
        private readonly BudgetAttributionService $budgetAttribution,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Run all alert checks and return active alerts.
     *
     * @return array<int, array{type: string, severity: string, message: string, value: float|int|string, threshold: float|int|string}>
     */
    public function checkAlerts(): array
    {
        if (!$this->scopeConfig->isSetFlag(self::XML_PATH_ENABLED)) {
            return [];
        }

        $alerts = [];

        $this->checkSpendWithoutConversion($alerts);
        $this->checkB2BRoas($alerts);
        $this->checkPendingQuotes($alerts);
        $this->checkIdleHotProspects($alerts);

        return $alerts;
    }

    /**
     * Daily spend > X with zero B2B conversions.
     *
     * @param array<int, array<string, mixed>> &$alerts
     */
    private function checkSpendWithoutConversion(array &$alerts): void
    {
        $threshold = (float) $this->scopeConfig->getValue(self::XML_PATH_SPEND_THRESHOLD);
        if ($threshold <= 0) {
            return;
        }

        $yesterday = date('Y-m-d', strtotime('-1 day'));
        $collection = $this->insightCollectionFactory->create();
        $collection->addFieldToFilter('date_start', $yesterday);
        $collection->addFieldToFilter('classification', 'b2b');

        $select = $collection->getSelect();
        $select->reset(\Magento\Framework\DB\Select::COLUMNS);
        $select->columns([
            'total_spend' => 'SUM(spend)',
            'total_clicks' => 'SUM(clicks)',
        ]);

        $row = $collection->getConnection()->fetchRow($select);
        $dailySpend = (float) ($row['total_spend'] ?? 0);
        $dailyClicks = (int) ($row['total_clicks'] ?? 0);

        if ($dailySpend >= $threshold && $dailyClicks === 0) {
            $alerts[] = [
                'type' => 'spend_no_conversion',
                'severity' => 'high',
                'message' => sprintf(
                    'Gasto B2B de R$%.2f ontem sem nenhum clique. Verificar campanhas.',
                    $dailySpend
                ),
                'value' => $dailySpend,
                'threshold' => $threshold,
            ];
        }
    }

    /**
     * B2B ROAS below configured minimum.
     *
     * @param array<int, array<string, mixed>> &$alerts
     */
    private function checkB2BRoas(array &$alerts): void
    {
        $minRoas = (float) $this->scopeConfig->getValue(self::XML_PATH_MIN_B2B_ROAS);
        if ($minRoas <= 0) {
            return;
        }

        $since = date('Y-m-d', strtotime('-7 days'));
        $until = date('Y-m-d');
        $attribution = $this->budgetAttribution->getAttribution($since, $until);

        $b2bRoas = $attribution['b2b']['roas'] ?? null;
        if ($b2bRoas !== null && $b2bRoas < $minRoas) {
            $alerts[] = [
                'type' => 'low_b2b_roas',
                'severity' => 'medium',
                'message' => sprintf(
                    'ROAS B2B últimos 7 dias: %.2fx (mínimo configurado: %.2fx).',
                    $b2bRoas,
                    $minRoas
                ),
                'value' => $b2bRoas,
                'threshold' => $minRoas,
            ];
        }
    }

    /**
     * B2B quotes pending longer than configured days.
     *
     * @param array<int, array<string, mixed>> &$alerts
     */
    private function checkPendingQuotes(array &$alerts): void
    {
        $maxDays = (int) $this->scopeConfig->getValue(self::XML_PATH_MAX_QUOTE_DAYS);
        if ($maxDays <= 0) {
            return;
        }

        $cutoff = date('Y-m-d', strtotime('-' . $maxDays . ' days'));

        try {
            $collection = $this->prospectCollectionFactory->create();
            $connection = $collection->getConnection();
            $quoteTable = $collection->getTable('grupoawamotos_b2b_quote_request');

            $select = $connection->select()
                ->from($quoteTable, ['cnt' => 'COUNT(*)'])
                ->where('status IN (?)', ['pending', 'quoted'])
                ->where('created_at <= ?', $cutoff);

            $count = (int) $connection->fetchOne($select);
        } catch (\Exception $e) {
            $this->logger->debug('AlertService: quote table not available — ' . $e->getMessage());
            return;
        }

        if ($count > 0) {
            $alerts[] = [
                'type' => 'stale_quotes',
                'severity' => 'medium',
                'message' => sprintf(
                    '%d cotações B2B pendentes há mais de %d dias.',
                    $count,
                    $maxDays
                ),
                'value' => $count,
                'threshold' => $maxDays,
            ];
        }
    }

    /**
     * Hot prospects (score >= 70) idle for more than configured days.
     *
     * @param array<int, array<string, mixed>> &$alerts
     */
    private function checkIdleHotProspects(array &$alerts): void
    {
        $maxDays = (int) $this->scopeConfig->getValue(self::XML_PATH_MAX_PROSPECT_IDLE);
        if ($maxDays <= 0) {
            return;
        }

        $cutoff = date('Y-m-d H:i:s', strtotime('-' . $maxDays . ' days'));

        $collection = $this->prospectCollectionFactory->create();
        $collection->addFieldToFilter('prospect_score', ['gteq' => 70]);
        $collection->addFieldToFilter('prospect_status', 'new');
        $collection->addFieldToFilter('updated_at', ['lteq' => $cutoff]);

        $count = $collection->getSize();

        if ($count > 0) {
            $alerts[] = [
                'type' => 'idle_hot_prospects',
                'severity' => 'high',
                'message' => sprintf(
                    '%d prospects quentes (score 70+) sem contato há mais de %d dias.',
                    $count,
                    $maxDays
                ),
                'value' => $count,
                'threshold' => $maxDays,
            ];
        }
    }
}
