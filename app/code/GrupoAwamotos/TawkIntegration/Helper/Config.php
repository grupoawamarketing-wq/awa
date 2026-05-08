<?php

declare(strict_types=1);

namespace GrupoAwamotos\TawkIntegration\Helper;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Store\Model\ScopeInterface;

class Config extends AbstractHelper
{
    private const XML_PREFIX = 'grupoawamotos_tawk/';

    private EncryptorInterface $encryptor;

    public function __construct(
        Context $context,
        EncryptorInterface $encryptor
    ) {
        parent::__construct($context);
        $this->encryptor = $encryptor;
    }

    public function isEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PREFIX . 'general/enabled',
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function getPropertyId(?int $storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::XML_PREFIX . 'general/property_id',
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function getWidgetId(?int $storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::XML_PREFIX . 'general/widget_id',
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function getApiKey(): string
    {
        $encrypted = (string) $this->scopeConfig->getValue(
            self::XML_PREFIX . 'secure_mode/api_key'
        );
        return $encrypted ? $this->encryptor->decrypt($encrypted) : '';
    }

    public function isWebhookEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PREFIX . 'webhook/enabled'
        );
    }

    public function getWebhookSecret(): string
    {
        $encrypted = (string) $this->scopeConfig->getValue(
            self::XML_PREFIX . 'webhook/secret'
        );
        return $encrypted ? $this->encryptor->decrypt($encrypted) : '';
    }

    public function shouldSendB2bData(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PREFIX . 'enrichment/send_b2b_data',
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function shouldSendRfmData(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PREFIX . 'enrichment/send_rfm_data',
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function shouldSendOrderData(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PREFIX . 'enrichment/send_order_data',
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function shouldSendCartData(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PREFIX . 'enrichment/send_cart_data',
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Generate HMAC-SHA256 hash for Secure Mode
     *
     * @param string $email Customer email
     * @return string HMAC hash
     */
    public function generateVisitorHash(string $email): string
    {
        $apiKey = $this->getApiKey();
        if ($apiKey === '') {
            return '';
        }
        return hash_hmac('sha256', $email, $apiKey);
    }
}
