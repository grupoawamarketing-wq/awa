<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Plugin\Checkout;

use GrupoAwamotos\B2B\Model\Checkout\ErpPurchaseGate;
use Magento\Checkout\Model\DefaultConfigProvider;

/**
 * Exposes ERP purchase gate state to window.checkoutConfig for frontend UX.
 */
class CheckoutErpGateConfigPlugin
{
    public function __construct(
        private readonly ErpPurchaseGate $erpPurchaseGate
    ) {
    }

    /**
     * @param DefaultConfigProvider $subject
     * @param array<string, mixed> $result
     * @return array<string, mixed>
     */
    public function afterGetConfig(DefaultConfigProvider $subject, array $result): array
    {
        if (!$this->erpPurchaseGate->isBlockedForCurrentCustomer()) {
            return $result;
        }

        $result['b2bCheckoutBlocked'] = true;
        $result['b2bCheckoutBlockMessage'] = $this->erpPurchaseGate->getBlockMessage();

        return $result;
    }
}
