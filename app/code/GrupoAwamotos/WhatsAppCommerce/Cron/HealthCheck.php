<?php

declare(strict_types=1);

namespace GrupoAwamotos\WhatsAppCommerce\Cron;

use GrupoAwamotos\WhatsAppCommerce\Api\HealthCheckInterface;
use GrupoAwamotos\WhatsAppCommerce\Helper\Config;
use Psr\Log\LoggerInterface;

/**
 * Cron health check — roda a cada 5 min e loga se algum componente estiver offline.
 */
class HealthCheck
{
    public function __construct(
        private readonly HealthCheckInterface $healthCheck,
        private readonly Config $config,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function execute(): void
    {
        if (!$this->config->isEnabled()) {
            return;
        }

        try {
            $result = $this->healthCheck->check();

            if (!$result['healthy']) {
                $failures = [];
                foreach ($result['checks'] as $name => $check) {
                    if ($check['status'] === 'error') {
                        $failures[] = "{$name}: {$check['message']}";
                    }
                }

                $this->logger->error('[HealthCheck] WhatsApp Commerce unhealthy', [
                    'failures' => $failures,
                    'timestamp' => $result['timestamp'],
                ]);
            } else {
                $this->logger->debug('[HealthCheck] WhatsApp Commerce healthy');
            }
        } catch (\Exception $e) {
            $this->logger->error('[HealthCheck] Cron failed: ' . $e->getMessage());
        }
    }
}
