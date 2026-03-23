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
            ['grupoawamotos_theme/header_experiment/variant_seed', ScopeInterface::SCOPE_STORE, null, 'home5_header_v2'],
        ]);

        $this->customerSession->method('getCustomerId')->willReturn(88);
        $this->decider->method('normalizeRolloutPercentage')->willReturn(15);
        $this->decider->method('normalizeVariantSeed')->willReturn('home5_header_v2');
        $this->decider->method('getDefaultVariantCode')->willReturn('v2');
        $this->decider->expects($this->once())
            ->method('decide')
            ->with('customer:88', 'home5_header_v2', true, 15, 'v2')
            ->willReturn(['variant' => 'v2', 'active' => true, 'seed' => 'home5_header_v2']);

        $this->assertSame(['variant' => 'v2', 'active' => true, 'seed' => 'home5_header_v2'], $this->subject->getPayload());
    }

    public function testGetPayloadFallsBackToSessionSeedForGuests(): void
    {
        $this->scopeConfig->method('isSetFlag')->willReturn(false);
        $this->scopeConfig->method('getValue')->willReturnMap([
            ['grupoawamotos_theme/header_experiment/rollout_percentage', ScopeInterface::SCOPE_STORE, null, '0'],
            ['grupoawamotos_theme/header_experiment/variant_seed', ScopeInterface::SCOPE_STORE, null, 'home5_header_guest'],
        ]);

        $this->customerSession->method('getCustomerId')->willReturn(null);
        $this->sessionManager->method('getSessionId')->willReturn('sess-123');
        $this->decider->method('normalizeRolloutPercentage')->willReturn(0);
        $this->decider->method('normalizeVariantSeed')->willReturn('home5_header_guest');
        $this->decider->method('getDefaultVariantCode')->willReturn('v2');
        $this->decider->expects($this->once())
            ->method('decide')
            ->with('session:sess-123', 'home5_header_guest', false, 0, 'v2')
            ->willReturn(['variant' => 'control', 'active' => false, 'seed' => 'home5_header_guest']);

        $this->assertSame(['variant' => 'control', 'active' => false, 'seed' => 'home5_header_guest'], $this->subject->getPayload());
    }

    public function testGetPayloadMemoizesConfigurationAndDecisionForSameStore(): void
    {
        $this->scopeConfig->expects($this->once())
            ->method('isSetFlag')
            ->with('grupoawamotos_theme/header_experiment/enabled', ScopeInterface::SCOPE_STORE, null)
            ->willReturn(true);

        $this->scopeConfig->expects($this->exactly(2))
            ->method('getValue')
            ->willReturnMap([
                ['grupoawamotos_theme/header_experiment/rollout_percentage', ScopeInterface::SCOPE_STORE, null, '35'],
                ['grupoawamotos_theme/header_experiment/variant_seed', ScopeInterface::SCOPE_STORE, null, 'home5_header_cached'],
            ]);

        $this->customerSession->method('getCustomerId')->willReturn(21);

        $this->decider->expects($this->once())
            ->method('normalizeRolloutPercentage')
            ->with(35)
            ->willReturn(35);

        $this->decider->expects($this->once())
            ->method('normalizeVariantSeed')
            ->with('home5_header_cached')
            ->willReturn('home5_header_cached');

        $this->decider->expects($this->once())
            ->method('getDefaultVariantCode')
            ->willReturn('v2');

        $this->decider->expects($this->once())
            ->method('decide')
            ->with('customer:21', 'home5_header_cached', true, 35, 'v2')
            ->willReturn([
                'variant' => 'v2',
                'active' => true,
                'seed' => 'home5_header_cached',
            ]);

        $first = $this->subject->getPayload();
        $second = $this->subject->getPayload();

        $this->assertSame($first, $second);
    }
}
