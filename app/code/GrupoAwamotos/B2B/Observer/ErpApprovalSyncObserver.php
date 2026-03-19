<?php
declare(strict_types=1);

namespace GrupoAwamotos\B2B\Observer;

use GrupoAwamotos\B2B\Model\ErpIntegration;
use GrupoAwamotos\B2B\Helper\Data as B2BHelper;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Psr\Log\LoggerInterface;

/**
 * Observer to sync approved B2B customer to ERP
 */
class ErpApprovalSyncObserver implements ObserverInterface
{
    private ErpIntegration $erpIntegration;
    private B2BHelper $b2bHelper;
    private CustomerRepositoryInterface $customerRepository;
    private LoggerInterface $logger;

    public function __construct(
        ErpIntegration $erpIntegration,
        B2BHelper $b2bHelper,
        CustomerRepositoryInterface $customerRepository,
        LoggerInterface $logger
    ) {
        $this->erpIntegration = $erpIntegration;
        $this->b2bHelper = $b2bHelper;
        $this->customerRepository = $customerRepository;
        $this->logger = $logger;
    }

    /**
     * Sync approved B2B customer to ERP
     */
    public function execute(Observer $observer): void
    {
        $customerId = $observer->getEvent()->getCustomerId();
        $newGroupId = $observer->getEvent()->getNewGroupId();

        if (!$customerId) {
            $this->logger->warning('ErpApprovalSyncObserver: No customer ID provided');
            return;
        }

        $this->logger->info(sprintf(
            'ErpApprovalSyncObserver: Processing approval for customer %d to group %s',
            $customerId,
            $newGroupId ?? 'unknown'
        ));

        try {
            $customer = $this->customerRepository->getById($customerId);

            // Check if customer has CNPJ (is B2B)
            $cnpj = $this->getCustomerCnpj($customer);
            if (empty($cnpj)) {
                $this->logger->debug('ErpApprovalSyncObserver: Customer has no CNPJ, skipping ERP sync');
                return;
            }

            // Check if already linked to ERP
            $erpCode = $this->erpIntegration->getErpCodeForCustomer((int) $customerId);

            if ($erpCode) {
                $erpCodeStr = (string) $erpCode;
                $this->logger->info(sprintf(
                    'ErpApprovalSyncObserver: Customer already linked to ERP code %s',
                    $erpCodeStr
                ));
                // Update existing ERP customer
                $this->updateErpCustomer($customer, $erpCodeStr, $newGroupId);
            } else {
                // Create new customer in ERP
                $this->logger->info('ErpApprovalSyncObserver: Creating new customer in ERP');
                $this->erpIntegration->syncApprovedCustomerToErp((int) $customerId);
            }

            // Sync credit limit from ERP if available
            $this->syncCreditLimit($customer);

        } catch (\Exception $e) {
            $this->logger->error(sprintf(
                'ErpApprovalSyncObserver: Error syncing customer %d to ERP - %s',
                $customerId,
                $e->getMessage()
            ));
            // Don't fail approval if ERP sync fails
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
     * Update existing ERP customer with approval info
     */
    private function updateErpCustomer($customer, string $erpCode, ?int $newGroupId): void
    {
        try {
            // Map Magento group to ERP customer type
            $erpCustomerType = $this->mapGroupToErpType($newGroupId);

            $this->logger->debug(sprintf(
                'ErpApprovalSyncObserver: Updating ERP customer %s to type %s',
                $erpCode,
                $erpCustomerType
            ));

            // The actual ERP update would be done through ERPIntegration module
            // This is a placeholder for the integration logic
        } catch (\Exception $e) {
            $this->logger->warning(sprintf(
                'ErpApprovalSyncObserver: Failed to update ERP customer - %s',
                $e->getMessage()
            ));
        }
    }

    /**
     * Map Magento customer group to ERP customer type
     */
    private function mapGroupToErpType(?int $groupId): string
    {
        // Get group codes from B2B module configuration
        $groupMapping = [
            $this->b2bHelper->getGroupIdByCode('b2b_atacado') => 'ATACADO',
            $this->b2bHelper->getGroupIdByCode('b2b_vip') => 'VIP',
            $this->b2bHelper->getGroupIdByCode('b2b_revendedor') => 'REVENDEDOR',
        ];

        return $groupMapping[$groupId] ?? 'ATACADO';
    }

    /**
     * Sync credit limit from ERP to customer
     */
    private function syncCreditLimit($customer): void
    {
        try {
            $customerId = (int) $customer->getId();
            $erpCode = $this->erpIntegration->getErpCodeForCustomer($customerId);

            if (!$erpCode) {
                return;
            }

            // Get credit limit from ERP
            $creditLimit = $this->erpIntegration->getCreditLimitFromErp((string) $erpCode);

            if ($creditLimit !== null && $creditLimit > 0) {
                // Save credit limit to customer attribute
                $customer->setCustomAttribute('credit_limit', $creditLimit);
                $this->customerRepository->save($customer);

                $this->logger->info(sprintf(
                    'ErpApprovalSyncObserver: Set credit limit %.2f for customer %d',
                    $creditLimit,
                    $customerId
                ));
            }
        } catch (\Exception $e) {
            $this->logger->warning(sprintf(
                'ErpApprovalSyncObserver: Failed to sync credit limit - %s',
                $e->getMessage()
            ));
        }
    }
}
