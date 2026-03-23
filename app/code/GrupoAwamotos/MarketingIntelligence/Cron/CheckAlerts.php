<?php

declare(strict_types=1);

namespace GrupoAwamotos\MarketingIntelligence\Cron;

use GrupoAwamotos\MarketingIntelligence\Model\Service\AlertService;
use Psr\Log\LoggerInterface;

class CheckAlerts
{
    public function __construct(
        private readonly AlertService $alertService,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(): void
    {
        $this->logger->info('Cron CheckAlerts: starting.');

        try {
            $alerts = $this->alertService->checkAlerts();

            if (empty($alerts)) {
                $this->logger->info('Cron CheckAlerts: no active alerts.');
                return;
            }

            foreach ($alerts as $alert) {
                $severity = strtoupper($alert['severity'] ?? 'info');
                $this->logger->warning(sprintf(
                    'Cron CheckAlerts [%s]: %s (value=%s, threshold=%s)',
                    $severity,
                    $alert['message'],
                    (string) $alert['value'],
                    (string) $alert['threshold']
                ));
            }

            $this->logger->info(sprintf('Cron CheckAlerts: %d alert(s) found.', count($alerts)));
        } catch (\Exception $e) {
            $this->logger->error('Cron CheckAlerts: failed — ' . $e->getMessage());
        }
    }
}
