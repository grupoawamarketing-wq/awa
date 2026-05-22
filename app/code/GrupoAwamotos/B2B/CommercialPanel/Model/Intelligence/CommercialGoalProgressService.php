<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\CommercialPanel\Model\Intelligence;

use GrupoAwamotos\B2B\CommercialPanel\Api\PortfolioScopeInterface;
use GrupoAwamotos\B2B\CommercialPanel\Model\Config\TaskConfig;
use GrupoAwamotos\B2B\CommercialPanel\Model\ResourceModel\CommercialGoal\CollectionFactory as GoalCollectionFactory;
use Magento\Framework\App\ResourceConnection;

/**
 * Calcula realizado vs meta comercial por vendedora/mês.
 */
class CommercialGoalProgressService
{
    public function __construct(
        private readonly PortfolioScopeInterface $portfolioScope,
        private readonly GoalCollectionFactory $goalCollectionFactory,
        private readonly ResourceConnection $resourceConnection,
        private readonly TaskConfig $taskConfig
    ) {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getProgressForPeriod(?string $periodMonth = null): array
    {
        $periodMonth = $periodMonth ?? date('Y-m');
        $attendantIds = $this->portfolioScope->getVisibleAttendantIds();
        if ($attendantIds === []) {
            return [];
        }

        $goals = $this->loadGoals($attendantIds, $periodMonth);
        $attendantNames = $this->loadAttendantNames($attendantIds);
        [$periodStart, $periodEnd] = $this->periodBounds($periodMonth);

        $revenueByAttendant = $this->sumRevenueByAttendant($attendantIds, $periodStart, $periodEnd);
        $contactsByAttendant = $this->countContactsByAttendant($attendantIds, $periodStart, $periodEnd);
        $reactivatedByAttendant = $this->countReactivatedByAttendant($attendantIds, $periodStart, $periodEnd);

        $result = [];
        foreach ($attendantIds as $attendantId) {
            $goal = $goals[$attendantId] ?? [
                'revenue_goal' => 0.0,
                'contacts_goal' => 0,
                'reactivated_goal' => 0,
                'goal_id' => null,
            ];

            $result[] = $this->buildProgressRow(
                $attendantId,
                (string) ($attendantNames[$attendantId] ?? ''),
                $periodMonth,
                $goal,
                (float) ($revenueByAttendant[$attendantId] ?? 0.0),
                (int) ($contactsByAttendant[$attendantId] ?? 0),
                (int) ($reactivatedByAttendant[$attendantId] ?? 0)
            );
        }

        return $result;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getOwnProgress(?string $periodMonth = null): ?array
    {
        $rows = $this->getProgressForPeriod($periodMonth);

        return $rows[0] ?? null;
    }

    /**
     * @param array<string, mixed> $goal
     * @return array<string, mixed>
     */
    private function buildProgressRow(
        int $attendantId,
        string $attendantName,
        string $periodMonth,
        array $goal,
        float $actualRevenue,
        int $actualContacts,
        int $actualReactivated
    ): array {
        $revenueGoal = (float) ($goal['revenue_goal'] ?? 0);
        $contactsGoal = (int) ($goal['contacts_goal'] ?? 0);
        $reactivatedGoal = (int) ($goal['reactivated_goal'] ?? 0);

        return [
            'goal_id' => $goal['goal_id'] ?? null,
            'attendant_id' => $attendantId,
            'attendant_name' => $attendantName,
            'period_month' => $periodMonth,
            'revenue_goal' => $revenueGoal,
            'revenue_actual' => $actualRevenue,
            'revenue_pct' => $this->percent($actualRevenue, $revenueGoal),
            'revenue_remaining' => max(0, $revenueGoal - $actualRevenue),
            'contacts_goal' => $contactsGoal,
            'contacts_actual' => $actualContacts,
            'contacts_pct' => $this->percent((float) $actualContacts, (float) $contactsGoal),
            'contacts_remaining' => max(0, $contactsGoal - $actualContacts),
            'reactivated_goal' => $reactivatedGoal,
            'reactivated_actual' => $actualReactivated,
            'reactivated_pct' => $this->percent((float) $actualReactivated, (float) $reactivatedGoal),
            'reactivated_remaining' => max(0, $reactivatedGoal - $actualReactivated),
        ];
    }

    private function percent(float $actual, float $goal): float
    {
        if ($goal <= 0) {
            return 0.0;
        }

        return round(min(100.0, ($actual / $goal) * 100), 1);
    }

    /**
     * @param int[] $attendantIds
     * @return array<int, array<string, mixed>>
     */
    private function loadGoals(array $attendantIds, string $periodMonth): array
    {
        $collection = $this->goalCollectionFactory->create();
        $collection->addFieldToFilter('attendant_id', ['in' => $attendantIds]);
        $collection->addFieldToFilter('period_month', $periodMonth);

        $result = [];
        foreach ($collection as $goal) {
            $result[(int) $goal->getAttendantId()] = [
                'goal_id' => $goal->getGoalId(),
                'revenue_goal' => $goal->getRevenueGoal(),
                'contacts_goal' => $goal->getContactsGoal(),
                'reactivated_goal' => $goal->getReactivatedGoal(),
            ];
        }

        return $result;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function periodBounds(string $periodMonth): array
    {
        $start = $periodMonth . '-01 00:00:00';
        $end = (new \DateTimeImmutable($start))->modify('last day of this month')->format('Y-m-d 23:59:59');

        return [$start, $end];
    }

    /**
     * @param int[] $attendantIds
     * @return array<int, float>
     */
    private function sumRevenueByAttendant(array $attendantIds, string $from, string $to): array
    {
        if ($attendantIds === []) {
            return [];
        }

        $connection = $this->resourceConnection->getConnection();
        $mappingTable = $this->resourceConnection->getTableName('grupoawamotos_b2b_customer_attendant');
        $orderTable = $this->resourceConnection->getTableName('sales_order');

        $rows = $connection->fetchPairs(
            $connection->select()
                ->from(['ca' => $mappingTable], ['attendant_id', 'total' => new \Magento\Framework\DB\Sql\Expression('ROUND(SUM(so.grand_total), 2)')])
                ->joinInner(['so' => $orderTable], 'so.customer_id = ca.customer_id', [])
                ->where('ca.attendant_id IN (?)', $attendantIds)
                ->where('so.created_at >= ?', $from)
                ->where('so.created_at <= ?', $to)
                ->where('so.state NOT IN (?)', ['canceled'])
                ->group('ca.attendant_id')
        );

        $result = [];
        foreach ($rows as $attendantId => $total) {
            $result[(int) $attendantId] = (float) $total;
        }

        return $result;
    }

    /**
     * @param int[] $attendantIds
     * @return array<int, int>
     */
    private function countContactsByAttendant(array $attendantIds, string $from, string $to): array
    {
        if ($attendantIds === []) {
            return [];
        }

        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName('grupoawamotos_b2b_contact_log');
        $rows = $connection->fetchPairs(
            $connection->select()
                ->from($table, ['attendant_id', 'cnt' => new \Magento\Framework\DB\Sql\Expression('COUNT(*)')])
                ->where('attendant_id IN (?)', $attendantIds)
                ->where('created_at >= ?', $from)
                ->where('created_at <= ?', $to)
                ->group('attendant_id')
        );

        $result = [];
        foreach ($rows as $attendantId => $count) {
            $result[(int) $attendantId] = (int) $count;
        }

        return $result;
    }

    /**
     * Cliente reativado = comprou no período após inatividade >= days_no_purchase.
     *
     * @param int[] $attendantIds
     * @return array<int, int>
     */
    private function countReactivatedByAttendant(array $attendantIds, string $from, string $to): array
    {
        if ($attendantIds === []) {
            return [];
        }

        $inactiveDays = $this->taskConfig->getDaysNoPurchase();
        $connection = $this->resourceConnection->getConnection();
        $mappingTable = $this->resourceConnection->getTableName('grupoawamotos_b2b_customer_attendant');
        $orderTable = $this->resourceConnection->getTableName('sales_order');

        $lastPriorSelect = $connection->select()
            ->from($orderTable, ['customer_id', 'last_prior_at' => new \Magento\Framework\DB\Sql\Expression('MAX(created_at)')])
            ->where('created_at < ?', $from)
            ->where('state NOT IN (?)', ['canceled'])
            ->group('customer_id');

        $reactivatedSelect = $connection->select()
            ->from(['so' => $orderTable], ['customer_id'])
            ->joinInner(['prior' => $lastPriorSelect], 'prior.customer_id = so.customer_id', [])
            ->where('so.created_at >= ?', $from)
            ->where('so.created_at <= ?', $to)
            ->where('so.state NOT IN (?)', ['canceled'])
            ->where('DATEDIFF(CURDATE(), DATE(prior.last_prior_at)) >= ?', $inactiveDays)
            ->group('so.customer_id');

        $rows = $connection->fetchPairs(
            $connection->select()
                ->from(['ca' => $mappingTable], ['attendant_id', 'cnt' => new \Magento\Framework\DB\Sql\Expression('COUNT(DISTINCT ca.customer_id)')])
                ->joinInner(['reactivated' => $reactivatedSelect], 'reactivated.customer_id = ca.customer_id', [])
                ->where('ca.attendant_id IN (?)', $attendantIds)
                ->group('ca.attendant_id')
        );

        $result = [];
        foreach ($rows as $attendantId => $count) {
            $result[(int) $attendantId] = (int) $count;
        }

        return $result;
    }

    /**
     * @param int[] $attendantIds
     * @return array<int, string>
     */
    private function loadAttendantNames(array $attendantIds): array
    {
        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName('grupoawamotos_b2b_attendants');
        $rows = $connection->fetchPairs(
            $connection->select()
                ->from($table, ['attendant_id', 'name'])
                ->where('attendant_id IN (?)', $attendantIds)
        );

        $result = [];
        foreach ($rows as $id => $name) {
            $result[(int) $id] = (string) $name;
        }

        return $result;
    }
}
