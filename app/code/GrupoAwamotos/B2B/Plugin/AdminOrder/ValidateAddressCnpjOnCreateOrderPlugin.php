<?php
declare(strict_types=1);

namespace GrupoAwamotos\B2B\Plugin\AdminOrder;

use GrupoAwamotos\B2B\Helper\CnpjValidator;
use GrupoAwamotos\B2B\Helper\Data as B2BHelper;
use GrupoAwamotos\ERPIntegration\Helper\Data as ErpHelper;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address;
use Magento\Sales\Model\AdminOrder\Create;

class ValidateAddressCnpjOnCreateOrderPlugin
{
    private CnpjValidator $cnpjValidator;
    private ErpHelper $erpHelper;
    private B2BHelper $b2bHelper;

    public function __construct(
        CnpjValidator $cnpjValidator,
        ErpHelper $erpHelper,
        B2BHelper $b2bHelper
    )
    {
        $this->cnpjValidator = $cnpjValidator;
        $this->erpHelper = $erpHelper;
        $this->b2bHelper = $b2bHelper;
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

        if (!$this->shouldRequirePullCnpj($quote)) {
            return;
        }

        $cnpj = $this->extractQuoteCnpj($quote);
        if ($cnpj === '') {
            throw new LocalizedException(
                __('Não foi possível criar o pedido B2B no admin para integração ERP: cliente sem CNPJ válido. Atualize o cadastro da empresa antes de concluir o pedido.')
            );
        }

        if (!$this->cnpjValidator->validateLocal($cnpj)) {
            throw new LocalizedException(
                __('Não foi possível criar o pedido B2B no admin para integração ERP: o CNPJ informado é inválido. Revise o cadastro da empresa antes de concluir o pedido.')
            );
        }

        $formattedCnpj = $this->cnpjValidator->format($cnpj);
        $quote->setData('b2b_cnpj', $formattedCnpj);
        $quote->setData('customer_taxvat', $formattedCnpj);
        $this->applyFormattedCnpj($quote->getBillingAddress(), $formattedCnpj);
        $this->applyFormattedCnpj($quote->getShippingAddress(), $formattedCnpj);
    }

    private function shouldRequirePullCnpj(Quote $quote): bool
    {
        if (!$this->erpHelper->isOrderSyncEnabled() || $this->erpHelper->sendOrderOnPlace()) {
            return false;
        }

        $customerId = (int) $quote->getCustomerId();

        return $customerId > 0 && $this->b2bHelper->isB2BCustomerById($customerId);
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

    private function extractQuoteCnpj(Quote $quote): string
    {
        $billingAddress = $quote->getBillingAddress();
        $shippingAddress = $quote->getShippingAddress();

        foreach ([
            (string) $quote->getData('b2b_cnpj'),
            (string) $quote->getData('customer_taxvat'),
            (string) ($billingAddress ? $billingAddress->getVatId() : ''),
            (string) ($shippingAddress ? $shippingAddress->getVatId() : ''),
        ] as $value) {
            $digits = $this->cnpjValidator->clean($value);
            if (strlen($digits) === 14) {
                return $digits;
            }
        }

        return '';
    }

    private function applyFormattedCnpj(?Address $address, string $formattedCnpj): void
    {
        if (!$address) {
            return;
        }

        $countryId = strtoupper(trim((string) $address->getCountryId()));
        if ($countryId !== '' && $countryId !== 'BR') {
            return;
        }

        $address->setVatId($formattedCnpj);
    }
}
