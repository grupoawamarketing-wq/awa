<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\CommercialPanel\Model;

use GrupoAwamotos\B2B\CommercialPanel\Api\Data\CommercialGoalInterface;
use GrupoAwamotos\B2B\CommercialPanel\Model\ResourceModel\CommercialGoalResource;
use Magento\Framework\Model\AbstractModel;

class CommercialGoal extends AbstractModel implements CommercialGoalInterface
{
    protected function _construct(): void
    {
        $this->_init(CommercialGoalResource::class);
    }

    public function getGoalId(): ?int
    {
        $id = $this->getData(self::GOAL_ID);

        return $id !== null ? (int) $id : null;
    }

    public function setGoalId(int $goalId): CommercialGoalInterface
    {
        return $this->setData(self::GOAL_ID, $goalId);
    }

    public function getAttendantId(): int
    {
        return (int) $this->getData(self::ATTENDANT_ID);
    }

    public function setAttendantId(int $attendantId): CommercialGoalInterface
    {
        return $this->setData(self::ATTENDANT_ID, $attendantId);
    }

    public function getPeriodMonth(): string
    {
        return (string) $this->getData(self::PERIOD_MONTH);
    }

    public function setPeriodMonth(string $periodMonth): CommercialGoalInterface
    {
        return $this->setData(self::PERIOD_MONTH, $periodMonth);
    }

    public function getRevenueGoal(): float
    {
        return (float) $this->getData(self::REVENUE_GOAL);
    }

    public function setRevenueGoal(float $revenueGoal): CommercialGoalInterface
    {
        return $this->setData(self::REVENUE_GOAL, $revenueGoal);
    }

    public function getContactsGoal(): int
    {
        return (int) $this->getData(self::CONTACTS_GOAL);
    }

    public function setContactsGoal(int $contactsGoal): CommercialGoalInterface
    {
        return $this->setData(self::CONTACTS_GOAL, $contactsGoal);
    }

    public function getReactivatedGoal(): int
    {
        return (int) $this->getData(self::REACTIVATED_GOAL);
    }

    public function setReactivatedGoal(int $reactivatedGoal): CommercialGoalInterface
    {
        return $this->setData(self::REACTIVATED_GOAL, $reactivatedGoal);
    }
}
