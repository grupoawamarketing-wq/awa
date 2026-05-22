<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\CommercialPanel\Cron;

use GrupoAwamotos\B2B\CommercialPanel\Api\TaskGeneratorInterface;
use Psr\Log\LoggerInterface;

class GenerateCommercialTasks
{
    public function __construct(
        private readonly TaskGeneratorInterface $taskGenerator,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(): void
    {
        try {
            $result = $this->taskGenerator->generateAll();
            $this->logger->info('[AWA Commercial Task Cron] Finalizado', $result);
        } catch (\Exception $e) {
            $this->logger->error('[AWA Commercial Task Cron] Erro: ' . $e->getMessage());
        }
    }
}
