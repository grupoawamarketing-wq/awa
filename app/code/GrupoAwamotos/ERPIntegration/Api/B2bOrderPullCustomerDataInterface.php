<?php

declare(strict_types=1);

namespace GrupoAwamotos\ERPIntegration\Api;

use Magento\Sales\Api\Data\OrderInterface;

/**
 * B2B fiscal/customer payload for Sectra order pull (implemented by GrupoAwamotos_B2B).
 */
interface B2bOrderPullCustomerDataInterface
{
    /**
     * Build customer block for order pull API / oc_order bridge.
     *
     * @return array<string, mixed>
     */
    public function buildForOrder(OrderInterface $order): array;

    /**
     * Whether an approved B2B customer has minimum data for Sectra pull (no erp_code required).
     */
    public function isReadyForOrderPull(int $customerId): bool;

    /**
     * Validate order before placement; null = OK, string = blocking error message.
     */
    public function validateOrderForPull(OrderInterface $order): ?string;

    /**
     * Whether customer is B2B approved (commercial gate passed).
     */
    public function isApprovedB2bCustomer(int $customerId): bool;

    /**
     * Whether approved B2B customer is validated in ERP (oc_customer_b2b_confirmed gate).
     */
    public function isCustomerErpValidatedForPurchase(int $customerId): bool;
}
