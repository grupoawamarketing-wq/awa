<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\CommercialPanel\Model\Config;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class TaskConfig
{
    private const XML_PATH_DAYS_NO_PURCHASE = 'grupoawamotos_b2b/commercial_task/days_no_purchase';
    private const XML_PATH_DAYS_QUOTE_NO_RESPONSE = 'grupoawamotos_b2b/commercial_task/days_quote_no_response';
    private const XML_PATH_DAYS_PENDING_NO_CONTACT = 'grupoawamotos_b2b/commercial_task/days_pending_no_contact';
    private const XML_PATH_DAYS_NEW_CUSTOMER = 'grupoawamotos_b2b/commercial_task/days_new_customer_no_contact';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    public function getDaysNoPurchase(): int
    {
        return max(1, (int) $this->scopeConfig->getValue(self::XML_PATH_DAYS_NO_PURCHASE, ScopeInterface::SCOPE_STORE));
    }

    public function getDaysQuoteNoResponse(): int
    {
        return max(1, (int) $this->scopeConfig->getValue(self::XML_PATH_DAYS_QUOTE_NO_RESPONSE, ScopeInterface::SCOPE_STORE));
    }

    public function getDaysPendingNoContact(): int
    {
        return max(1, (int) $this->scopeConfig->getValue(self::XML_PATH_DAYS_PENDING_NO_CONTACT, ScopeInterface::SCOPE_STORE));
    }

    public function getDaysNewCustomerNoContact(): int
    {
        return max(1, (int) $this->scopeConfig->getValue(self::XML_PATH_DAYS_NEW_CUSTOMER, ScopeInterface::SCOPE_STORE));
    }
}
