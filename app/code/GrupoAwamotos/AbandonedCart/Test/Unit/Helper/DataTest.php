<?php

declare(strict_types=1);

namespace GrupoAwamotos\AbandonedCart\Test\Unit\Helper;

use GrupoAwamotos\AbandonedCart\Helper\Data;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Helper\Context;
use Magento\Store\Model\ScopeInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @covers \GrupoAwamotos\AbandonedCart\Helper\Data
 */
class DataTest extends TestCase
{
    private Data $helper;
    private ScopeConfigInterface&MockObject $scopeConfig;

    protected function setUp(): void
    {
        $this->scopeConfig = $this->createMock(ScopeConfigInterface::class);

        $context = $this->createMock(Context::class);
        $context->method('getScopeConfig')->willReturn($this->scopeConfig);

        $this->helper = new Data($context);
    }

    // ====================================================================
    // isEnabled
    // ====================================================================

    public function testIsEnabledReturnsTrueWhenEnabled(): void
    {
        $this->scopeConfig->method('isSetFlag')
            ->with('abandoned_cart/general/enabled', ScopeInterface::SCOPE_STORE, 1)
            ->willReturn(true);

        $this->assertTrue($this->helper->isEnabled(1));
    }

    public function testIsEnabledReturnsFalseWhenDisabled(): void
    {
        $this->scopeConfig->method('isSetFlag')
            ->with('abandoned_cart/general/enabled', ScopeInterface::SCOPE_STORE, null)
            ->willReturn(false);

        $this->assertFalse($this->helper->isEnabled());
    }

    // ====================================================================
    // getMinCartValue
    // ====================================================================

    public function testGetMinCartValueReturnsFloat(): void
    {
        $this->scopeConfig->method('getValue')
            ->with('abandoned_cart/general/min_cart_value', ScopeInterface::SCOPE_STORE, 1)
            ->willReturn('50.00');

        $this->assertSame(50.0, $this->helper->getMinCartValue(1));
    }

    public function testGetMinCartValueReturnsZeroWhenNull(): void
    {
        $this->scopeConfig->method('getValue')
            ->with('abandoned_cart/general/min_cart_value', ScopeInterface::SCOPE_STORE, null)
            ->willReturn(null);

        $this->assertSame(0.0, $this->helper->getMinCartValue());
    }

    // ====================================================================
    // excludeGuest
    // ====================================================================

    public function testExcludeGuestReturnsTrue(): void
    {
        $this->scopeConfig->method('isSetFlag')
            ->with('abandoned_cart/general/exclude_guest', ScopeInterface::SCOPE_STORE, null)
            ->willReturn(true);

        $this->assertTrue($this->helper->excludeGuest());
    }

    public function testExcludeGuestReturnsFalse(): void
    {
        $this->scopeConfig->method('isSetFlag')
            ->with('abandoned_cart/general/exclude_guest', ScopeInterface::SCOPE_STORE, 2)
            ->willReturn(false);

        $this->assertFalse($this->helper->excludeGuest(2));
    }

    // ====================================================================
    // isEmailEnabled
    // ====================================================================

    public function testIsEmailEnabledForEmail1(): void
    {
        $this->scopeConfig->method('isSetFlag')
            ->with('abandoned_cart/email_1/enabled', ScopeInterface::SCOPE_STORE, null)
            ->willReturn(true);

        $this->assertTrue($this->helper->isEmailEnabled(1));
    }

    public function testIsEmailEnabledForEmail2Disabled(): void
    {
        $this->scopeConfig->method('isSetFlag')
            ->with('abandoned_cart/email_2/enabled', ScopeInterface::SCOPE_STORE, 1)
            ->willReturn(false);

        $this->assertFalse($this->helper->isEmailEnabled(2, 1));
    }

    // ====================================================================
    // getEmailDelay
    // ====================================================================

