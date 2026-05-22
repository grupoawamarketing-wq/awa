<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Model;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\ResourceModel\Customer\CollectionFactory as CustomerCollectionFactory;

class CnpjDuplicateChecker
{
    private CustomerRepositoryInterface $customerRepository;
    private CustomerCollectionFactory $customerCollectionFactory;

    public function __construct(
        CustomerRepositoryInterface $customerRepository,
        CustomerCollectionFactory $customerCollectionFactory
    ) {
        $this->customerRepository = $customerRepository;
        $this->customerCollectionFactory = $customerCollectionFactory;
    }

    /**
     * @return array{customer_id:int,email:string,cnpj:string}|null
     */
    public function findConflict(int $customerId): ?array
    {
        try {
            $customer = $this->customerRepository->getById($customerId);
        } catch (\Exception) {
            return null;
        }

        $cnpjAttr = $customer->getCustomAttribute('b2b_cnpj');
        if ($cnpjAttr === null) {
            return null;
        }

        $cnpjDigits = $this->normalizeDocument((string) $cnpjAttr->getValue());
        if ($cnpjDigits === '') {
            return null;
        }

        $formattedCnpj = $this->formatCnpj($cnpjDigits);

        $collection = $this->customerCollectionFactory->create();
        $collection->addAttributeToFilter('b2b_cnpj', ['in' => [$cnpjDigits, $formattedCnpj]]);
        $collection->addFieldToFilter('entity_id', ['neq' => $customerId]);
        $collection->setPageSize(1);

        foreach ($collection as $conflict) {
            $conflictCnpj = $this->normalizeDocument((string) $conflict->getData('b2b_cnpj'));
            if ($conflictCnpj !== $cnpjDigits) {
                continue;
            }

            return [
                'customer_id' => (int) $conflict->getId(),
                'email' => (string) $conflict->getEmail(),
                'cnpj' => $formattedCnpj,
            ];
        }

        return null;
    }

    public function hasDuplicate(int $customerId): bool
    {
        return $this->findConflict($customerId) !== null;
    }

    private function normalizeDocument(string $value): string
    {
        return (string) preg_replace('/\D+/', '', $value);
    }

    private function formatCnpj(string $cnpjDigits): string
    {
        if (strlen($cnpjDigits) !== 14) {
            return $cnpjDigits;
        }

        return substr($cnpjDigits, 0, 2)
            . '.' . substr($cnpjDigits, 2, 3)
            . '.' . substr($cnpjDigits, 5, 3)
            . '/' . substr($cnpjDigits, 8, 4)
            . '-' . substr($cnpjDigits, 12, 2);
    }
}
