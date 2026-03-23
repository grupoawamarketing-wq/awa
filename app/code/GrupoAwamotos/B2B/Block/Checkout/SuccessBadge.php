<?php
declare(strict_types=1);

namespace GrupoAwamotos\B2B\Block\Checkout;

use GrupoAwamotos\B2B\Model\Payment\CreditPayment;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Sales\Model\Order;

class SuccessBadge extends Template
{
    public function __construct(
        Context $context,
        private readonly CheckoutSession $checkoutSession,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function shouldDisplay(): bool
    {
        $order = $this->getOrder();
        if ($order === null) {
            return false;
        }

        $payment = $order->getPayment();
        return $payment !== null
            && $payment->getMethod() === CreditPayment::METHOD_CODE;
    }

    public function getOrder(): ?Order
    {
        $order = $this->checkoutSession->getLastRealOrder();
        if ($order && $order->getId()) {
            return $order;
        }
        return null;
    }

    public function getOrderIncrementId(): string
    {
        return (string) ($this->getOrder()?->getIncrementId() ?? '');
    }

    public function getPaymentTermTitle(): string
    {
        $order = $this->getOrder();
        if ($order === null) {
            return '';
        }

        $info = $order->getPayment()?->getAdditionalInformation();
        return (string) ($info['b2b_payment_term_label'] ?? __('Crédito B2B'));
    }
}