    public function testGetEmailDelayReturnsInt(): void
    {
        $this->scopeConfig->method('getValue')
            ->with('abandoned_cart/email_1/delay_hours', ScopeInterface::SCOPE_STORE, null)
            ->willReturn('24');

        $this->assertSame(24, $this->helper->getEmailDelay(1));
    }

    public function testGetEmailDelayReturnsZeroWhenNull(): void
    {
        $this->scopeConfig->method('getValue')
            ->with('abandoned_cart/email_3/delay_hours', ScopeInterface::SCOPE_STORE, null)
            ->willReturn(null);

        $this->assertSame(0, $this->helper->getEmailDelay(3));
    }

    // ====================================================================
    // getEmailSubject
    // ====================================================================

    public function testGetEmailSubjectReturnsString(): void
    {
        $this->scopeConfig->method('getValue')
            ->with('abandoned_cart/email_1/subject', ScopeInterface::SCOPE_STORE, null)
            ->willReturn('Esqueceu algo?');

        $this->assertSame('Esqueceu algo?', $this->helper->getEmailSubject(1));
    }

    public function testGetEmailSubjectReturnsEmptyStringWhenNull(): void
    {
        $this->scopeConfig->method('getValue')
            ->with('abandoned_cart/email_2/subject', ScopeInterface::SCOPE_STORE, null)
            ->willReturn(null);

        $this->assertSame('', $this->helper->getEmailSubject(2));
    }

    // ====================================================================
    // getEmailTemplate
    // ====================================================================

    public function testGetEmailTemplateReturnsConfiguredTemplate(): void
    {
        $this->scopeConfig->method('getValue')
            ->with('abandoned_cart/email_1/template', ScopeInterface::SCOPE_STORE, 1)
            ->willReturn('custom_template_id');

        $this->assertSame('custom_template_id', $this->helper->getEmailTemplate(1, 1));
    }

    // ====================================================================
    // isCouponEnabled
    // ====================================================================

    public function testIsCouponEnabledReturnsTrueForEmail2(): void
    {
        $this->scopeConfig->method('isSetFlag')
            ->with('abandoned_cart/email_2/coupon_enabled', ScopeInterface::SCOPE_STORE, null)
            ->willReturn(true);

        $this->assertTrue($this->helper->isCouponEnabled(2));
    }

    public function testIsCouponEnabledReturnsFalse(): void
    {
        $this->scopeConfig->method('isSetFlag')
            ->with('abandoned_cart/email_1/coupon_enabled', ScopeInterface::SCOPE_STORE, null)
            ->willReturn(false);

        $this->assertFalse($this->helper->isCouponEnabled(1));
    }

    // ====================================================================
    // getCouponDiscount
    // ====================================================================

    public function testGetCouponDiscountReturnsFloat(): void
    {
        $this->scopeConfig->method('getValue')
            ->with('abandoned_cart/email_2/coupon_discount', ScopeInterface::SCOPE_STORE, null)
            ->willReturn('10');

        $this->assertSame(10.0, $this->helper->getCouponDiscount(2));
    }

    // ====================================================================
    // getCouponType
    // ====================================================================

    public function testGetCouponTypeReturnsPercent(): void
    {
        $this->scopeConfig->method('getValue')
            ->with('abandoned_cart/email_2/coupon_type', ScopeInterface::SCOPE_STORE, null)
            ->willReturn('percent');

        $this->assertSame('percent', $this->helper->getCouponType(2));
    }

    public function testGetCouponTypeReturnsFixed(): void
    {
        $this->scopeConfig->method('getValue')
            ->with('abandoned_cart/email_3/coupon_type', ScopeInterface::SCOPE_STORE, 1)
            ->willReturn('fixed');

        $this->assertSame('fixed', $this->helper->getCouponType(3, 1));
    }

    public function testGetCouponTypeReturnsEmptyWhenNull(): void
    {
        $this->scopeConfig->method('getValue')
            ->with('abandoned_cart/email_1/coupon_type', ScopeInterface::SCOPE_STORE, null)
            ->willReturn(null);

        $this->assertSame('', $this->helper->getCouponType(1));
    }
}
