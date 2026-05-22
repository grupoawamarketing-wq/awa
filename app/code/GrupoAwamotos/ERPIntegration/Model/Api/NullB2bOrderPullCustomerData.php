<?php

declare(strict_types=1);

namespace GrupoAwamotos\ERPIntegration\Model\Api;

use GrupoAwamotos\ERPIntegration\Api\B2bOrderPullCustomerDataInterface;
use Magento\Sales\Api\Data\OrderInterface;

/**
 * No-op fallback when B2B module is disabled.
 */
class NullB2bOrderPullCustomerData implements B2bOrderPullCustomerDataInterface
{
    public function buildForOrder(OrderInterface $order): array
    {
        return [];
    }

    public function isReadyForOrderPull(int $customerId): bool
    {
        return false;
    }

    public function validateOrderForPull(OrderInterface $order): ?string
    {
        return null;
    }

    public function isApprovedB2bCustomer(int $customerId): bool
    {
        return false;
    }

    public function isCustomerErpValidatedForPurchase(int $customerId): bool
    {
        return true;
    }
}
