<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Api;

use GrupoAwamotos\B2B\Api\Data\ApprovalScoreResultInterface;

interface ApprovalScoreServiceInterface
{
    /**
     * Avalia o cadastro B2B e retorna score com motivo e grupo sugerido.
     */
    public function evaluate(int $customerId): ApprovalScoreResultInterface;

    /**
     * Persiste score, motivo e grupo sugerido nos atributos do cliente.
     */
    public function persistScore(int $customerId, ApprovalScoreResultInterface $result): void;

    /**
     * Avalia, persiste e aplica auto-aprovação quando score for verde.
     *
     * @return ApprovalScoreResultInterface Resultado da triagem
     */
    public function processRegistration(int $customerId): ApprovalScoreResultInterface;
}
