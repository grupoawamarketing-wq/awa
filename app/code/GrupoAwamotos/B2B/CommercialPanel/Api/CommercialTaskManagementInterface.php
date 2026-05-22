<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\CommercialPanel\Api;

use GrupoAwamotos\B2B\CommercialPanel\Api\Data\CommercialTaskInterface;

interface CommercialTaskManagementInterface
{
    /**
     * Cria tarefa manual validando escopo da carteira.
     *
     * @param array<string, mixed> $data
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function createManual(array $data, int $adminUserId): CommercialTaskInterface;

    /**
     * Cria tarefa automática (cron) — idempotente via dedup_key.
     *
     * @param array<string, mixed> $data
     */
    public function createAutomatic(array $data): ?CommercialTaskInterface;

    /**
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function complete(int $taskId, int $adminUserId): CommercialTaskInterface;

    /**
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function reschedule(int $taskId, string $dueAt, int $adminUserId): CommercialTaskInterface;

    public function hasOpenTaskByDedupKey(string $dedupKey): bool;

    public function existsByDedupKey(string $dedupKey): bool;
}
