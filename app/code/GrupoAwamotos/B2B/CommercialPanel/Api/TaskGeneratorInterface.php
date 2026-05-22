<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\CommercialPanel\Api;

interface TaskGeneratorInterface
{
    /**
     * Executa todas as regras de geração de tarefas.
     *
     * @return array{created: int, skipped: int}
     */
    public function generateAll(): array;
}
