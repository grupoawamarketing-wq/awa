<?php
declare(strict_types=1);

namespace GrupoAwamotos\Theme\Test\Unit\Helper;

use GrupoAwamotos\Theme\Helper\HeaderExperiment;
use GrupoAwamotos\Theme\Model\HeaderExperimentDecider;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Session\SessionManagerInterface;
use Magento\Store\Model\ScopeInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @covers \GrupoAwamotos\Theme\Helper\HeaderExperiment
 */
class HeaderExperimentTest extends TestCase
{
    private HeaderExperiment $subject;
    private ScopeConfigInterface&MockObject $scopeConfig;
    private CustomerSession&MockObject $customerSession;
    private SessionManagerInterface&MockObject $sessionManager;
    private HeaderExperimentDecider&MockObject $decider;

    protected function setUp(): void
    {
        $this->scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $this->customerSession = $this->createMock(CustomerSession::class);
        $this->sessionManager = $this->createMock(SessionManagerInterface::class);
        $this->decider = $this->createMock(HeaderExperimentDecider::class);

        $context = $this->createMock(Context::class);
        $context->method('getScopeConfig')->willReturn($this->scopeConfig);

        $this->subject = new HeaderExperiment(
            $context,
            $this->customerSession,
            $this->sessionManager,
            $this->decider
        );
    }

    public function testGetRolloutPercentageUsesNormalizer(): void
    {
        $this->scopeConfig->expects($this->once())
            ->method('getValue')
            ->with(
                'grupoawamotos_theme/header_experiment/rollout_percentage',
                ScopeInterface::SCOPE_STORE,
                null
            )
            ->willReturn('125');

        $this->decider->expects($this->once())
            ->method('normalizeRolloutPercentage')
            ->with(125)
            ->willReturn(100);

        $this->assertSame(100, $this->subject->getRolloutPercentage());
    }

    public function testGetPayloadUsesCustomerSeedWhenLoggedIn(): void
    {
        $this->scopeConfig->method('isSetFlag')->willReturn(true);
        $this->scopeConfig->method('getValue')->willReturnMap([
            ['grupoawamotos_theme/header_experiment/rollout_percentage', ScopeInterface::SCOPE_STORE, null, '15'],
            ['grupoawamotos_theme/header_experiment/variant_code', ScopeInterface::SCOPE_STORE, null, 'v2'],
        ]);

        $this->customerSession->method('getCustomerId')->willReturn(88);
        $this->decider->method('normalizeRolloutPercentage')->willReturn(15);
        $this->decider->method('normalizeVariantCode')->willReturn('v2');
        $this->decider->expects($this->once())
            ->method('decide')
            ->with('customer:88', true, 15, 'v2')
            ->willReturn(['variant' => 'v2', 'active' => true]);

        $this->assertSame(['variant' => 'v2', 'active' => true], $this->subject->getPayload());
    }

    public function testGetPayloadFallsBackToSessionSeedForGuests(): void
    {
        $this->scopeConfig->method('isSetFlag')->willReturn(false);
        $this->scopeConfig->method('getValue')->willReturnMap([
            ['grupoawamotos_theme/header_experiment/rollout_percentage', ScopeInterface::SCOPE_STORE, null, '0'],
            ['grupoawamotos_theme/header_experiment/variant_code', ScopeInterface::SCOPE_STORE, null, 'compact-topbar'],
        ]);

        $this->customerSession->method('getCustomerId')->willReturn(null);
        $this->sessionManager->method('getSessionId')->willReturn('sess-123');
        $this->decider->method('normalizeRolloutPercentage')->willReturn(0);
        $this->decider->method('normalizeVariantCode')->willReturn('compact-topbar');
        $this->decider->expects($this->once())
            ->method('decide')
            ->with('session:sess-123', false, 0, 'compact-topbar')
            ->willReturn(['variant' => 'control', 'active' => false]);

        $this->assertSame(['variant' => 'control', 'active' => false], $this->subject->getPayload());
    }
}