<?php
/**
 * B2B Company Data Config Provider
 * P2-4.1: Provides company data (razão social, CNPJ, IE) for checkout auto-fill
 */
declare(strict_types=1);

namespace GrupoAwamotos\B2B\Model\Checkout;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Customer\Model\Session as CustomerSession;
use GrupoAwamotos\B2B\Model\CompanyService;
use Psr\Log\LoggerInterface;

class CompanyDataConfigProvider implements ConfigProviderInterface
{
    private CustomerSession $customerSession;
    private CompanyService $companyService;
    private LoggerInterface $logger;

    public function __construct(
        CustomerSession $customerSession,
        CompanyService $companyService,
        LoggerInterface $logger
    ) {
        $this->customerSession = $customerSession;
        $this->companyService = $companyService;
        $this->logger = $logger;
    }

    /**
     * Provide B2B company data for checkout auto-fill
     *
     * @return array<string, mixed>
     */
    public function getConfig(): array
    {
        if (!$this->customerSession->isLoggedIn()) {
            return [];
        }

        try {
            $customerId = (int) $this->customerSession->getCustomerId();
            $company = $this->companyService->getCompanyForCustomer($customerId);

            if ($company === null || !$company->getId()) {
                return [];
            }

            return [
                'b2bCompanyData' => [
                    'company' => (string) ($company->getData('razao_social') ?: $company->getData('nome_fantasia') ?: ''),
                    'vatId' => (string) ($company->getData('inscricao_estadual') ?: ''),
                    'cnpj' => (string) ($company->getData('cnpj') ?: ''),
                    'nomeFantasia' => (string) ($company->getData('nome_fantasia') ?: ''),
                    'razaoSocial' => (string) ($company->getData('razao_social') ?: ''),
                ],
            ];
        } catch (\Exception $e) {
            $this->logger->error('[B2B] Erro ao carregar dados da empresa para checkout: ' . $e->getMessage());

            return [];
        }
    }
}
