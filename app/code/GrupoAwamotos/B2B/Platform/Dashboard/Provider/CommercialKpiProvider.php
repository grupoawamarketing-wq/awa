<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Platform\Dashboard\Provider;

use GrupoAwamotos\B2B\CommercialPanel\Model\DashboardDataProvider;
use GrupoAwamotos\B2B\CommercialPanel\Model\ResourceModel\CommercialTask\CollectionFactory as TaskCollectionFactory;
use GrupoAwamotos\B2B\Model\ResourceModel\Attendant\CollectionFactory as AttendantCollectionFactory;
use GrupoAwamotos\B2B\Platform\Dashboard\B2bDashboardScopeHelper;
use GrupoAwamotos\B2B\Platform\Dashboard\DashboardFilter;
use GrupoAwamotos\B2B\Platform\Dashboard\KpiValue;
use Magento\Framework\App\ResourceConnection;

/**
 * KPIs comerciais — delega fontes reais existentes.
 */
class CommercialKpiProvider implements KpiProviderInterface
{
    /** @var string[] */
    private const OPEN_STATUSES = ['open', 'in_progress'];

    public function __construct(
        private readonly B2bDashboardScopeHelper $scopeHelper,
        private readonly DashboardDataProvider $dashboardDataProvider,
        private readonly TaskCollectionFactory $taskCollectionFactory,
        private readonly AttendantCollectionFactory $attendantCollectionFactory,
        private readonly ResourceConnection $resourceConnection
    ) {
    }

    /**
     * @inheritdoc
     */
    public function getData(DashboardFilter $filter): array
    {
        $attendantIds = $this->scopeHelper->getVisibleAttendantIds();
        $openTasks = $this->countOpenTasks($attendantIds);
        $activeAttendants = $this->countActiveAttendants($attendantIds);
        $abandonedCarts = $this->countAbandonedCarts();

        return [
            'open_tasks' => $activeAttendants > 0 || $this->scopeHelper->canBypassPortfolioScope()
                ? KpiValue::available($openTasks)->toArray()
                : KpiValue::unavailable()->toArray(),
            'active_attendants' => KpiValue::available($activeAttendants)->toArray(),
            'abandoned_carts' => $this->isAbandonedCartSourceAvailable()
                ? KpiValue::available($abandonedCarts)->toArray()
                : KpiValue::unavailable()->toArray(),
            'pending_b2b_approval' => KpiValue::available(
                $this->dashboardDataProvider->getPendingB2bCount()
            )->toArray(),
        ];
    }

    /**
     * @param int[] $attendantIds
     */
    private function countOpenTasks(array $attendantIds): int
    {
        if ($attendantIds === []) {
            return 0;
        }

        $collection = $this->taskCollectionFactory->create();
        $collection->addFieldToFilter('attendant_id', ['in' => $attendantIds]);
        $collection->addFieldToFilter('status', ['in' => self::OPEN_STATUSES]);

        return (int) $collection->getSize();
    }

    /**
     * @param int[] $attendantIds
     */
    private function countActiveAttendants(array $attendantIds): int
    {
        if ($attendantIds === []) {
            return 0;
        }

        $collection = $this->attendantCollectionFactory->create();
        $collection->addFieldToFilter('attendant_id', ['in' => $attendantIds]);
        $collection->addFieldToFilter('is_active', 1);

        return (int) $collection->getSize();
    }

    private function countAbandonedCarts(): int
    {
        $summary = $this->dashboardDataProvider->getSummary();

        return (int) ($summary['abandoned_carts_count'] ?? 0);
    }

    private function isAbandonedCartSourceAvailable(): bool
    {
        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName('grupoawamotos_abandoned_cart');

        return $connection->isTableExists($table);
    }
}
