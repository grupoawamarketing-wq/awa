<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Block\Account;

use Magento\Framework\View\Element\Template;
use Magento\Customer\Model\Session;
use GrupoAwamotos\B2B\Model\CompanyService;
use GrupoAwamotos\B2B\Model\Company as CompanyModel;
use Magento\Customer\Api\CustomerRepositoryInterface;

class CompanyBlock extends Template
{
    private Session $customerSession;
    private CompanyService $companyService;
    private CustomerRepositoryInterface $customerRepository;

    public function __construct(
        Template\Context $context,
        Session $customerSession,
        CompanyService $companyService,
        CustomerRepositoryInterface $customerRepository,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->customerSession = $customerSession;
        $this->companyService = $companyService;
        $this->customerRepository = $customerRepository;
    }

    public function getCompany(): ?CompanyModel
    {
        $customerId = (int) $this->customerSession->getCustomerId();
        $company = $this->companyService->getCompanyForCustomer($customerId);

        if (!$company) {
            $company = $this->companyService->findOrCreateByCustomer($customerId);
        }

        return $company;
    }

    public function getCompanyUsers(): array
    {
        $company = $this->getCompany();
        if (!$company) {
            return [];
        }

        $users = [];
        foreach ($this->companyService->getCompanyUsers((int) $company->getId()) as $companyUser) {
            $customerId = (int) $companyUser->getData('customer_id');
            try {
                $customer = $this->customerRepository->getById($customerId);
                $users[] = [
                    'customer_id' => $customerId,
                    'name' => $customer->getFirstname() . ' ' . $customer->getLastname(),
                    'email' => $customer->getEmail(),
                    'role' => $companyUser->getData('role'),
                    'role_label' => $this->getRoleLabel($companyUser->getData('role')),
                    'is_active' => (bool) $companyUser->getData('is_active'),
                ];
            } catch (\Exception $e) {
                continue;
            }
        }

        return $users;
    }

    public function isCompanyAdmin(): bool
    {
        $role = $this->companyService->getUserRole(
            (int) $this->customerSession->getCustomerId()
        );
        return $role === CompanyModel::ROLE_ADMIN;
    }

    public function getCurrentUserRole(): ?string
    {
        return $this->companyService->getUserRole(
            (int) $this->customerSession->getCustomerId()
        );
    }

    public function getRoleLabel(string $role): string
    {
        $roles = CompanyModel::getRoles();
        return isset($roles[$role]) ? (string) $roles[$role] : $role;
    }

    public function getRoles(): array
    {
        return CompanyModel::getRoles();
    }

    public function getManageUserUrl(): string
    {
        return $this->getUrl('b2b/company/manageUser');
    }
}
