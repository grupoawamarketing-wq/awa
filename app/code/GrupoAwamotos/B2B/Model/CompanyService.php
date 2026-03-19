<?php
declare(strict_types=1);

namespace GrupoAwamotos\B2B\Model;

use GrupoAwamotos\B2B\Model\CompanyFactory;
use GrupoAwamotos\B2B\Model\CompanyUserFactory;
use GrupoAwamotos\B2B\Model\ResourceModel\Company as CompanyResource;
use GrupoAwamotos\B2B\Model\ResourceModel\CompanyUser as CompanyUserResource;
use GrupoAwamotos\B2B\Model\ResourceModel\Company\CollectionFactory as CompanyCollectionFactory;
use GrupoAwamotos\B2B\Model\ResourceModel\CompanyUser\CollectionFactory as UserCollectionFactory;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\Exception\LocalizedException;
use Psr\Log\LoggerInterface;

class CompanyService
{
    private CompanyFactory $companyFactory;
    private CompanyUserFactory $userFactory;
    private CompanyResource $companyResource;
    private CompanyUserResource $userResource;
    private CompanyCollectionFactory $companyCollectionFactory;
    private UserCollectionFactory $userCollectionFactory;
    private CustomerRepositoryInterface $customerRepository;
    private LoggerInterface $logger;

    public function __construct(
        CompanyFactory $companyFactory,
        CompanyUserFactory $userFactory,
        CompanyResource $companyResource,
        CompanyUserResource $userResource,
        CompanyCollectionFactory $companyCollectionFactory,
        UserCollectionFactory $userCollectionFactory,
        CustomerRepositoryInterface $customerRepository,
        LoggerInterface $logger
    ) {
        $this->companyFactory = $companyFactory;
        $this->userFactory = $userFactory;
        $this->companyResource = $companyResource;
        $this->userResource = $userResource;
        $this->companyCollectionFactory = $companyCollectionFactory;
        $this->userCollectionFactory = $userCollectionFactory;
        $this->customerRepository = $customerRepository;
        $this->logger = $logger;
    }

    /**
     * Find or create company by CNPJ, assign customer as admin
     */
    public function findOrCreateByCustomer(int $customerId): ?Company
    {
        $customer = $this->customerRepository->getById($customerId);
        $cnpj = $this->getCustomerCnpj($customer);

        if (!$cnpj) {
            return null;
        }

        $company = $this->findByCnpj($cnpj);
        if (!$company) {
            $company = $this->createCompany($cnpj, $customer, $customerId);
        }

        $this->ensureUserInCompany($company->getId(), $customerId, Company::ROLE_ADMIN);
        return $company;
    }

    public function findByCnpj(string $cnpj): ?Company
    {
        $collection = $this->companyCollectionFactory->create();
        $collection->addFieldToFilter('cnpj', $cnpj);
        $company = $collection->getFirstItem();
        return $company->getId() ? $company : null;
    }

    public function getCompanyForCustomer(int $customerId): ?Company
    {
        $userCollection = $this->userCollectionFactory->create();
        $userCollection->addFieldToFilter('customer_id', $customerId);
        $userCollection->addFieldToFilter('is_active', 1);
        $user = $userCollection->getFirstItem();

        if (!$user->getId()) {
            return null;
        }

        $company = $this->companyFactory->create();
        $this->companyResource->load($company, $user->getData('company_id'));
        return $company->getId() ? $company : null;
    }

    public function getCompanyUsers(int $companyId): \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection
    {
        return $this->userCollectionFactory->create()->filterByCompany($companyId);
    }

    /**
     * Invite user to company
     */
    public function addUser(int $companyId, int $customerId, string $role = Company::ROLE_BUYER): CompanyUser
    {
        $existing = $this->getUserInCompany($companyId, $customerId);
        if ($existing) {
            throw new LocalizedException(__('Este usuário já está vinculado à empresa.'));
        }

        return $this->ensureUserInCompany($companyId, $customerId, $role);
    }

    /**
     * Remove user from company
     */
    public function removeUser(int $companyId, int $customerId): void
    {
        $user = $this->getUserInCompany($companyId, $customerId);
        if ($user) {
            $this->userResource->delete($user);
            $this->logger->info("B2B Company user removed: company=$companyId customer=$customerId");
        }
    }

    /**
     * Update user role
     */
    public function updateUserRole(int $companyId, int $customerId, string $role): void
    {
        $user = $this->getUserInCompany($companyId, $customerId);
        if ($user) {
            $user->setData('role', $role);
            $this->userResource->save($user);
        }
    }

    /**
     * Get user role in company
     */
    public function getUserRole(int $customerId): ?string
    {
        $userCollection = $this->userCollectionFactory->create();
        $userCollection->addFieldToFilter('customer_id', $customerId);
        $userCollection->addFieldToFilter('is_active', 1);
        $user = $userCollection->getFirstItem();
        return $user->getId() ? $user->getData('role') : null;
    }

    private function getUserInCompany(int $companyId, int $customerId): ?CompanyUser
    {
        $collection = $this->userCollectionFactory->create();
        $collection->addFieldToFilter('company_id', $companyId);
        $collection->addFieldToFilter('customer_id', $customerId);
        $user = $collection->getFirstItem();
        return $user->getId() ? $user : null;
    }

    private function ensureUserInCompany(int $companyId, int $customerId, string $role): CompanyUser
    {
        $existing = $this->getUserInCompany($companyId, $customerId);
        if ($existing) {
            return $existing;
        }

        $user = $this->userFactory->create();
        $user->setData([
            'company_id' => $companyId,
            'customer_id' => $customerId,
            'role' => $role,
            'is_active' => 1,
        ]);
        $this->userResource->save($user);

        $this->logger->info("B2B Company user added: company=$companyId customer=$customerId role=$role");
        return $user;
    }

    private function createCompany(string $cnpj, $customer, int $customerId): Company
    {
        $company = $this->companyFactory->create();
        $company->setData([
            'cnpj' => $cnpj,
            'razao_social' => $this->getCustomerAttribute($customer, 'b2b_razao_social') ?: $customer->getFirstname() . ' ' . $customer->getLastname(),
            'nome_fantasia' => $this->getCustomerAttribute($customer, 'b2b_razao_social'),
            'inscricao_estadual' => $this->getCustomerAttribute($customer, 'b2b_inscricao_estadual'),
            'email' => $customer->getEmail(),
            'phone' => $this->getCustomerAttribute($customer, 'b2b_company_phone'),
            'admin_customer_id' => $customerId,
            'is_active' => 1,
        ]);
        $this->companyResource->save($company);

        $this->logger->info("B2B Company created: cnpj=$cnpj company_id=" . $company->getId());
        return $company;
    }

    private function getCustomerCnpj($customer): ?string
    {
        return $this->getCustomerAttribute($customer, 'b2b_cnpj');
    }

    private function getCustomerAttribute($customer, string $code): ?string
    {
        $attr = $customer->getCustomAttribute($code);
        return $attr ? $attr->getValue() : null;
    }
}
