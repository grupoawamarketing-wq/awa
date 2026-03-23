<?php
declare(strict_types=1);

namespace GrupoAwamotos\LiveChat\Test\Unit\Model\Chat;

use GrupoAwamotos\LiveChat\Model\Chat\WidgetExperimentAssigner;
use GrupoAwamotos\LiveChat\Model\Config\WidgetExperimentConfig;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Session\SessionManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class WidgetExperimentAssignerTest extends TestCase
{
    private WidgetExperimentAssigner $subject;

    private WidgetExperimentConfig&MockObject $config;

    private CustomerSession&MockObject $customerSession;

    private SessionManagerInterface&MockObject $sessionManager;

    protected function setUp(): void
    {
        $this->config = $this->createMock(WidgetExperimentConfig::class);
        $this->customerSession = $this->createMock(CustomerSession::class);
        $this->sessionManager = $this->createMock(SessionManagerInterface::class);

        $this->subject = new WidgetExperimentAssigner(
            $this->config,
            $this->customerSession,
            $this->sessionManager
        );
    }

    public function testDisabledExperimentFallsBackToControl(): void
    {
        $this->config->method('isExperimentEnabled')->willReturn(false);

        $this->assertSame(WidgetExperimentAssigner::VARIANT_CONTROL, $this->subject->getVariant());
        $this->assertFalse($this->subject->shouldDeferInit());
    }

    public function testZeroRolloutReturnsControl(): void
    {
        $this->config->method('isExperimentEnabled')->willReturn(true);
        $this->config->method('getRolloutPercentage')->willReturn(0);

        $this->assertSame(WidgetExperimentAssigner::VARIANT_CONTROL, $this->subject->getVariant());
    }

    public function testFullRolloutReturnsTreatment(): void
    {
        $this->config->method('isExperimentEnabled')->willReturn(true);
        $this->config->method('getRolloutPercentage')->willReturn(100);

        $this->assertSame(WidgetExperimentAssigner::VARIANT_TREATMENT, $this->subject->getVariant());
        $this->assertTrue($this->subject->shouldDeferInit());
    }

    public function testLoggedInCustomerCanFallIntoTreatmentBucket(): void
    {
        $this->config->method('isExperimentEnabled')->willReturn(true);
        $this->config->method('getRolloutPercentage')->willReturn(20);
        $this->config->method('getVariantSeed')->willReturn('livechat_widget_v1');
        $this->customerSession->method('isLoggedIn')->willReturn(true);
        $this->customerSession->method('getCustomerId')->willReturn(99);

        $this->assertSame(WidgetExperimentAssigner::VARIANT_TREATMENT, $this->subject->getVariant());
    }

    public function testLoggedInCustomerCanFallIntoControlBucket(): void
    {
        $this->config->method('isExperimentEnabled')->willReturn(true);
        $this->config->method('getRolloutPercentage')->willReturn(20);
        $this->config->method('getVariantSeed')->willReturn('livechat_widget_v1');
        $this->customerSession->method('isLoggedIn')->willReturn(true);
        $this->customerSession->method('getCustomerId')->willReturn(42);

        $this->assertSame(WidgetExperimentAssigner::VARIANT_CONTROL, $this->subject->getVariant());
    }

    public function testSessionIdentityIsUsedForGuestUsers(): void
    {
        $this->config->method('isExperimentEnabled')->willReturn(true);
        $this->config->method('getRolloutPercentage')->willReturn(10);
        $this->config->method('getVariantSeed')->willReturn('livechat_widget_v1');
        $this->customerSession->method('isLoggedIn')->willReturn(false);
        $this->sessionManager->method('getSessionId')->willReturn('sess42');

        $this->assertSame(WidgetExperimentAssigner::VARIANT_TREATMENT, $this->subject->getVariant());
    }

    public function testGuestWithoutIdentityFallsBackToControl(): void
    {
        $this->config->method('isExperimentEnabled')->willReturn(true);
        $this->config->method('getRolloutPercentage')->willReturn(50);
        $this->customerSession->method('isLoggedIn')->willReturn(false);
        $this->sessionManager->method('getSessionId')->willReturn('');

        $this->assertSame(WidgetExperimentAssigner::VARIANT_CONTROL, $this->subject->getVariant());
        $this->assertFalse($this->subject->shouldDeferInit());
    }
}
