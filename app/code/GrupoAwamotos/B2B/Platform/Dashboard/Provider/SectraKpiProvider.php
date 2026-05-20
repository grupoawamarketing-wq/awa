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
 * KPIs ERP/Sectra de pedidos — read-only.
 */
class SectraKpiProvider implements KpiProviderInterface
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
        $awaiting = $this->countByStatus(SectraImportStatus::AWAITING_CUSTOMER_VALIDATION);
        $blocked = $this->countByStatus(SectraImportStatus::ORDER_BLOCKED_CUSTOMER_NOT_VALIDATED);
        $nonImportable = $this->countByStatuses(SectraImportStatus::NON_IMPORTABLE);
        $ready = $this->countByStatus(SectraImportStatus::READY_FOR_IMPORT);
        $imported = $this->countByStatus(SectraImportStatus::IMPORTED);
        $importFailed = $this->countByStatus(SectraImportStatus::IMPORT_FAILED);

        return [
            'awaiting_erp' => KpiValue::available($awaiting)->toArray(),
            'blocked' => KpiValue::available($blocked)->toArray(),
            'non_importable' => KpiValue::available($nonImportable)->toArray(),
            'ready_for_import' => KpiValue::available($ready)->toArray(),
            'imported' => KpiValue::available($imported)->toArray(),
            'import_failed' => KpiValue::available($importFailed)->toArray(),
            'status_breakdown' => $this->fetchStatusBreakdown(),
            'blocked_orders' => $this->fetchBlockedOrders(self::TABLE_LIMIT),
            'alerts' => $this->fetchRecentAlerts(),
        ];
    }

    private function countByStatus(string $status): int
    {
        return $this->countByStatuses([$status]);
    }

    /**
     * @param string[] $statuses
     */
    private function countByStatuses(array $statuses): int
    {
        $select = $this->scopeHelper->createB2bOrderSelect();
        $select->reset(\Magento\Framework\DB\Select::COLUMNS)
            ->columns(['cnt' => new Expression('COUNT(DISTINCT so.entity_id)')])
            ->where('so.sectra_import_status IN (?)', $statuses);

        return (int) $this->resourceConnection->getConnection()->fetchOne($select);
    }

    /**
     * @return list<array{status: string, label: string, count: int}>
     */
    private function fetchStatusBreakdown(): array
    {
        $select = $this->scopeHelper->createB2bOrderSelect();
        $select->reset(\Magento\Framework\DB\Select::COLUMNS)
            ->columns([
                'status' => 'so.sectra_import_status',
                'cnt' => new Expression('COUNT(DISTINCT so.entity_id)'),
            ])
            ->where('so.sectra_import_status IS NOT NULL')
            ->where("so.sectra_import_status != ''")
            ->group('so.sectra_import_status');

        $rows = $this->resourceConnection->getConnection()->fetchAll($select);
        $labels = SectraImportStatus::labels();
        $result = [];

        foreach ($rows as $row) {
            $status = (string) $row['status'];
            $result[] = [
                'status' => $status,
                'label' => $labels[$status] ?? $status,
                'count' => (int) $row['cnt'],
            ];
        }

        usort($result, static fn (array $a, array $b): int => $b['count'] <=> $a['count']);

        return $result;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchBlockedOrders(int $limit): array
    {
        $select = $this->scopeHelper->createB2bOrderSelect();
        $select->reset(\Magento\Framework\DB\Select::COLUMNS)
            ->columns([
                'entity_id' => 'so.entity_id',
                'increment_id' => 'so.increment_id',
                'customer_id' => 'so.customer_id',
                'customer_name' => new Expression("CONCAT(so.customer_firstname, ' ', so.customer_lastname)"),
                'grand_total' => 'so.grand_total',
                'sectra_import_status' => 'so.sectra_import_status',
                'created_at' => 'so.created_at',
            ])
            ->where('so.sectra_import_status IN (?)', SectraImportStatus::NON_IMPORTABLE)
            ->order('so.created_at DESC')
            ->limit($limit);

        $rows = $this->resourceConnection->getConnection()->fetchAll($select);
        $labels = SectraImportStatus::labels();

        foreach ($rows as &$row) {
            $status = (string) ($row['sectra_import_status'] ?? '');
            $row['sectra_label'] = $labels[$status] ?? $status;
            $row['grand_total_formatted'] = (string) $this->priceHelper->currency((float) $row['grand_total'], true, false);
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchRecentAlerts(): array
    {
        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName('grupoawamotos_b2b_sectra_sync_log');

        if (!$connection->isTableExists($table)) {
            return [];
        }

        $since = (new \DateTimeImmutable('-24 hours'))->format('Y-m-d H:i:s');
        $select = $connection->select()
            ->from($table, ['log_id', 'event_type', 'level', 'message', 'order_id', 'customer_id', 'created_at'])
            ->where('created_at >= ?', $since)
            ->where('level = ?', 'error')
            ->order('created_at DESC')
            ->limit(10);

        return $connection->fetchAll($select);
    }
}
