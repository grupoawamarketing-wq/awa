<?php

declare(strict_types=1);

namespace GrupoAwamotos\BrazilCustomer\Block\Customer;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;

class BrazilFields extends Template
{
    private CustomerSession $customerSession;
    private CustomerRepositoryInterface $customerRepository;
    private ?array $customerData = null;

    public function __construct(
        Context $context,
        CustomerSession $customerSession,
        CustomerRepositoryInterface $customerRepository,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->customerSession = $customerSession;
        $this->customerRepository = $customerRepository;
    }

    /**
     * Get current customer's custom attribute value
     */
    public function getCustomerAttributeValue(string $attributeCode): string
    {
        $data = $this->getCustomerData();
        return $data[$attributeCode] ?? '';
    }

    private function getCustomerData(): array
    {
        if ($this->customerData !== null) {
            return $this->customerData;
        }

        $this->customerData = [];

        try {
            $customerId = $this->customerSession->getCustomerId();
            if (!$customerId) {
                return $this->customerData;
            }

            $customer = $this->customerRepository->getById($customerId);
            $attributes = ['person_type', 'cpf', 'rg', 'cnpj', 'ie', 'company_name', 'trade_name'];

            foreach ($attributes as $code) {
                $attr = $customer->getCustomAttribute($code);
                $this->customerData[$code] = $attr ? (string) $attr->getValue() : '';
            }
        } catch (\Exception $e) {
            // Customer not found or attribute error - return defaults
        }

        return $this->customerData;
    }
}
