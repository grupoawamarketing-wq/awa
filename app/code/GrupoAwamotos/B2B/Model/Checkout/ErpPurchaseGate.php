<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Model\Checkout;

use GrupoAwamotos\B2B\Helper\Config;
use GrupoAwamotos\B2B\Model\Sectra\CheckoutBlockMessage;
use GrupoAwamotos\ERPIntegration\Api\B2bOrderPullCustomerDataInterface;
use Magento\Customer\Model\Session as CustomerSession;

/**
 * Shared ERP purchase gate logic for checkout config, blocks and cart notice.
 */
class ErpPurchaseGate
{
    public function __construct(
        private readonly Config $config,
        private readonly CustomerSession $customerSession,
        private readonly B2bOrderPullCustomerDataInterface $orderPullCustomerData
    ) {
    }

    public function isBlockedForCurrentCustomer(): bool
    {
        if (!$this->config->isEnabled() || !$this->customerSession->isLoggedIn()) {
            return false;
        }

        $customerId = (int) $this->customerSession->getCustomerId();

        return $customerId > 0
            && $this->orderPullCustomerData->isApprovedB2bCustomer($customerId)
            && !$this->orderPullCustomerData->isCustomerErpValidatedForPurchase($customerId);
    }

    public function getBlockMessage(): string
    {
        return CheckoutBlockMessage::MESSAGE;
    }
}
