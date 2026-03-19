<?php
/**
 * B2B Helper - Central configuration access
 */
declare(strict_types=1);

namespace GrupoAwamotos\B2B\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Store\Model\ScopeInterface;

class Config extends AbstractHelper
{
    const XML_PATH_ENABLED = 'grupoawamotos_b2b/general/enabled';
    const XML_PATH_B2B_MODE = 'grupoawamotos_b2b/general/b2b_mode';
    
    // Price Visibility
    const XML_PATH_HIDE_PRICE_GUESTS = 'grupoawamotos_b2b/price_visibility/hide_price_guests';
    const XML_PATH_HIDE_ADD_TO_CART_GUESTS = 'grupoawamotos_b2b/price_visibility/hide_add_to_cart_guests';
    const XML_PATH_LOGIN_MESSAGE = 'grupoawamotos_b2b/price_visibility/login_message';
    const XML_PATH_SHOW_PRICE_PENDING = 'grupoawamotos_b2b/price_visibility/show_price_pending';
    const XML_PATH_HIDE_PRICE_NO_ERP = 'grupoawamotos_b2b/price_visibility/hide_price_no_erp';
    const XML_PATH_PENDING_ERP_MESSAGE = 'grupoawamotos_b2b/price_visibility/pending_erp_message';

    // Customer Approval
    const XML_PATH_REQUIRE_APPROVAL = 'grupoawamotos_b2b/customer_approval/require_approval';
    const XML_PATH_AUTO_APPROVE_GROUPS = 'grupoawamotos_b2b/customer_approval/auto_approve_groups';
    const XML_PATH_PENDING_MESSAGE = 'grupoawamotos_b2b/customer_approval/pending_message';
    const XML_PATH_SEND_APPROVAL_EMAIL = 'grupoawamotos_b2b/customer_approval/send_approval_email';
    const XML_PATH_NOTIFY_ADMIN = 'grupoawamotos_b2b/customer_approval/notify_admin_new_customer';
    const XML_PATH_ADMIN_EMAIL = 'grupoawamotos_b2b/customer_approval/admin_email';
    
    // Minimum Qty
    const XML_PATH_MIN_QTY_ENABLED = 'grupoawamotos_b2b/minimum_qty/enabled';
    const XML_PATH_GLOBAL_MIN_QTY = 'grupoawamotos_b2b/minimum_qty/global_min_qty';
    const XML_PATH_MIN_ORDER_AMOUNT = 'grupoawamotos_b2b/minimum_qty/min_order_amount';
    const XML_PATH_MIN_ORDER_MESSAGE = 'grupoawamotos_b2b/minimum_qty/min_order_message';
    
    // Quote Request
    const XML_PATH_QUOTE_ENABLED = 'grupoawamotos_b2b/quote_request/enabled';
    const XML_PATH_QUOTE_BUTTON = 'grupoawamotos_b2b/quote_request/show_button';
    const XML_PATH_QUOTE_ALLOW_GUESTS = 'grupoawamotos_b2b/quote_request/allow_guests';
    const XML_PATH_QUOTE_EXPIRY_DAYS = 'grupoawamotos_b2b/quote_request/expiry_days';
    const XML_PATH_QUOTE_NOTIFY_CUSTOMER = 'grupoawamotos_b2b/quote_request/notify_customer';
    
    // Customer Groups
    const XML_PATH_WHOLESALE_GROUP = 'grupoawamotos_b2b/customer_groups/wholesale_group';
    const XML_PATH_WHOLESALE_DISCOUNT = 'grupoawamotos_b2b/customer_groups/wholesale_discount';
    const XML_PATH_VIP_GROUP = 'grupoawamotos_b2b/customer_groups/vip_group';
    const XML_PATH_VIP_DISCOUNT = 'grupoawamotos_b2b/customer_groups/vip_discount';
    const XML_PATH_DEFAULT_B2B_GROUP = 'grupoawamotos_b2b/customer_groups/default_b2b_group';
    
    // CNAE Profiling
    const XML_PATH_CNAE_ENABLED = 'grupoawamotos_b2b/cnae_profiling/enabled';
    const XML_PATH_CNAE_AUTO_APPROVE_DIRECT = 'grupoawamotos_b2b/cnae_profiling/auto_approve_direct';

    /**
     * Check if B2B module is enabled
     */
    public function isEnabled($storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }
    
    /**
     * Get B2B mode (strict or mixed)
     */
    public function getB2BMode($storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::XML_PATH_B2B_MODE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }
    
    /**
     * Check if strict B2B mode
     */
    public function isStrictB2B($storeId = null): bool
    {
        return $this->getB2BMode($storeId) === 'strict';
    }
    
