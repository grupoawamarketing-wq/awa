<?php
declare(strict_types=1);

namespace GrupoAwamotos\B2B\Observer;

use GrupoAwamotos\B2B\Model\ErpIntegration;
use GrupoAwamotos\B2B\Helper\Data as B2BHelper;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Message\ManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Observer to check ERP for existing customer data on B2B registration
 */
class ErpRegistrationCheckObserver implements ObserverInterface
{
    private ErpIntegration $erpIntegration;
    private B2BHelper $b2bHelper;
    private CustomerRepositoryInterface $customerRepository;
    private ManagerInterface $messageManager;
    private LoggerInterface $logger;

    public function __construct(
        ErpIntegration $erpIntegration,
        B2BHelper $b2bHelper,
        CustomerRepositoryInterface $customerRepository,
        ManagerInterface $messageManager,
        LoggerInterface $logger
    ) {
        $this->erpIntegration = $erpIntegration;
        $this->b2bHelper = $b2bHelper;
        $this->customerRepository = $customerRepository;
        $this->messageManager = $messageManager;
        $this->logger = $logger;
    }

    /**
     * Check if customer CNPJ exists in ERP and link if found
     */
    public function execute(Observer $observer): void
    {
        /** @var \Magento\Customer\Api\Data\CustomerInterface $customer */
        $customer = $observer->getEvent()->getCustomer();

        if (!$customer || !$customer->getId()) {
            return;
        }

        // Only process B2B customers (those with CNPJ)
        $cnpj = $this->getCustomerCnpj($customer);
        if (empty($cnpj)) {
            $this->logger->debug('ErpRegistrationCheckObserver: Customer has no CNPJ, skipping');
            return;
        }

        $this->logger->info(sprintf(
            'ErpRegistrationCheckObserver: Checking ERP for customer %s with CNPJ %s',
            $customer->getEmail(),
            $this->maskCnpj($cnpj)
        ));

        try {
            // Check if CNPJ exists in ERP
            $erpCustomer = $this->erpIntegration->findErpCustomerByCnpj($cnpj);

            if ($erpCustomer && !empty($erpCustomer['CODIGO'])) {
                $erpCode = $erpCustomer['CODIGO'];

                $this->logger->info(sprintf(
                    'ErpRegistrationCheckObserver: Found ERP customer %s for CNPJ',
                    $erpCode
                ));

                // Link customer to ERP
                $this->erpIntegration->linkCustomerToErp(
                    (int) $customer->getId(),
                    $erpCode
                );

                // Sync addresses from ERP if available
                $this->erpIntegration->syncAddressesFromErp(
                    (int) $customer->getId(),
                    $erpCode
                );

                // Update customer with ERP data
                $this->updateCustomerFromErp($customer, $erpCustomer);

                $this->messageManager->addSuccessMessage(
                    __('Seus dados foram encontrados em nosso sistema. Sua conta será analisada em breve.')
                );
            } else {
                $this->logger->debug('ErpRegistrationCheckObserver: CNPJ not found in ERP, new customer');
            }
        } catch (\Exception $e) {
            $this->logger->error(sprintf(
                'ErpRegistrationCheckObserver: Error checking ERP - %s',
                $e->getMessage()
            ));
            // Don't fail registration if ERP check fails
        }
    }

    /**
     * Get customer CNPJ from custom attribute
     */
    private function getCustomerCnpj($customer): ?string
    {
        $cnpjAttribute = $customer->getCustomAttribute('cnpj');
        if ($cnpjAttribute) {
            return $cnpjAttribute->getValue();
        }

        // Try taxvat as fallback
        $taxvat = $customer->getTaxvat();
        if ($taxvat && strlen(preg_replace('/\D/', '', $taxvat)) === 14) {
            return $taxvat;
        }

        return null;
    }

    /**
     * Update customer data from ERP information
     */
    private function updateCustomerFromErp($customer, array $erpData): void
    {
        $updated = false;

        // Update razao social if available
        if (!empty($erpData['RAZAO_SOCIAL'])) {
            $razaoAttribute = $customer->getCustomAttribute('b2b_razao_social');
            if (!$razaoAttribute || empty($razaoAttribute->getValue())) {
                $customer->setCustomAttribute('b2b_razao_social', $erpData['RAZAO_SOCIAL']);
                $updated = true;
            }
        }

        // Update inscricao estadual if available
        if (!empty($erpData['INSCRICAO_ESTADUAL'])) {
            $ieAttribute = $customer->getCustomAttribute('b2b_inscricao_estadual');
            if (!$ieAttribute || empty($ieAttribute->getValue())) {
                $customer->setCustomAttribute('b2b_inscricao_estadual', $erpData['INSCRICAO_ESTADUAL']);
                $updated = true;
            }
        }

        if ($updated) {
            try {
                $this->customerRepository->save($customer);
                $this->logger->info('ErpRegistrationCheckObserver: Updated customer with ERP data');
            } catch (\Exception $e) {
                $this->logger->warning(sprintf(
                    'ErpRegistrationCheckObserver: Failed to update customer - %s',
                    $e->getMessage()
                ));
            }
        }
    }

    /**
     * Mask CNPJ for logging (show only first and last 4 digits)
     */
    private function maskCnpj(string $cnpj): string
    {
        $clean = preg_replace('/\D/', '', $cnpj);
        if (strlen($clean) !== 14) {
            return '******';
        }
        return substr($clean, 0, 4) . '******' . substr($clean, -4);
    }
}
