<?php
/**
 * ERP Integration Service for B2B Module
 * Connects B2B customers with ERP system
 */
declare(strict_types=1);

namespace GrupoAwamotos\B2B\Model;

use GrupoAwamotos\ERPIntegration\Model\CustomerSync;
use GrupoAwamotos\ERPIntegration\Helper\Data as ErpHelper;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Psr\Log\LoggerInterface;

class ErpIntegration
{
    private CustomerSync $customerSync;
    private ErpHelper $erpHelper;
    private CustomerRepositoryInterface $customerRepository;
    private LoggerInterface $logger;

    public function __construct(
        CustomerSync $customerSync,
        ErpHelper $erpHelper,
        CustomerRepositoryInterface $customerRepository,
        LoggerInterface $logger
    ) {
        $this->customerSync = $customerSync;
        $this->erpHelper = $erpHelper;
        $this->customerRepository = $customerRepository;
        $this->logger = $logger;
    }

    /**
     * Check if CNPJ exists in ERP and return customer data
     *
     * @param string $cnpj
     * @return array|null
     */
    public function findErpCustomerByCnpj(string $cnpj): ?array
    {
        if (!$this->erpHelper->isEnabled()) {
            return null;
        }

        try {
            $cleanCnpj = preg_replace('/\D/', '', $cnpj);
            return $this->customerSync->getErpCustomerByTaxvat($cleanCnpj);
        } catch (\Exception $e) {
            $this->logger->error('[B2B-ERP] Error finding customer by CNPJ: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Link Magento B2B customer to ERP customer
     *
     * @param int $magentoCustomerId
     * @param string|int $erpCustomerCode
     * @return bool
     */
    public function linkCustomerToErp(int $magentoCustomerId, $erpCustomerCode): bool
    {
        if (!$this->erpHelper->isEnabled()) {
            return false;
        }

        try {
            $result = $this->customerSync->linkMagentoToErp($magentoCustomerId, $erpCustomerCode);

            if ($result) {
                // Update customer attribute with ERP code
                $customer = $this->customerRepository->getById($magentoCustomerId);
                $customer->setCustomAttribute('erp_code', $erpCustomerCode);
                $this->customerRepository->save($customer);

                $this->logger->info(sprintf(
                    '[B2B-ERP] Customer #%d linked to ERP code #%d',
                    $magentoCustomerId,
                    $erpCustomerCode
                ));
            }

            return $result;
        } catch (\Exception $e) {
            $this->logger->error('[B2B-ERP] Error linking customer to ERP: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Sync approved B2B customer to ERP
     * Creates or updates customer in ERP system
     *
     * @param int $customerId
     * @return array Result with success status and message
     */
    public function syncApprovedCustomerToErp(int $customerId): array
    {
        $result = [
            'success' => false,
            'erp_code' => null,
            'message' => '',
            'action' => null,
        ];

        if (!$this->erpHelper->isEnabled()) {
            $result['message'] = 'ERP integration is disabled';
            return $result;
        }

        try {
            $customer = $this->customerRepository->getById($customerId);
            $cnpj = $this->getCustomerAttribute($customer, 'b2b_cnpj')
                ?? $this->getCustomerAttribute($customer, 'cnpj')
                ?? $customer->getTaxvat();

            if (empty($cnpj)) {
                $result['message'] = 'Customer has no CNPJ';
                return $result;
            }

            // Check if customer already exists in ERP
            $erpCustomer = $this->findErpCustomerByCnpj($cnpj);

            if ($erpCustomer) {
                // Customer exists in ERP - just link
                $erpCode = (int) $erpCustomer['CODIGO'];
                $this->linkCustomerToErp($customerId, $erpCode);

                $result['success'] = true;
                $result['erp_code'] = $erpCode;
                $result['action'] = 'linked';
                $result['message'] = sprintf('Customer linked to existing ERP customer #%d', $erpCode);

                $this->logger->info(sprintf(
                    '[B2B-ERP] B2B customer #%d linked to existing ERP #%d (CNPJ: %s)',
                    $customerId,
                    $erpCode,
                    $cnpj
                ));
            } else {
                // Customer doesn't exist in ERP - needs manual creation
                // ERP system typically requires manual customer creation for legal compliance
                $result['success'] = true;
                $result['action'] = 'pending_erp_creation';
                $result['message'] = 'Customer approved but not found in ERP. Manual ERP registration required.';

                $this->logger->info(sprintf(
                    '[B2B-ERP] B2B customer #%d approved but not found in ERP (CNPJ: %s). Manual registration needed.',
                    $customerId,
                    $cnpj
                ));
            }

            return $result;
        } catch (\Exception $e) {
            $result['message'] = 'Error syncing to ERP: ' . $e->getMessage();
            $this->logger->error('[B2B-ERP] Sync error: ' . $e->getMessage());
            return $result;
        }
    }

    /**
     * Check B2B registration against ERP and auto-link if found
     *
     * @param CustomerInterface $customer
     * @return array Result with ERP status
     */
    public function checkRegistrationAgainstErp(CustomerInterface $customer): array
    {
        $result = [
            'found_in_erp' => false,
            'erp_code' => null,
            'erp_data' => null,
            'linked' => false,
            'message' => '',
        ];

        if (!$this->erpHelper->isEnabled()) {
            $result['message'] = 'ERP integration disabled';
            return $result;
        }

        try {
            $cnpj = $this->getCustomerAttribute($customer, 'b2b_cnpj');

            if (empty($cnpj)) {
                $result['message'] = 'No CNPJ provided';
                return $result;
            }

            $erpCustomer = $this->findErpCustomerByCnpj($cnpj);

            if ($erpCustomer) {
                $result['found_in_erp'] = true;
                $result['erp_code'] = (int) $erpCustomer['CODIGO'];
                $result['erp_data'] = [
                    'razao_social' => $erpCustomer['RAZAO'] ?? '',
                    'fantasia' => $erpCustomer['FANTASIA'] ?? '',
                    'email' => $erpCustomer['EMAIL'] ?? '',
                    'cidade' => $erpCustomer['CIDADE'] ?? '',
                    'uf' => $erpCustomer['UF'] ?? '',
                ];

                // Auto-link the customer
                $customerId = (int) $customer->getId();
                if ($customerId > 0) {
                    $this->linkCustomerToErp($customerId, $result['erp_code']);
                    $result['linked'] = true;
                    $result['message'] = sprintf(
                        'Cliente encontrado no ERP: %s (Código: %d)',
                        $erpCustomer['RAZAO'] ?? $erpCustomer['FANTASIA'] ?? 'N/A',
                        $result['erp_code']
                    );
                }

                $this->logger->info(sprintf(
                    '[B2B-ERP] B2B registration found existing ERP customer #%d for CNPJ %s',
                    $result['erp_code'],
                    $cnpj
                ));
            } else {
                $result['message'] = 'Cliente não encontrado no ERP. Será necessário cadastro manual.';
            }

            return $result;
        } catch (\Exception $e) {
            $result['message'] = 'Error checking ERP: ' . $e->getMessage();
            $this->logger->error('[B2B-ERP] Registration check error: ' . $e->getMessage());
            return $result;
        }
    }

    /**
     * Get ERP customer code for a Magento customer
     *
     * @param int $customerId
     * @return int|null
     */
    public function getErpCodeForCustomer(int $customerId): ?int
    {
        try {
            $erpCode = $this->customerSync->getErpCodeByCustomerId($customerId);
            return $erpCode ? (int) $erpCode : null;
        } catch (\Exception $e) {
            $this->logger->debug('[B2B ErpIntegration] getErpCodeForCustomer failed for #' . $customerId . ': ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Sync customer addresses from ERP
     *
     * @param int $magentoCustomerId
     * @param int $erpCode
     * @return bool
     */
    public function syncAddressesFromErp(int $magentoCustomerId, int $erpCode): bool
    {
        if (!$this->erpHelper->isEnabled()) {
            return false;
        }

        try {
            return $this->customerSync->syncCustomerAddresses($magentoCustomerId, $erpCode);
        } catch (\Exception $e) {
            $this->logger->error('[B2B-ERP] Address sync error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get credit limit from ERP for a customer
     *
     * @param string $erpCode
     * @return float|null
     */
    public function getCreditLimitFromErp(string $erpCode): ?float
    {
        if (!$this->erpHelper->isEnabled()) {
            return null;
        }

        try {
            $creditData = $this->customerSync->getCustomerCreditFromErp($erpCode);

            if ($creditData && isset($creditData['LIMITE_CREDITO'])) {
                return (float) $creditData['LIMITE_CREDITO'];
            }

            return null;
        } catch (\Exception $e) {
            $this->logger->error('[B2B-ERP] Error getting credit limit: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get customer attribute value
     *
     * @param CustomerInterface $customer
     * @param string $attributeCode
     * @return string|null
     */
    private function getCustomerAttribute(CustomerInterface $customer, string $attributeCode): ?string
    {
        $attribute = $customer->getCustomAttribute($attributeCode);
        return $attribute ? (string) $attribute->getValue() : null;
    }
}
