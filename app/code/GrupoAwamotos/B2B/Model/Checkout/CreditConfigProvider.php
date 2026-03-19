<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Model\Checkout;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * Config provider for B2B Credit payment method
 *
 * @deprecated Use \GrupoAwamotos\B2B\Model\Payment\CreditConfigProvider instead.
 * This provider is kept for backward compatibility but returns an empty config.
 * @see \GrupoAwamotos\B2B\Model\Payment\CreditConfigProvider
 */
class CreditConfigProvider implements ConfigProviderInterface
{
    private const XML_PATH_CREDIT_ENABLED = 'grupoawamotos_b2b/credit/enabled';
    private const XML_PATH_DEFAULT_LIMIT = 'grupoawamotos_b2b/credit/default_limit';
    private const XML_PATH_AUTO_DEBIT = 'grupoawamotos_b2b/credit/auto_debit';
    private const XML_PATH_ALLOW_PARTIAL = 'grupoawamotos_b2b/credit/allow_partial';

    /**
     * @var ScopeConfigInterface
     */
    private ScopeConfigInterface $scopeConfig;

    /**
     * @var CustomerSession
     */
    private CustomerSession $customerSession;

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param CustomerSession $customerSession
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        CustomerSession $customerSession
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->customerSession = $customerSession;
    }

    /**
     * @inheritDoc
     *
     * @deprecated Returns empty array. Use Payment\CreditConfigProvider.
     */
    public function getConfig(): array
    {
        return [];
    }

    /**
     * Check if credit system is enabled
     *
     * @return bool
     */
    private function isEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_CREDIT_ENABLED,
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Check if partial payment is allowed
     *
     * @return bool
     */
    private function isAllowPartial(): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_ALLOW_PARTIAL,
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Check if auto debit is enabled
     *
     * @return bool
     */
    private function isAutoDebit(): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_AUTO_DEBIT,
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Get customer credit information
     *
     * @return array{limit: float, available: float, used: float}
     */
    private function getCustomerCreditInfo(): array
    {
        if (!$this->customerSession->isLoggedIn()) {
            return ['limit' => 0.0, 'available' => 0.0, 'used' => 0.0];
        }

        $customer = $this->customerSession->getCustomer();

        // Get credit attributes from customer
        $creditLimit = (float) ($customer->getData('b2b_credit_limit') ?? 0.0);
        $creditUsed = (float) ($customer->getData('b2b_credit_used') ?? 0.0);

        // If no custom limit, use default
        if ($creditLimit <= 0) {
            $creditLimit = (float) $this->scopeConfig->getValue(
                self::XML_PATH_DEFAULT_LIMIT,
                ScopeInterface::SCOPE_STORE
            );
        }

        return [
            'limit' => $creditLimit,
            'available' => max(0, $creditLimit - $creditUsed),
            'used' => $creditUsed,
        ];
    }
}
