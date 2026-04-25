<?php
declare(strict_types=1);

namespace GrupoAwamotos\LogMonitoring\Cron;

use GrupoAwamotos\LogMonitoring\Service\SystemHealthService;
use GrupoAwamotos\LogMonitoring\Service\NotificationService;
use Psr\Log\LoggerInterface;

class SystemHealthCheck
{
    private SystemHealthService $systemHealthService;
    private NotificationService $notificationService;
    private LoggerInterface $logger;

    public function __construct(
        SystemHealthService $systemHealthService,
        NotificationService $notificationService,
        LoggerInterface $logger
    ) {
        $this->systemHealthService = $systemHealthService;
        $this->notificationService = $notificationService;
        $this->logger = $logger;
    }

    public function execute(): void
    {
        try {
            $healthData = $this->systemHealthService->getOverallHealth();
            
            // Update component health in database
            if (isset($healthData['components'])) {
                foreach ($healthData['components'] as $component => $data) {
                    $this->systemHealthService->updateComponentHealth($component, $data);
                }
            }
            
            // Send alert if system health is poor
            if ($healthData['overall_score'] < 70) {
                $this->notificationService->sendSystemHealthAlert($healthData);
            }
            
            $this->logger->info('Completed system health check', [
                'status' => $healthData['overall_status'],
                'score' => $healthData['overall_score']
            ]);
            
        } catch (\Throwable $e) {
            $this->logger->error('Error in system health check: ' . $e->getMessage());
        }
    }
}
