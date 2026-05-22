<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Model;

use GrupoAwamotos\ERPIntegration\Model\ResourceModel\SyncLog as SyncLogResource;
use Magento\Customer\Api\AddressRepositoryInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Model\ResourceModel\Customer\CollectionFactory as CustomerCollectionFactory;
use GrupoAwamotos\B2B\Model\Customer\Attribute\Source\ApprovalStatus;

/**
 * Distingue cadastro real via /b2b/register/ de clientes legados provisionados pelo ERP.
 */
class RealB2BRegistrationChecker
{
    public const SEGMENT_REAL_REGISTER = 'real_register';
    public const SEGMENT_ERP_LEGACY = 'erp_legacy';

    private CustomerRepositoryInterface $customerRepository;
    private AddressRepositoryInterface $addressRepository;
    private CustomerCollectionFactory $customerCollectionFactory;
    private SyncLogResource $syncLogResource;

    public function __construct(
        CustomerRepositoryInterface $customerRepository,
        AddressRepositoryInterface $addressRepository,
        CustomerCollectionFactory $customerCollectionFactory,
        SyncLogResource $syncLogResource
    ) {
        $this->customerRepository = $customerRepository;
        $this->addressRepository = $addressRepository;
        $this->customerCollectionFactory = $customerCollectionFactory;
        $this->syncLogResource = $syncLogResource;
    }

    public function isRealB2BRegistration(int $customerId): bool
    {
        try {
            $customer = $this->customerRepository->getById($customerId);
        } catch (\Exception) {
            return false;
        }

        return $this->isRealB2BRegistrationCustomer($customer);
    }

    public function isRealB2BRegistrationCustomer(CustomerInterface $customer): bool
    {
        $status = $this->getAttributeValue($customer, 'b2b_approval_status');
        if (!in_array($status, [ApprovalStatus::STATUS_PENDING, ApprovalStatus::STATUS_DATA_REVIEW], true)) {
            return false;
        }

        return $this->getMissingRegistrationFields($customer) === [];
    }

    /**
     * @return list<string>
     */
    public function getMissingRegistrationFields(CustomerInterface $customer): array
    {
        $missing = [];

        $requiredAttributes = [
            'b2b_cnpj' => 'CNPJ',
            'b2b_razao_social' => 'Razão Social',
            'b2b_phone' => 'Telefone',
        ];

        foreach ($requiredAttributes as $code => $label) {
            $value = trim((string) ($this->getAttributeValue($customer, $code) ?? ''));
            if ($value === '') {
                $missing[] = $label;
            }
        }

        $cnpjDigits = preg_replace('/\D/', '', (string) ($this->getAttributeValue($customer, 'b2b_cnpj') ?? ''));
        if ($cnpjDigits !== '' && strlen($cnpjDigits) !== 14) {
            $missing[] = 'CNPJ inválido';
        }

        if (trim($customer->getFirstname()) === '') {
            $missing[] = 'Nome';
        }

        if (trim($customer->getLastname()) === '') {
            $missing[] = 'Sobrenome';
        }

        if (trim((string) $customer->getEmail()) === '') {
            $missing[] = 'E-mail';
        }

        if (!$this->hasValidAddress($customer)) {
            $missing[] = 'Endereço completo';
        }

        $personType = $this->getAttributeValue($customer, 'b2b_person_type');
        if ($personType !== null && $personType !== '' && $personType !== 'pj') {
            $missing[] = 'Tipo pessoa PJ';
        }

        return $missing;
    }

    public function getSegment(int $customerId): string
    {
        return $this->isRealB2BRegistration($customerId)
            ? self::SEGMENT_REAL_REGISTER
            : self::SEGMENT_ERP_LEGACY;
    }

    public function isErpMapped(int $customerId): bool
    {
        $erpCode = $this->syncLogResource->getErpCodeByMagentoId('customer', $customerId);

        return $erpCode !== null && $erpCode !== '';
    }

    /**
     * Clientes pendentes segmentados.
     *
     * @return array{real_register: list<int>, erp_legacy: list<int>}
     */
    public function segmentPendingCustomerIds(): array
    {
        $collection = $this->customerCollectionFactory->create();
        $collection->addAttributeToSelect([
            'b2b_approval_status',
            'b2b_cnpj',
            'b2b_razao_social',
            'b2b_phone',
            'b2b_person_type',
            'firstname',
            'lastname',
            'email',
        ]);
        $collection->addAttributeToFilter(
            'b2b_approval_status',
            ['in' => [ApprovalStatus::STATUS_PENDING, ApprovalStatus::STATUS_DATA_REVIEW]]
        );
        $collection->load();

        $real = [];
        $legacy = [];

        foreach ($collection as $customer) {
            $customerId = (int) $customer->getId();
            if ($this->isRealB2BRegistration($customerId)) {
                $real[] = $customerId;
            } else {
                $legacy[] = $customerId;
            }
        }

        return [
            self::SEGMENT_REAL_REGISTER => $real,
            self::SEGMENT_ERP_LEGACY => $legacy,
        ];
    }

    private function hasValidAddress(CustomerInterface $customer): bool
    {
        $defaultBilling = $customer->getDefaultBilling();
        if ($defaultBilling === null) {
            return false;
        }

        try {
            $address = $this->addressRepository->getById((int) $defaultBilling);
        } catch (\Exception) {
            return false;
        }

        $street = implode(' ', $address->getStreet());
        if (trim($street) === '') {
            return false;
        }

        if (trim((string) $address->getCity()) === '') {
            return false;
        }

        if (trim((string) $address->getPostcode()) === '') {
            return false;
        }

        return $address->getRegionId() !== null || trim((string) $address->getRegion()) !== '';
    }

    private function getAttributeValue(CustomerInterface $customer, string $code): ?string
    {
        $attribute = $customer->getCustomAttribute($code);

        return $attribute !== null ? (string) $attribute->getValue() : null;
    }
}
