<?php

declare(strict_types=1);

namespace GrupoAwamotos\WhatsAppCommerce\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Store\Model\ScopeInterface;

class Config extends AbstractHelper
{
    // General
    private const XML_PATH_ENABLED = 'whatsapp_commerce/general/enabled';
    private const XML_PATH_MAX_PRODUCTS = 'whatsapp_commerce/general/max_products_per_response';
    private const XML_PATH_CHECKOUT_URL = 'whatsapp_commerce/general/checkout_base_url';

    // Notifications
    private const XML_PATH_NOTIFY_PLACED = 'whatsapp_commerce/notifications/order_placed';
    private const XML_PATH_NOTIFY_PAID = 'whatsapp_commerce/notifications/order_paid';
    private const XML_PATH_NOTIFY_SHIPPED = 'whatsapp_commerce/notifications/order_shipped';
    private const XML_PATH_NOTIFY_REFUNDED = 'whatsapp_commerce/notifications/order_refunded';

    // Phase 6: Review Request
    private const XML_PATH_REVIEW_ENABLED = 'whatsapp_commerce/review_request/enabled';
    private const XML_PATH_REVIEW_DELAY = 'whatsapp_commerce/review_request/delay_days';

    // Phase 6: Meta Description IA
    private const XML_PATH_META_ENABLED = 'whatsapp_commerce/meta_description/enabled';
    private const XML_PATH_META_GROQ_KEY = 'whatsapp_commerce/meta_description/groq_api_key';
    private const XML_PATH_META_BATCH_SIZE = 'whatsapp_commerce/meta_description/batch_size';

    // Phase 6: Social Post
    private const XML_PATH_SOCIAL_ENABLED = 'whatsapp_commerce/social_post/enabled';
    private const XML_PATH_SOCIAL_WEBHOOK = 'whatsapp_commerce/social_post/webhook_url';

    // Phase 6: Retargeting
    private const XML_PATH_RETARGETING_ENABLED = 'whatsapp_commerce/retargeting/enabled';
    private const XML_PATH_RETARGETING_INACTIVE_DAYS = 'whatsapp_commerce/retargeting/inactive_days';
    private const XML_PATH_RETARGETING_HIGH_VALUE = 'whatsapp_commerce/retargeting/high_value_threshold';

    // ==================== General ====================

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

    // ==================== Notifications ====================

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

    // ==================== Phase 6: Review Request ====================

    public function isReviewRequestEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_REVIEW_ENABLED, ScopeInterface::SCOPE_STORE);
    }

    public function getReviewDelayDays(): int
    {
        return (int) $this->scopeConfig->getValue(self::XML_PATH_REVIEW_DELAY) ?: 7;
    }

    // ==================== Phase 6: Meta Description IA ====================

    public function isMetaGenerationEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_META_ENABLED);
    }

    public function getGroqApiKey(): string
    {
        return (string) $this->scopeConfig->getValue(self::XML_PATH_META_GROQ_KEY);
    }

    public function getMetaBatchSize(): int
    {
        return (int) $this->scopeConfig->getValue(self::XML_PATH_META_BATCH_SIZE) ?: 20;
    }

    // ==================== Phase 6: Retargeting ====================

    public function isRetargetingEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_RETARGETING_ENABLED, ScopeInterface::SCOPE_STORE);
    }

    public function getRetargetingInactiveDays(): int
    {
        return (int) $this->scopeConfig->getValue(self::XML_PATH_RETARGETING_INACTIVE_DAYS) ?: 60;
    }

    public function getRetargetingHighValueThreshold(): float
    {
        return (float) $this->scopeConfig->getValue(self::XML_PATH_RETARGETING_HIGH_VALUE) ?: 500.0;
    }

    // ==================== Phase 6: Social Post ====================

    public function isSocialPostEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_SOCIAL_ENABLED);
    }

    public function getSocialPostWebhookUrl(): string
    {
        return (string) $this->scopeConfig->getValue(self::XML_PATH_SOCIAL_WEBHOOK);
    }
}
