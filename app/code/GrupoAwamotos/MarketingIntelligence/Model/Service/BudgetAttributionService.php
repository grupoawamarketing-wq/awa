<?php

declare(strict_types=1);

namespace GrupoAwamotos\MarketingIntelligence\Model\Service;

use GrupoAwamotos\MarketingIntelligence\Model\ResourceModel\CampaignInsight\CollectionFactory;
use Psr\Log\LoggerInterface;

/**
 * Aggregates campaign insights by classification to compute budget attribution.
 */
class BudgetAttributionService
{
    public function __construct(
        private readonly CollectionFactory $collectionFactory,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Get budget breakdown by classification for a given period.
     *
     * @param string $since Y-m-d
     * @param string $until Y-m-d
     * @return array<string, array{spend: float, impressions: int, clicks: int, roas: float|null, pct: float}>
     */
    public function getAttribution(string $since, string $until): array
    {
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter('date_start', ['gteq' => $since]);
        $collection->addFieldToFilter('date_stop', ['lteq' => $until]);

        $select = $collection->getSelect();
        $select->reset(\Magento\Framework\DB\Select::COLUMNS);
        $select->columns([
            'classification' => 'COALESCE(classification, "unclassified")',
            'total_spend' => 'SUM(spend)',
            'total_impressions' => 'SUM(impressions)',
            'total_clicks' => 'SUM(clicks)',
            'avg_roas' => 'AVG(NULLIF(purchase_roas, 0))',
        ]);
        $select->group('classification');

        $connection = $collection->getConnection();
        $rows = $connection->fetchAll($select);

        $totalSpend = 0.0;
        $breakdown = [];

        foreach ($rows as $row) {
            $spend = (float) $row['total_spend'];
            $totalSpend += $spend;
            $breakdown[$row['classification']] = [
                'spend' => $spend,
                'impressions' => (int) $row['total_impressions'],
                'clicks' => (int) $row['total_clicks'],
                'roas' => $row['avg_roas'] !== null ? round((float) $row['avg_roas'], 2) : null,
                'pct' => 0.0,
            ];
        }

        if ($totalSpend > 0) {
            foreach ($breakdown as $label => &$data) {
                $data['pct'] = round(($data['spend'] / $totalSpend) * 100, 1);
            }
        }

        return $breakdown;
    }

    /**
     * Get total spend for a period.
     */
    public function getTotalSpend(string $since, string $until): float
    {
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter('date_start', ['gteq' => $since]);
        $collection->addFieldToFilter('date_stop', ['lteq' => $until]);

        $select = $collection->getSelect();
        $select->reset(\Magento\Framework\DB\Select::COLUMNS);
        $select->columns(['total' => 'SUM(spend)']);

        return (float) $collection->getConnection()->fetchOne($select);
    }
}
