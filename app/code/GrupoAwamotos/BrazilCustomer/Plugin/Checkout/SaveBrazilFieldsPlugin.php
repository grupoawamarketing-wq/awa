<?php

declare(strict_types=1);

namespace GrupoAwamotos\BrazilCustomer\Plugin\Checkout;

use Magento\Checkout\Api\Data\ShippingInformationInterface;
use Magento\Checkout\Model\ShippingInformationManagement;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Psr\Log\LoggerInterface;

/**
 * Persists Brazilian custom attributes (CPF, CNPJ, etc.) from checkout to customer entity
 */
class SaveBrazilFieldsPlugin
{
    private const BRAZIL_ATTRIBUTES = [
        'person_type',
        'cpf',
        'rg',
        'cnpj',
        'ie',
        'company_name',
        'trade_name',
    ];

    private CustomerRepositoryInterface $customerRepository;
    private CustomerSession $customerSession;
    private LoggerInterface $logger;

    public function __construct(
        CustomerRepositoryInterface $customerRepository,
        CustomerSession $customerSession,
        LoggerInterface $logger
    ) {
        $this->customerRepository = $customerRepository;
        $this->customerSession = $customerSession;
        $this->logger = $logger;
    }

    /**
     * After saving shipping information, persist Brazil fields to customer
     */
    public function afterSaveAddressInformation(
        ShippingInformationManagement $subject,
        $result,
        $cartId,
        ShippingInformationInterface $addressInformation
    ) {
        if (!$this->customerSession->isLoggedIn()) {
            return $result;
        }

        try {
            $shippingAddress = $addressInformation->getShippingAddress();
            $customAttributes = $shippingAddress->getCustomAttributes();

            if (empty($customAttributes)) {
                return $result;
            }

            $customerId = $this->customerSession->getCustomerId();
            $customer = $this->customerRepository->getById($customerId);
            $updated = false;

            foreach (self::BRAZIL_ATTRIBUTES as $attributeCode) {
                $attribute = $shippingAddress->getCustomAttribute($attributeCode);
                if ($attribute && $attribute->getValue()) {
                    $customer->setCustomAttribute($attributeCode, $attribute->getValue());
                    $updated = true;
                }
            }

            // Also update taxvat from CPF/CNPJ for ERP integration compatibility
            if ($updated) {
                $this->updateTaxvat($customer, $shippingAddress);
                $this->customerRepository->save($customer);
            }
        } catch (\Exception $e) {
            $this->logger->error('[BrazilCustomer] Error saving checkout fields: ' . $e->getMessage());
        }

        return $result;
    }

    /**
     * Sync CPF/CNPJ to taxvat field for ERP compatibility
     */
    private function updateTaxvat($customer, $shippingAddress): void
    {
        $personType = $shippingAddress->getCustomAttribute('person_type');
        $type = $personType ? $personType->getValue() : 'pf';

        if ($type === 'pj') {
            $cnpj = $shippingAddress->getCustomAttribute('cnpj');
            if ($cnpj && $cnpj->getValue()) {
                $customer->setTaxvat(preg_replace('/[^0-9]/', '', $cnpj->getValue()));
            }
        } else {
            $cpf = $shippingAddress->getCustomAttribute('cpf');
            if ($cpf && $cpf->getValue()) {
                $customer->setTaxvat(preg_replace('/[^0-9]/', '', $cpf->getValue()));
            }
        }
    }
}
