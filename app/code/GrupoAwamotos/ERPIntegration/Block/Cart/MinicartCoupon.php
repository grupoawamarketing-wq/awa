<?php

declare(strict_types=1);

namespace GrupoAwamotos\ERPIntegration\Block\Cart;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;

/**
 * Campo rápido de cupom no painel do minicart.
 */
class MinicartCoupon extends Template
{
    private CheckoutSession $checkoutSession;
    private FormKey $formKey;

    public function __construct(
        Context $context,
        CheckoutSession $checkoutSession,
        FormKey $formKey,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->checkoutSession = $checkoutSession;
        $this->formKey = $formKey;
    }

    public function getFormKeyValue(): string
    {
        return $this->formKey->getFormKey();
    }

    public function shouldDisplay(): bool
    {
        $quote = $this->checkoutSession->getQuote();

        return $quote && (int) $quote->getItemsCount() > 0;
    }

    public function getCouponCode(): string
    {
        $quote = $this->checkoutSession->getQuote();

        return $quote ? (string) $quote->getCouponCode() : '';
    }

    public function hasCouponApplied(): bool
    {
        return $this->getCouponCode() !== '';
    }

    public function getApplyUrl(): string
    {
        return $this->getUrl('checkout/cart/couponPost');
    }
}
