<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Block\Checkout;

use GrupoAwamotos\B2B\Model\Checkout\ErpPurchaseGate;
use Magento\Framework\View\Element\Template;

class ErpGateNotice extends Template
{
    public function __construct(
        Template\Context $context,
        private readonly ErpPurchaseGate $erpPurchaseGate,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function isCheckoutBlocked(): bool
    {
        return $this->erpPurchaseGate->isBlockedForCurrentCustomer();
    }

    public function getBlockMessage(): string
    {
        return $this->erpPurchaseGate->getBlockMessage();
    }
}
