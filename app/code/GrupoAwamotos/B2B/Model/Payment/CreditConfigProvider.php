<?php

/**
 * B2B Credit Payment Config Provider
 *
 * Provides credit balance info to the checkout JS context.
 */

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Model\Payment;

use GrupoAwamotos\B2B\Helper\Data as B2BHelper;
use GrupoAwamotos\B2B\Model\CreditService;
use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Psr\Log\LoggerInterface;

class CreditConfigProvider implements ConfigProviderInterface
{
    private const XML_PATH_PAYMENT_ACTIVE = 'payment/b2b_credit/active';

    /**
     * @var CreditService
     */
    private CreditService $creditService;

    /**
     * @var CustomerSession
     */
    private CustomerSession $customerSession;

    /**
     * @var PriceCurrencyInterface
     */
    private PriceCurrencyInterface $priceCurrency;

    /**
     * @var ScopeConfigInterface
     */
    private ScopeConfigInterface $scopeConfig;

    /**
     * @var B2BHelper
     */
    private B2BHelper $b2bHelper;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    public function __construct(
        CreditService $creditService,
        CustomerSession $customerSession,
        PriceCurrencyInterface $priceCurrency,
        ScopeConfigInterface $scopeConfig,
        B2BHelper $b2bHelper,
        LoggerInterface $logger
    ) {
        $this->creditService = $creditService;
        $this->customerSession = $customerSession;
        $this->priceCurrency = $priceCurrency;
        $this->scopeConfig = $scopeConfig;
        $this->b2bHelper = $b2bHelper;
        $this->logger = $logger;
    }

    /**
     * Provide B2B credit payment config to checkout
     *
     * @return array
     */
    public function getConfig(): array
    {
        if (!$this->isMethodEligibleForCustomer()) {
            return [];
        }

        $customerId = (int)$this->customerSession->getCustomerId();
        if ($customerId <= 0) {
            return [];
        }

        try {
            $creditLimit = $this->creditService->getCreditLimit($customerId);
        } catch (\Throwable $exception) {
            $this->logger->error(
                'Failed to load B2B credit checkout config.',
                [
                    'customer_id' => $customerId,
                    'exception' => $exception,
                ]
            );

            return [];
        }

        $limit = max(0.0, (float)$creditLimit->getCreditLimit());
        $used = max(0.0, (float)$creditLimit->getUsedCredit());
        $available = max(0.0, $limit - $used);

        return [
            'payment' => [
                'b2b_credit' => [
                    'title' => $this->creditService->getPaymentTitle(),
                    'credit_info' => [
                        'limit' => $limit,
                        'used' => $used,
                        'available' => $available,
                        'limit_formatted' => $this->priceCurrency->format($limit, false),
                        'used_formatted' => $this->priceCurrency->format($used, false),
                        'available_formatted' => $this->priceCurrency->format($available, false),
                    ],
                    'payment_terms' => $this->creditService->getAvailablePaymentTerms($customerId),
                ],
            ],
        ];
    }

    /**
     * Expose credit data only when payment method is active and customer can actually use it.
     */
    private function isMethodEligibleForCustomer(): bool
    {
        if (!$this->scopeConfig->isSetFlag(self::XML_PATH_PAYMENT_ACTIVE)) {
            return false;
        }

        if (!$this->creditService->isEnabled() || !$this->customerSession->isLoggedIn()) {
            return false;
        }

        return $this->b2bHelper->isApprovedB2BCustomer();
    }
}
