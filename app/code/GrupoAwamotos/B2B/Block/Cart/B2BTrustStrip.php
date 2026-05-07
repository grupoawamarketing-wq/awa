<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Block\Cart;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Customer\Model\Session as CustomerSession;
use GrupoAwamotos\B2B\Helper\Data as B2BHelper;
use GrupoAwamotos\B2B\Model\CreditService;
use Magento\Framework\Pricing\PriceCurrencyInterface;

class B2BTrustStrip extends Template
{
    private CustomerSession $customerSession;
    private B2BHelper $b2bHelper;
    private CreditService $creditService;
    private PriceCurrencyInterface $priceCurrency;

    public function __construct(
        Context $context,
        CustomerSession $customerSession,
        B2BHelper $b2bHelper,
        CreditService $creditService,
        PriceCurrencyInterface $priceCurrency,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->customerSession = $customerSession;
        $this->b2bHelper = $b2bHelper;
        $this->creditService = $creditService;
        $this->priceCurrency = $priceCurrency;
    }

    /**
     * @return bool
     */
    public function shouldDisplay(): bool
    {
        if (!$this->b2bHelper->isEnabled()) {
            return false;
        }

        if (!$this->customerSession->isLoggedIn()) {
            return false;
        }

        $customerGroupId = (int) $this->customerSession->getCustomerGroupId();
        return in_array($customerGroupId, $this->b2bHelper->getB2BGroupIds());
    }

    /**
     * @return string
     */
    public function getFirstName(): string
    {
        $customer = $this->customerSession->getCustomer();
        return $customer ? (string) $customer->getFirstname() : '';
    }

    /**
     * @return string
     */
    public function getGroupName(): string
    {
        $customerGroupId = (int) $this->customerSession->getCustomerGroupId();
        return $this->b2bHelper->getB2BGroupName($customerGroupId);
    }

    /**
     * @return bool
     */
    public function hasCreditAvailable(): bool
    {
        $customerId = (int) $this->customerSession->getCustomerId();
        if (!$customerId) {
            return false;
        }

        $credit = $this->creditService->getCreditLimit($customerId);
        return $credit->getCreditLimit() > 0;
    }

    /**
     * @return string
     */
    public function getAvailableCreditFormatted(): string
    {
        $customerId = (int) $this->customerSession->getCustomerId();
        $credit = $this->creditService->getCreditLimit($customerId);
        $available = $credit->getCreditLimit() - $credit->getUsedCredit();
        return $this->priceCurrency->format(max(0.0, $available), false);
    }

    /**
     * @return string
     */
    public function getDashboardUrl(): string
    {
        return $this->getUrl('b2b/account/dashboard');
    }
}
