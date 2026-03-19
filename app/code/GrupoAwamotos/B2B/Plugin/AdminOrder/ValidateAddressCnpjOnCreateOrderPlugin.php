<?php
declare(strict_types=1);

namespace GrupoAwamotos\B2B\Plugin\AdminOrder;

use GrupoAwamotos\B2B\Helper\CnpjValidator;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Model\Quote\Address;
use Magento\Sales\Model\AdminOrder\Create;

class ValidateAddressCnpjOnCreateOrderPlugin
{
    private CnpjValidator $cnpjValidator;

    public function __construct(CnpjValidator $cnpjValidator)
    {
        $this->cnpjValidator = $cnpjValidator;
    }

    public function beforeCreateOrder(Create $subject): void
    {
        $quote = $subject->getQuote();
        if (!$quote) {
            return;
        }

        $this->validateAddressCnpj($quote->getBillingAddress(), 'faturamento');

        if (!$quote->isVirtual()) {
            $this->validateAddressCnpj($quote->getShippingAddress(), 'entrega');
        }
    }

    private function validateAddressCnpj(?Address $address, string $addressLabel): void
    {
        if (!$address) {
            return;
        }

        $countryId = strtoupper(trim((string) $address->getCountryId()));
        if ($countryId !== 'BR') {
            return;
        }

        $vatId = trim((string) $address->getVatId());
        if ($vatId === '') {
            return;
        }

        $digits = $this->cnpjValidator->clean($vatId);
        if (strlen($digits) !== 14) {
            return;
        }

        if (!$this->cnpjValidator->validateLocal($digits)) {
            throw new LocalizedException(
                __('CNPJ inválido informado no endereço de %1.', $addressLabel)
            );
        }

        $address->setVatId($this->cnpjValidator->format($digits));
    }
}
