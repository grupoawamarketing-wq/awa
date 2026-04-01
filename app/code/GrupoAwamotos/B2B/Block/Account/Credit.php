<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Block\Account;

use Magento\Framework\View\Element\Template;
use Magento\Customer\Model\Session;
use GrupoAwamotos\B2B\Model\CreditService;
use Magento\Framework\Pricing\PriceCurrencyInterface;

class Credit extends Template
{
    private Session $customerSession;
    private CreditService $creditService;
    private PriceCurrencyInterface $priceCurrency;

    public function __construct(
        Template\Context $context,
        Session $customerSession,
        CreditService $creditService,
        PriceCurrencyInterface $priceCurrency,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->customerSession = $customerSession;
        $this->creditService = $creditService;
        $this->priceCurrency = $priceCurrency;
    }

    public function getCreditLimit()
    {
        return $this->creditService->getCreditLimit(
            (int) $this->customerSession->getCustomerId()
        );
    }

    public function getTransactions()
    {
        return $this->creditService->getTransactions(
            (int) $this->customerSession->getCustomerId(),
            50
        );
    }

    public function formatPrice(float $price): string
    {
        return $this->priceCurrency->format($price, false);
    }

    public function getUsagePercent(): float
    {
        $credit = $this->getCreditLimit();
        if ($credit->getCreditLimit() <= 0) {
            return 0;
        }
        return min(100, ($credit->getUsedCredit() / $credit->getCreditLimit()) * 100);
    }

    public function getTransactionTypeLabel(string $type): string
    {
        $types = \GrupoAwamotos\B2B\Model\CreditTransaction::getTypes();
        return isset($types[$type]) ? (string) $types[$type] : $type;
    }
}
