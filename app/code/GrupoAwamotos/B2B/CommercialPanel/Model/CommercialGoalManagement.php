<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\CommercialPanel\Model;

use GrupoAwamotos\B2B\CommercialPanel\Api\CommercialGoalManagementInterface;
use GrupoAwamotos\B2B\CommercialPanel\Api\Data\CommercialGoalInterface;
use GrupoAwamotos\B2B\CommercialPanel\Api\PortfolioScopeInterface;
use GrupoAwamotos\B2B\CommercialPanel\Model\ResourceModel\CommercialGoal\CollectionFactory as GoalCollectionFactory;
use Magento\Framework\Exception\LocalizedException;

class CommercialGoalManagement implements CommercialGoalManagementInterface
{
    public function __construct(
        private readonly CommercialGoalFactory $goalFactory,
        private readonly GoalCollectionFactory $goalCollectionFactory,
        private readonly PortfolioScopeInterface $portfolioScope
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function saveGoal(array $data): CommercialGoalInterface
    {
        $attendantId = (int) ($data['attendant_id'] ?? 0);
        $periodMonth = (string) ($data['period_month'] ?? '');

        if ($attendantId <= 0 || !preg_match('/^\d{4}-\d{2}$/', $periodMonth)) {
            throw new LocalizedException(__('Dados de meta inválidos.'));
        }

        if (!$this->canManageAttendant($attendantId)) {
            throw new LocalizedException(__('Sem permissão para gerenciar meta desta vendedora.'));
        }

        $goalId = (int) ($data['goal_id'] ?? 0);
        /** @var CommercialGoal $goal */
        $goal = $goalId > 0
            ? $this->goalFactory->create()->load($goalId)
            : $this->findExisting($attendantId, $periodMonth) ?? $this->goalFactory->create();

        if ($goalId > 0 && !$goal->getGoalId()) {
            throw new LocalizedException(__('Meta não encontrada.'));
        }

        $goal->setAttendantId($attendantId);
        $goal->setPeriodMonth($periodMonth);
        $goal->setRevenueGoal((float) ($data['revenue_goal'] ?? 0));
        $goal->setContactsGoal((int) ($data['contacts_goal'] ?? 0));
        $goal->setReactivatedGoal((int) ($data['reactivated_goal'] ?? 0));
        $goal->save();

        return $goal;
    }

    public function deleteGoal(int $goalId): bool
    {
        $goal = $this->goalFactory->create()->load($goalId);
        if (!$goal->getGoalId()) {
            return false;
        }

        if (!$this->canManageAttendant($goal->getAttendantId())) {
            throw new LocalizedException(__('Sem permissão para excluir esta meta.'));
        }

        $goal->delete();

        return true;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getGoalByAttendantAndMonth(int $attendantId, string $periodMonth): ?array
    {
        $goal = $this->findExisting($attendantId, $periodMonth);

        return $goal?->getData();
    }

    private function findExisting(int $attendantId, string $periodMonth): ?CommercialGoal
    {
        $collection = $this->goalCollectionFactory->create();
        $collection->addFieldToFilter('attendant_id', $attendantId);
        $collection->addFieldToFilter('period_month', $periodMonth);
        $collection->setPageSize(1);
        $item = $collection->getFirstItem();

        return $item->getGoalId() ? $item : null;
    }

    private function canManageAttendant(int $attendantId): bool
    {
        if ($this->portfolioScope->canBypassPortfolioScope()) {
            return true;
        }

        return in_array($attendantId, $this->portfolioScope->getVisibleAttendantIds(), true);
    }
}
