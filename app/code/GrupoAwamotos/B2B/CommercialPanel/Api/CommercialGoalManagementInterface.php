<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\CommercialPanel\Api;

use GrupoAwamotos\B2B\CommercialPanel\Api\Data\CommercialGoalInterface;

interface CommercialGoalManagementInterface
{
    /**
     * @param array<string, mixed> $data
     */
    public function saveGoal(array $data): CommercialGoalInterface;

    public function deleteGoal(int $goalId): bool;

    /**
     * @return array<string, mixed>|null
     */
    public function getGoalByAttendantAndMonth(int $attendantId, string $periodMonth): ?array;
}