    /**
     * Check if should hide prices for guests
     */
    public function hidePriceForGuests($storeId = null): bool
    {
        return $this->isEnabled($storeId) && $this->scopeConfig->isSetFlag(
            self::XML_PATH_HIDE_PRICE_GUESTS,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }
    
    /**
     * Check if should hide add to cart for guests
     */
    public function hideAddToCartForGuests($storeId = null): bool
    {
        return $this->isEnabled($storeId) && $this->scopeConfig->isSetFlag(
            self::XML_PATH_HIDE_ADD_TO_CART_GUESTS,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }
    
    /**
     * Get login message for guests
     */
    public function getLoginMessage($storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::XML_PATH_LOGIN_MESSAGE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }
    
    /**
     * Check if pending customers can see prices
     */
    public function showPriceForPending($storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_SHOW_PRICE_PENDING,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }
    
    /**
     * Check if should hide prices for approved customers without ERP code
     */
    public function hidePriceForNoErp($storeId = null): bool
    {
        return $this->isEnabled($storeId) && $this->scopeConfig->isSetFlag(
            self::XML_PATH_HIDE_PRICE_NO_ERP,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Get message for customers awaiting ERP price list assignment
     */
    public function getPendingErpMessage($storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::XML_PATH_PENDING_ERP_MESSAGE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Check if customer approval is required
     */
    public function requireApproval($storeId = null): bool
    {
        return $this->isEnabled($storeId) && $this->scopeConfig->isSetFlag(
            self::XML_PATH_REQUIRE_APPROVAL,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }
    
    /**
     * Get auto approve groups
     */
    public function getAutoApproveGroups($storeId = null): array
    {
        $value = $this->scopeConfig->getValue(
            self::XML_PATH_AUTO_APPROVE_GROUPS,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
        
        if (empty($value)) {
            return [];
        }
        
        return array_map('intval', explode(',', $value));
    }
    
    /**
     * Get pending message
     */
    public function getPendingMessage($storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::XML_PATH_PENDING_MESSAGE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }
    
    /**
     * Check if should send approval email
     */
    public function sendApprovalEmail($storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_SEND_APPROVAL_EMAIL,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }
    
    /**
     * Check if should notify admin
     */
    public function notifyAdmin($storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_NOTIFY_ADMIN,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }
    
    /**
     * Get admin email for notifications
     */
    public function getAdminEmail($storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::XML_PATH_ADMIN_EMAIL,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }
    
    /**
     * Check if minimum qty is enabled
     */
    public function isMinQtyEnabled($storeId = null): bool
    {
        return $this->isEnabled($storeId) && $this->scopeConfig->isSetFlag(
            self::XML_PATH_MIN_QTY_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }
    
    /**
     * Get global minimum qty
     */
    public function getGlobalMinQty($storeId = null): int
    {
        return (int) $this->scopeConfig->getValue(
            self::XML_PATH_GLOBAL_MIN_QTY,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }
    
    /**
     * Get minimum order amount
     */
    public function getMinOrderAmount($storeId = null): float
    {
        return (float) $this->scopeConfig->getValue(
            self::XML_PATH_MIN_ORDER_AMOUNT,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }
    
    /**
     * Get minimum order message
     */
    public function getMinOrderMessage($storeId = null): string
    {
        $message = (string) $this->scopeConfig->getValue(
            self::XML_PATH_MIN_ORDER_MESSAGE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
        
        return str_replace(
            '{{min_amount}}',
            number_format($this->getMinOrderAmount($storeId), 2, ',', '.'),
            $message
        );
    }
    
    /**
     * Check if quote request is enabled
     */
    public function isQuoteEnabled($storeId = null): bool
    {
        return $this->isEnabled($storeId) && $this->scopeConfig->isSetFlag(
            self::XML_PATH_QUOTE_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }
    
    /**
     * Get quote button position
     */
    public function getQuoteButtonPosition($storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::XML_PATH_QUOTE_BUTTON,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }
    
    /**
     * Check if guests can request quotes
     */
    public function allowGuestsQuote($storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_QUOTE_ALLOW_GUESTS,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }
    
    /**
     * Get quote expiry days
     */
    public function getQuoteExpiryDays($storeId = null): int
    {
        return (int) $this->scopeConfig->getValue(
            self::XML_PATH_QUOTE_EXPIRY_DAYS,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }
    
    /**
     * Get wholesale group ID
     */
    public function getWholesaleGroupId($storeId = null): int
    {
        return (int) $this->scopeConfig->getValue(
            self::XML_PATH_WHOLESALE_GROUP,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }
    
    /**
     * Get wholesale discount percentage
     */
    public function getWholesaleDiscount($storeId = null): float
    {
        return (float) $this->scopeConfig->getValue(
            self::XML_PATH_WHOLESALE_DISCOUNT,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }
    
    /**
     * Get VIP group ID
     */
    public function getVipGroupId($storeId = null): int
    {
        return (int) $this->scopeConfig->getValue(
            self::XML_PATH_VIP_GROUP,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }
    
    /**
     * Get VIP discount percentage
     */
    public function getVipDiscount($storeId = null): float
    {
        return (float) $this->scopeConfig->getValue(
            self::XML_PATH_VIP_DISCOUNT,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }
    
    /**
     * Get default B2B group ID for new approved customers
     */
    public function getDefaultB2BGroupId($storeId = null): int
    {
        return (int) $this->scopeConfig->getValue(
            self::XML_PATH_DEFAULT_B2B_GROUP,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Check if CNAE profiling is enabled
     */
    public function isCnaeProfilingEnabled($storeId = null): bool
    {
        return $this->isEnabled($storeId) && $this->scopeConfig->isSetFlag(
            self::XML_PATH_CNAE_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Check if direct-profile customers should be auto-approved
     */
    public function isCnaeAutoApproveDirect($storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_CNAE_AUTO_APPROVE_DIRECT,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }
}
