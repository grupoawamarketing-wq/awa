<?php
declare(strict_types=1);

namespace GrupoAwamotos\LogMonitoring\Test\Unit\Service\AgentTermination;

use GrupoAwamotos\LogMonitoring\Api\Data\AlertInterface;
use GrupoAwamotos\LogMonitoring\Service\AgentTermination\Classifier;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ClassifierTest extends TestCase
{
    private Classifier $classifier;

    protected function setUp(): void
    {
        $this->classifier = new Classifier();
    }

    public function testClassifiesProviderCapacityExhaustion(): void
    {
        $analysis = $this->classifier->classify([
            'message' => 'HTTP 503 Service Unavailable',
            'workflow' => 'agent_execution',
            'model' => 'gemini-3.1-pro-high',
            'details' => [
                'error' => [
                    'message' => 'No capacity available for model gemini-3.1-pro-high on the server',
                    'details' => [
                        ['retryDelay' => '43s'],
                        ['reason' => 'MODEL_CAPACITY_EXHAUSTED']
                    ]
                ]
            ]
        ]);

        $this->assertSame('provider_capacity_exhausted', $analysis['classification']['code']);
        $this->assertSame('No capacity available for model gemini-3.1-pro-high on the server', $analysis['evidence']['exact_error_message']);
        $this->assertSame(43, $analysis['classification']['retry_delay_seconds']);
        $this->assertContains('agent_execution', $analysis['affected_workflows']);
    }

    public function testEscalatesSeverityWhenIncidentRepeats(): void
    {
        $historicalAlert = $this->createConfiguredMock(AlertInterface::class, [
            'getContextData' => [
                'analysis' => [
                    'classification' => [
                        'code' => 'provider_capacity_exhausted'
                    ]
                ]
            ],
            'getOccurrences' => 3,
            'getCreatedAt' => date('Y-m-d H:i:s'),
        ]);

        $analysis = $this->classifier->classify([
            'message' => 'MODEL_CAPACITY_EXHAUSTED'
        ], [$historicalAlert]);

        $this->assertSame(AlertInterface::SEVERITY_CRITICAL, $analysis['classification']['severity']);
        $this->assertSame('spiking', $analysis['frequency_analysis']['trend']);
    }

    public function testClassifiesEnvironmentCommandUnavailability(): void
    {
        $analysis = $this->classifier->classify([
            'message' => "Command 'code' not found, but can be installed with: sudo snap install code"
        ]);

        $this->assertSame('environment_command_unavailable', $analysis['classification']['code']);
        $this->assertStringContainsString('Command \'code\' not found', $analysis['evidence']['exact_error_message']);
    }
}
