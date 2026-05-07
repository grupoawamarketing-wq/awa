<?php

declare(strict_types=1);

namespace GrupoAwamotos\ERPIntegration\Model\Api;

use GrupoAwamotos\ERPIntegration\Api\SuggestedCartInterface;
use GrupoAwamotos\ERPIntegration\Model\Cart\SuggestedCart;
use GrupoAwamotos\ERPIntegration\Helper\Data as Helper;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\AuthorizationException;

/**
 * Suggested Cart API Implementation
 */
class SuggestedCartManagement implements SuggestedCartInterface
{
    private SuggestedCart $suggestedCart;
    private CustomerSession $customerSession;
    private Helper $helper;

    public function __construct(
        SuggestedCart $suggestedCart,
        CustomerSession $customerSession,
        Helper $helper
    ) {
        $this->suggestedCart = $suggestedCart;
        $this->customerSession = $customerSession;
        $this->helper = $helper;
    }

    /**
     * @inheritdoc
     */
    public function getSuggestedCart(): array
    {
        $this->validateEnabled();
        $this->validateLoggedIn();

        $customerCode = $this->getCustomerCode();
        if (!$customerCode) {
            return ['error' => true, 'message' => 'Customer not linked to ERP'];
        }

        return $this->suggestedCart->buildSuggestedCart($customerCode);
    }

    /**
     * @inheritdoc
     */
    public function getSuggestedCartForCustomer(int $customerId): array
    {
        $this->validateEnabled();

        // Get customer code from ERP (would need customer repository)
        // For now, use customerId as customerCode if they match
        return $this->suggestedCart->buildSuggestedCart($customerId);
    }

    /**
     * @inheritdoc
     */
    public function getReorderSuggestions(int $limit = 10): array
    {
        $this->validateEnabled();
        $this->validateLoggedIn();

        $customerCode = $this->getCustomerCode();
        if (!$customerCode) {
            return [];
        }

        $cart = $this->suggestedCart->buildSuggestedCart($customerCode);
        $reorderItems = $cart['reorder_items'] ?? [];

        return array_slice($reorderItems, 0, $limit);
    }

    /**
     * @inheritdoc
     */
    public function getCrossSellSuggestions(int $limit = 8): array
    {
        $this->validateEnabled();
        $this->validateLoggedIn();

        $customerCode = $this->getCustomerCode();
        if (!$customerCode) {
            return [];
        }

        $cart = $this->suggestedCart->buildSuggestedCart($customerCode);
        $crossSellItems = $cart['cross_sell_items'] ?? [];

        return array_slice($crossSellItems, 0, $limit);
    }

    /**
     * Validate feature is enabled
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    private function validateEnabled(): void
    {
        if (!$this->helper->isEnabled() || !$this->helper->isSuggestedCartEnabled()) {
            throw new LocalizedException(__('Suggested Cart feature is disabled'));
        }
    }

    /**
     * Validate customer is logged in
     *
     * @throws \Magento\Framework\Exception\AuthorizationException
     */
    private function validateLoggedIn(): void
    {
        if (!$this->customerSession->isLoggedIn()) {
            throw new AuthorizationException(__('Customer must be logged in'));
        }
    }

    /**
     * Get customer ERP code
     */
    private function getCustomerCode(): ?int
    {
        $customer = $this->customerSession->getCustomer();
        if (!$customer || !$customer->getId()) {
            return null;
        }

        // Check for ERP customer code attribute
        $erpCode = $customer->getData('erp_customer_code');
        if ($erpCode) {
            return (int) $erpCode;
        }

        // Fallback to customer ID
        return (int) $customer->getId();
    }
}
