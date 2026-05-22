<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Block\Minicart;

use GrupoAwamotos\B2B\Helper\CnpjValidator;
use GrupoAwamotos\B2B\Helper\Data as B2BHelper;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;

/**
 * Contexto B2B para o rodapé do minicart (CNPJ formatado do cliente logado).
 */
class TrustBadge extends Template
{
    private CustomerSession $customerSession;
    private B2BHelper $b2bHelper;
    private CnpjValidator $cnpjValidator;

    public function __construct(
        Context $context,
        CustomerSession $customerSession,
        B2BHelper $b2bHelper,
        CnpjValidator $cnpjValidator,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->customerSession = $customerSession;
        $this->b2bHelper = $b2bHelper;
        $this->cnpjValidator = $cnpjValidator;
    }

    public function shouldDisplay(): bool
    {
        if (!$this->b2bHelper->isEnabled() || !$this->customerSession->isLoggedIn()) {
            return false;
        }

        $groupId = (int) $this->customerSession->getCustomerGroupId();

        return in_array($groupId, $this->b2bHelper->getB2BGroupIds(), true);
    }

    public function getFormattedCnpj(): string
    {
        if (!$this->customerSession->isLoggedIn()) {
            return '';
        }

        $customer = $this->customerSession->getCustomer();
        $attr = $customer->getCustomAttribute('b2b_cnpj');
        $raw = $attr ? (string) $attr->getValue() : '';

        if ($raw === '') {
            return '';
        }

        return $this->cnpjValidator->format($raw);
    }

    public function hasCnpj(): bool
    {
        return $this->getFormattedCnpj() !== '';
    }
}
