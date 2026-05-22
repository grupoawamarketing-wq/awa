<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\CommercialPanel\Api\Data;

interface CommercialGoalInterface
{
    public const GOAL_ID = 'goal_id';
    public const ATTENDANT_ID = 'attendant_id';
    public const PERIOD_MONTH = 'period_month';
    public const REVENUE_GOAL = 'revenue_goal';
    public const CONTACTS_GOAL = 'contacts_goal';
    public const REACTIVATED_GOAL = 'reactivated_goal';

    public function getGoalId(): ?int;

    public function setGoalId(int $goalId): self;

    public function getAttendantId(): int;

    public function setAttendantId(int $attendantId): self;

    public function getPeriodMonth(): string;

    public function setPeriodMonth(string $periodMonth): self;

    public function getRevenueGoal(): float;

    public function setRevenueGoal(float $revenueGoal): self;

    public function getContactsGoal(): int;

    public function setContactsGoal(int $contactsGoal): self;

    public function getReactivatedGoal(): int;

    public function setReactivatedGoal(int $reactivatedGoal): self;
}
