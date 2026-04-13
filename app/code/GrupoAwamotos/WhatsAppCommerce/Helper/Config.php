<?php
declare(strict_types=1);

namespace GrupoAwamotos\WhatsAppCommerce\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Store\Model\ScopeInterface;

class Config extends AbstractHelper
{
    private const XML_PATH_ENABLED = 'whatsapp_commerce/general/enabled';
    private const XML_PATH_MAX_PRODUCTS = 'whatsapp_commerce/general/max_products_per_response';
    private const XML_PATH_CHECKOUT_URL = 'whatsapp_commerce/general/checkout_base_url';
    private const XML_PATH_NOTIFY_PLACED = 'whatsapp_commerce/notifications/order_placed';
    private const XML_PATH_NOTIFY_PAID = 'whatsapp_commerce/notifications/order_paid';
    private const XML_PATH_NOTIFY_SHIPPED = 'whatsapp_commerce/notifications/order_shipped';
    private const XML_PATH_NOTIFY_REFUNDED = 'whatsapp_commerce/notifications/order_refunded';

    public function isEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_ENABLED,
            ScopeInterface::SCOPE_STORE
        );
    }

    public function getMaxProductsPerResponse(): int
    {
        return (int) $this->scopeConfig->getValue(
            self::XML_PATH_MAX_PRODUCTS,
            ScopeInterface::SCOPE_STORE
        ) ?: 5;
    }

    public function getCheckoutBaseUrl(): string
    {
        $url = (string) $this->scopeConfig->getValue(
            self::XML_PATH_CHECKOUT_URL,
            ScopeInterface::SCOPE_STORE
        );
        return $url ?: '';
    }

    public function isNotifyOrderPlacedEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_NOTIFY_PLACED, ScopeInterface::SCOPE_STORE);
    }

    public function isNotifyOrderPaidEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_NOTIFY_PAID, ScopeInterface::SCOPE_STORE);
    }

    public function isNotifyOrderShippedEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_NOTIFY_SHIPPED, ScopeInterface::SCOPE_STORE);
    }

    public function isNotifyOrderRefundedEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_NOTIFY_REFUNDED, ScopeInterface::SCOPE_STORE);
    }
}
