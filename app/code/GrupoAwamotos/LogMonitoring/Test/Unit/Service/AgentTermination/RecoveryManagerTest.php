<?php
declare(strict_types=1);

namespace GrupoAwamotos\LogMonitoring\Test\Unit\Service\AgentTermination;

use GrupoAwamotos\LogMonitoring\Service\AgentTermination\RecoveryManager;
use PHPUnit\Framework\TestCase;

class RecoveryManagerTest extends TestCase
{
    private RecoveryManager $recoveryManager;

    protected function setUp(): void
    {
        $this->recoveryManager = new RecoveryManager();
    }

    public function testBuildsProviderCapacityRecoveryPlan(): void
    {
        $plan = $this->recoveryManager->buildRecoveryPlan([
            'classification' => [
                'code' => 'provider_capacity_exhausted',
                'retry_delay_seconds' => 43,
            ]
        ]);

        $this->assertSame('graceful_degradation', $plan['mode']);
        $this->assertTrue($plan['automatic_retry_policy']['enabled']);
        $this->assertSame(43, $plan['automatic_retry_policy']['base_delay_seconds']);
        $this->assertTrue($plan['graceful_degradation']['allow_retry_action']);
        $this->assertTrue($plan['graceful_degradation']['allow_new_conversation_action']);
    }

    public function testBuildsMcpRecoveryPlanWithoutRetryLoop(): void
    {
        $plan = $this->recoveryManager->buildRecoveryPlan([
            'classification' => [
                'code' => 'mcp_configuration_error',
                'retry_delay_seconds' => null,
            ]
        ]);

        $this->assertFalse($plan['automatic_retry_policy']['enabled']);
        $this->assertContains(
            'Repair the MCP server definition so it includes a valid command or server URL.',
            $plan['recommended_fixes']
        );
    }
}
