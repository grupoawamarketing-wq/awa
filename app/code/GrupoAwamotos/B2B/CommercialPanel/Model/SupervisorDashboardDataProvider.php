<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\CommercialPanel\Model;

use GrupoAwamotos\B2B\CommercialPanel\Api\PortfolioScopeInterface;
use GrupoAwamotos\B2B\CommercialPanel\Model\Config\TaskConfig;
use Magento\Framework\App\ResourceConnection;

/**
 * Métricas consolidadas da equipe comercial (supervisora / TI).
 */
class SupervisorDashboardDataProvider
{
    /** @var string[] */
    private const OPEN_STATUSES = ['open', 'in_progress'];

    public function __construct(
        private readonly PortfolioScopeInterface $portfolioScope,
        private readonly ResourceConnection $resourceConnection,
        private readonly TaskConfig $taskConfig
    ) {
    }

    public function isSupervisorView(): bool
    {
        return $this->portfolioScope->canViewAllPortfolios();
    }

    /**
     * @return array<string, mixed>
     */
    public function getTeamSummary(): array
    {
        $attendantIds = $this->portfolioScope->getVisibleAttendantIds();
        $customerIds = $this->portfolioScope->getVisibleCustomerIds();

        return [
            'active_sellers_count' => count($attendantIds),
            'team_customers_count' => count($customerIds),
            'customers_no_contact_count' => $this->countCustomersNoRecentContact($customerIds),
            'overdue_tasks_count' => $this->countOverdueTasks($attendantIds),
            'abandoned_carts_count' => $this->countAbandonedCarts($customerIds),
            'contacts_month_count' => $this->countContactsThisMonth($attendantIds),
            'orders_30d_count' => $this->countRecentOrders($customerIds, 30),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getSellerRanking(): array
    {
        $attendantIds = $this->portfolioScope->getVisibleAttendantIds();
        if ($attendantIds === []) {
            return [];
        }

        $connection = $this->resourceConnection->getConnection();
        $attendantTable = $this->resourceConnection->getTableName('grupoawamotos_b2b_attendants');
        $mappingTable = $this->resourceConnection->getTableName('grupoawamotos_b2b_customer_attendant');

        $names = $connection->fetchPairs(
            $connection->select()
                ->from($attendantTable, ['attendant_id', 'name'])
                ->where('attendant_id IN (?)', $attendantIds)
        );

        $clientCounts = $connection->fetchPairs(
            $connection->select()
                ->from($mappingTable, ['attendant_id', 'cnt' => new \Magento\Framework\DB\Sql\Expression('COUNT(*)')])
                ->where('attendant_id IN (?)', $attendantIds)
                ->group('attendant_id')
        );

        $monthStart = (new \DateTimeImmutable('first day of this month'))->format('Y-m-d 00:00:00');
        $since30 = (new \DateTimeImmutable())->modify('-30 days')->format('Y-m-d H:i:s');
        $now = date('Y-m-d H:i:s');

        $contactsMonth = $this->fetchCountGroupedByAttendant(
            'grupoawamotos_b2b_contact_log',
            $attendantIds,
            ['created_at >= ?' => $monthStart]
        );
        $openTasks = $this->fetchCountGroupedByAttendant(
            'grupoawamotos_b2b_commercial_task',
            $attendantIds,
            ['status IN (?)' => self::OPEN_STATUSES]
        );
        $overdueTasks = $this->fetchCountGroupedByAttendant(
            'grupoawamotos_b2b_commercial_task',
            $attendantIds,
            [
                'status IN (?)' => self::OPEN_STATUSES,
                'due_at IS NOT NULL' => null,
                'due_at < ?' => $now,
            ]
        );
        $orders30d = $this->fetchOrders30dByAttendant($attendantIds, $since30);

        $ranking = [];
        foreach ($attendantIds as $attendantId) {
            $ranking[] = [
                'attendant_id' => $attendantId,
                'attendant_name' => (string) ($names[$attendantId] ?? ''),
                'customers_count' => (int) ($clientCounts[$attendantId] ?? 0),
                'contacts_month' => (int) ($contactsMonth[$attendantId] ?? 0),
                'orders_30d' => (int) ($orders30d[$attendantId] ?? 0),
                'open_tasks' => (int) ($openTasks[$attendantId] ?? 0),
                'overdue_tasks' => (int) ($overdueTasks[$attendantId] ?? 0),
            ];
        }

        usort($ranking, static fn (array $a, array $b): int => ($b['contacts_month'] <=> $a['contacts_month']));

        return $ranking;
    }

    /**
     * @param int[] $customerIds
     */
    private function countCustomersNoRecentContact(array $customerIds): int
    {
        if ($customerIds === []) {
            return 0;
        }

        $days = $this->taskConfig->getDaysNewCustomerNoContact();
        $since = (new \DateTimeImmutable())->modify(sprintf('-%d days', $days))->format('Y-m-d H:i:s');
        $connection = $this->resourceConnection->getConnection();
        $contactTable = $this->resourceConnection->getTableName('grupoawamotos_b2b_contact_log');

        if (!$connection->isTableExists($contactTable)) {
            return count($customerIds);
        }

        $withContact = $connection->fetchCol(
            $connection->select()
                ->from($contactTable, ['customer_id'])
                ->where('customer_id IN (?)', $customerIds)
                ->where('created_at >= ?', $since)
                ->group('customer_id')
        );

        return count($customerIds) - count(array_unique(array_map('intval', $withContact)));
    }

    /**
     * @param int[] $attendantIds
     */
    private function countOverdueTasks(array $attendantIds): int
    {
        if ($attendantIds === []) {
            return 0;
        }

        $now = date('Y-m-d H:i:s');
        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName('grupoawamotos_b2b_commercial_task');

        return (int) $connection->fetchOne(
            $connection->select()
                ->from($table, ['cnt' => new \Magento\Framework\DB\Sql\Expression('COUNT(*)')])
                ->where('attendant_id IN (?)', $attendantIds)
                ->where('status IN (?)', self::OPEN_STATUSES)
                ->where('due_at IS NOT NULL')
                ->where('due_at < ?', $now)
        );
    }

    /**
     * @param int[] $customerIds
     */
    private function countAbandonedCarts(array $customerIds): int
    {
        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName('grupoawamotos_abandoned_cart');
        if (!$connection->isTableExists($table) || $customerIds === []) {
            return 0;
        }

        $select = $connection->select()
            ->from($table, ['cnt' => new \Magento\Framework\DB\Sql\Expression('COUNT(*)')])
            ->where('customer_id IN (?)', $customerIds)
            ->where('recovered = ?', 0)
            ->where('status != ?', 'recovered');

        return (int) $connection->fetchOne($select);
    }

    /**
     * @param int[] $attendantIds
     */
    private function countContactsThisMonth(array $attendantIds): int
    {
        if ($attendantIds === []) {
            return 0;
        }

        $monthStart = (new \DateTimeImmutable('first day of this month'))->format('Y-m-d 00:00:00');
        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName('grupoawamotos_b2b_contact_log');

        return (int) $connection->fetchOne(
            $connection->select()
                ->from($table, ['cnt' => new \Magento\Framework\DB\Sql\Expression('COUNT(*)')])
                ->where('attendant_id IN (?)', $attendantIds)
                ->where('created_at >= ?', $monthStart)
        );
    }

    /**
     * @param int[] $customerIds
     */
    private function countRecentOrders(array $customerIds, int $days): int
    {
        if ($customerIds === []) {
            return 0;
        }

        $since = (new \DateTimeImmutable())->modify(sprintf('-%d days', $days))->format('Y-m-d H:i:s');
        $connection = $this->resourceConnection->getConnection();
        $orderTable = $this->resourceConnection->getTableName('sales_order');

        return (int) $connection->fetchOne(
            $connection->select()
                ->from($orderTable, ['cnt' => new \Magento\Framework\DB\Sql\Expression('COUNT(*)')])
                ->where('customer_id IN (?)', $customerIds)
                ->where('created_at >= ?', $since)
        );
    }

    /**
     * @param int[] $attendantIds
     * @param array<string, mixed> $extraWhere
     * @return array<int, int>
     */
    private function fetchCountGroupedByAttendant(string $tableCode, array $attendantIds, array $extraWhere): array
    {
        if ($attendantIds === []) {
            return [];
        }

        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName($tableCode);
        $select = $connection->select()
            ->from($table, ['attendant_id', 'cnt' => new \Magento\Framework\DB\Sql\Expression('COUNT(*)')])
            ->where('attendant_id IN (?)', $attendantIds)
            ->group('attendant_id');

        foreach ($extraWhere as $condition => $bind) {
            if ($bind === null) {
                $select->where($condition);
            } else {
                $select->where($condition, $bind);
            }
        }

        $rows = $connection->fetchPairs($select);
        $result = [];
        foreach ($rows as $attendantId => $count) {
            $result[(int) $attendantId] = (int) $count;
        }

        return $result;
    }

    /**
     * @param int[] $attendantIds
     * @return array<int, int>
     */
    private function fetchOrders30dByAttendant(array $attendantIds, string $since30): array
    {
        if ($attendantIds === []) {
            return [];
        }

        $connection = $this->resourceConnection->getConnection();
        $mappingTable = $this->resourceConnection->getTableName('grupoawamotos_b2b_customer_attendant');
        $orderTable = $this->resourceConnection->getTableName('sales_order');

        $rows = $connection->fetchPairs(
            $connection->select()
                ->from(['ca' => $mappingTable], ['attendant_id', 'cnt' => new \Magento\Framework\DB\Sql\Expression('COUNT(DISTINCT so.entity_id)')])
                ->joinInner(['so' => $orderTable], 'so.customer_id = ca.customer_id', [])
                ->where('ca.attendant_id IN (?)', $attendantIds)
                ->where('so.created_at >= ?', $since30)
                ->group('ca.attendant_id')
        );

        $result = [];
        foreach ($rows as $attendantId => $count) {
            $result[(int) $attendantId] = (int) $count;
        }

        return $result;
    }
}
