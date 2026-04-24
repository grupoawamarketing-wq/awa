<?php
declare(strict_types=1);

namespace GrupoAwamotos\LogMonitoring\Test\Unit\Service\AgentTermination;

use GrupoAwamotos\LogMonitoring\Api\AlertRepositoryInterface;
use GrupoAwamotos\LogMonitoring\Api\Data\AlertInterface;
use GrupoAwamotos\LogMonitoring\Api\Data\AlertInterfaceFactory;
use GrupoAwamotos\LogMonitoring\Service\AgentTermination\Classifier;
use GrupoAwamotos\LogMonitoring\Service\AgentTermination\DiagnosticReportService;
use GrupoAwamotos\LogMonitoring\Service\AgentTermination\RecoveryManager;
use GrupoAwamotos\LogMonitoring\Service\NotificationService;
use Magento\Framework\Stdlib\DateTime\DateTime;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class DiagnosticReportServiceTest extends TestCase
{
    private AlertRepositoryInterface&MockObject $alertRepository;
    private AlertInterfaceFactory&MockObject $alertFactory;
    private NotificationService&MockObject $notificationService;
    private DateTime&MockObject $dateTime;
    private LoggerInterface&MockObject $logger;
    private DiagnosticReportService $service;

    protected function setUp(): void
    {
        $this->alertRepository = $this->createMock(AlertRepositoryInterface::class);
        $this->alertFactory = $this->createMock(AlertInterfaceFactory::class);
        $this->notificationService = $this->createMock(NotificationService::class);
        $this->dateTime = $this->createMock(DateTime::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->dateTime->method('gmtDate')->willReturn('2026-04-23T22:29:16+00:00');

        $this->service = new DiagnosticReportService(
            new Classifier(),
            new RecoveryManager(),
            $this->alertRepository,
            $this->alertFactory,
            $this->notificationService,
            $this->dateTime,
            $this->logger
        );
    }

    public function testGenerateIncludesAnalysisAndRecoverySections(): void
    {
        $this->alertRepository->method('getAlertsByType')->willReturn([]);

        $report = $this->service->generate([
            'message' => 'HTTP 503 Service Unavailable',
            'details' => [
                'error' => [
                    'message' => 'No capacity available for model gemini-3.1-pro-high on the server',
                    'details' => [
                        ['retryDelay' => '43s'],
                        ['reason' => 'MODEL_CAPACITY_EXHAUSTED']
                    ]
                ]
            ],
            'workflow' => 'agent_execution',
            'model' => 'gemini-3.1-pro-high'
        ]);

        $this->assertSame('provider_capacity_exhausted', $report['analysis']['classification']['code']);
        $this->assertSame('graceful_degradation', $report['recovery']['mode']);
        $this->assertTrue($report['post_mortem']['retry_or_restart_options']['retry_operation']);
        $this->assertContains(RecoveryManager::SUPPORT_URL, $report['support']['resources']);
    }

    public function testGeneratePersistsAndNotifiesWhenRequested(): void
    {
        $this->alertRepository->method('getAlertsByType')->willReturn([]);

        $alert = $this->createMock(AlertInterface::class);
        $alert->method('getEntityId')->willReturn(42);
        $alert->method('getStatus')->willReturn(AlertInterface::STATUS_OPEN);
        $alert->method('getContextData')->willReturn([]);
        $alert->method('getMessage')->willReturn(null);
        $alert->method('setAlertType')->willReturnSelf();
        $alert->method('setSeverity')->willReturnSelf();
        $alert->method('setTitle')->willReturnSelf();
        $alert->method('setMessage')->willReturnSelf();
        $alert->method('setContextData')->willReturnSelf();
        $alert->method('setSource')->willReturnSelf();
        $alert->method('setStatus')->willReturnSelf();
        $alert->method('setOccurrences')->willReturnSelf();
        $alert->method('setFirstOccurrence')->willReturnSelf();
        $alert->method('setLastOccurrence')->willReturnSelf();

        $this->alertFactory->method('create')->willReturn($alert);
        $this->alertRepository->method('save')->willReturn($alert);
        $this->notificationService->method('sendAlert')->willReturn(['email' => true]);

        $report = $this->service->generate([
            'message' => "Command 'code' not found, but can be installed with: sudo snap install code"
        ], true, true);

        $this->assertTrue($report['alert']['saved']);
        $this->assertSame(42, $report['alert']['alert_id']);
        $this->assertSame(['email' => true], $report['notifications']);
    }

    public function testGenerateMergesMatchingOpenAlertInsteadOfCreatingDuplicate(): void
    {
        $existingAlert = $this->createMock(AlertInterface::class);
        $existingAlert->method('getEntityId')->willReturn(77);
        $existingAlert->method('getStatus')->willReturn(AlertInterface::STATUS_OPEN);
        $existingAlert->method('getMessage')->willReturn('No capacity available for model gemini-3.1-pro-high on the server');
        $existingAlert->method('getSeverity')->willReturn(AlertInterface::SEVERITY_MEDIUM);
        $existingAlert->method('getOccurrences')->willReturn(2);
        $existingAlert->method('getContextData')->willReturn([
            'incident' => [
                'exact_error_message' => 'No capacity available for model gemini-3.1-pro-high on the server',
                'model' => 'gemini-3.1-pro-high',
                'workflow' => 'agent_execution',
            ],
            'analysis' => [
                'classification' => [
                    'code' => 'provider_capacity_exhausted',
                ]
            ]
        ]);
        $existingAlert->method('setSeverity')->willReturnSelf();
        $existingAlert->method('setTitle')->willReturnSelf();
        $existingAlert->method('setMessage')->willReturnSelf();
        $existingAlert->method('setContextData')->willReturnSelf();
        $existingAlert->method('setLastOccurrence')->willReturnSelf();
        $existingAlert->method('setOccurrences')->willReturnSelf();

        $this->alertRepository->expects($this->exactly(2))
            ->method('getAlertsByType')
            ->willReturn([$existingAlert]);
        $this->alertRepository->expects($this->once())
            ->method('save')
            ->with($existingAlert)
            ->willReturn($existingAlert);
        $this->alertFactory->expects($this->never())->method('create');

        $report = $this->service->generate([
            'message' => 'HTTP 503 Service Unavailable',
            'details' => [
                'error' => [
                    'message' => 'No capacity available for model gemini-3.1-pro-high on the server',
                    'details' => [
                        ['retryDelay' => '43s'],
                        ['reason' => 'MODEL_CAPACITY_EXHAUSTED']
                    ]
                ]
            ],
            'workflow' => 'agent_execution',
            'model' => 'gemini-3.1-pro-high'
        ], true, false);

        $this->assertSame(77, $report['alert']['alert_id']);
        $this->assertTrue($report['alert']['saved']);
    }
}
