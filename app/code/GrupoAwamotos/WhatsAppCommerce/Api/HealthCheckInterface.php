<?php

declare(strict_types=1);

namespace GrupoAwamotos\WhatsAppCommerce\Api;

/**
 * WhatsApp Commerce Health Check API
 *
 * Endpoint para N8N e monitoramento verificarem se o módulo está operacional.
 */
interface HealthCheckInterface
{
    /**
     * Check overall system health
     *
     * @return mixed[] Health status with component checks
     */
    public function check(): array;
}
