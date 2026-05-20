<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Platform\Dashboard\Provider;

use GrupoAwamotos\B2B\Model\Sectra\SectraImportStatus;
use GrupoAwamotos\B2B\Platform\Dashboard\B2bDashboardScopeHelper;
use GrupoAwamotos\B2B\Platform\Dashboard\DashboardFilter;
use GrupoAwamotos\B2B\Platform\Dashboard\KpiValue;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Sql\Expression;
use Magento\Framework\Pricing\Helper\Data as PriceHelper;

/**
 * KPIs de pedidos B2B — read-only.
 */
class OrderKpiProvider implements KpiProviderInterface
{
    private const TABLE_LIMIT = 10;

    public function __construct(
        private readonly B2bDashboardScopeHelper $scopeHelper,
        private readonly ResourceConnection $resourceConnection,
        private readonly PriceHelper $priceHelper
    ) {
    }

    /**
     * @inheritdoc
     */
    public function getData(DashboardFilter $filter): array
    {
        $ordersToday = $this->countOrdersToday();
        $ordersPeriod = $this->countOrdersInPeriod($filter);
        $revenuePeriod = $this->sumRevenueInPeriod($filter);
        $avgTicket = $ordersPeriod > 0 ? $revenuePeriod / $ordersPeriod : 0.0;

        return [
            'orders_today' => KpiValue::available($ordersToday)->toArray(),
            'orders_period' => KpiValue::available($ordersPeriod)->toArray(),
            'revenue_period' => KpiValue::available(
                $revenuePeriod,
                (string) $this->priceHelper->currency($revenuePeriod, true, false)
            )->toArray(),
            'avg_ticket' => KpiValue::available(
                $avgTicket,
                (string) $this->priceHelper->currency($avgTicket, true, false)
            )->toArray(),
            'recent_orders' => $this->fetchRecentOrders(self::TABLE_LIMIT),
        ];
    }

    private function countOrdersToday(): int
    {
        $range = $this->scopeHelper->getTodayRange();
        $select = $this->scopeHelper->createB2bOrderSelect();
        $select->reset(\Magento\Framework\DB\Select::COLUMNS)
            ->columns(['cnt' => new Expression('COUNT(DISTINCT so.entity_id)')])
            ->where('so.created_at >= ?', $range['from'])
            ->where('so.created_at <= ?', $range['to']);

        return (int) $this->resourceConnection->getConnection()->fetchOne($select);
    }

    private function countOrdersInPeriod(DashboardFilter $filter): int
    {
        $select = $this->scopeHelper->createB2bOrderSelect();
        $select->reset(\Magento\Framework\DB\Select::COLUMNS)
            ->columns(['cnt' => new Expression('COUNT(DISTINCT so.entity_id)')])
            ->where('so.created_at >= ?', $filter->getDateFromDatetime())
            ->where('so.created_at <= ?', $filter->getDateToDatetime());

        return (int) $this->resourceConnection->getConnection()->fetchOne($select);
    }

    private function sumRevenueInPeriod(DashboardFilter $filter): float
    {
        $select = $this->scopeHelper->createB2bOrderSelect();
        $select->reset(\Magento\Framework\DB\Select::COLUMNS)
            ->columns(['total' => new Expression('COALESCE(SUM(so.grand_total), 0)')])
            ->where('so.created_at >= ?', $filter->getDateFromDatetime())
            ->where('so.created_at <= ?', $filter->getDateToDatetime());

        return (float) $this->resourceConnection->getConnection()->fetchOne($select);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchRecentOrders(int $limit): array
    {
        $select = $this->scopeHelper->createB2bOrderSelect();
        $select->reset(\Magento\Framework\DB\Select::COLUMNS)
            ->columns([
                'entity_id' => 'so.entity_id',
                'increment_id' => 'so.increment_id',
                'customer_id' => 'so.customer_id',
                'customer_name' => new Expression("CONCAT(so.customer_firstname, ' ', so.customer_lastname)"),
                'grand_total' => 'so.grand_total',
                'status' => 'so.status',
                'sectra_import_status' => 'so.sectra_import_status',
                'created_at' => 'so.created_at',
            ])
            ->order('so.created_at DESC')
            ->limit($limit);

        $rows = $this->resourceConnection->getConnection()->fetchAll($select);
        $labels = SectraImportStatus::labels();

        foreach ($rows as &$row) {
            $status = (string) ($row['sectra_import_status'] ?? '');
            $row['sectra_label'] = $labels[$status] ?? ($status !== '' ? $status : '—');
            $row['grand_total_formatted'] = (string) $this->priceHelper->currency((float) $row['grand_total'], true, false);
        }

        return $rows;
    }
}
