<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Observer;

use GrupoAwamotos\B2B\Model\Attendant\AttendantManager;
use GrupoAwamotos\ERPIntegration\Api\ConnectionInterface as ErpConnectionInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Psr\Log\LoggerInterface;

/**
 * On customer B2B approval: immediately look up VENDPREF in ERP and assign
 * the correct attendant — no need to wait for the 3am cron.
 *
 * Listens to: grupoawamotos_b2b_customer_approved
 *
 * Fallback chain:
 *   1. Has erp_code + ERP reachable + VENDPREF found + attendant exists → assign from ERP
 *   2. Otherwise → auto-assign (round-robin, least-loaded)
 */
class AttendantAssignOnApprovalObserver implements ObserverInterface
{
    private ErpConnectionInterface $erpConnection;
    private AttendantManager $attendantManager;
    private CustomerRepositoryInterface $customerRepository;
    private ResourceConnection $resource;
    private LoggerInterface $logger;

    public function __construct(
        ErpConnectionInterface $erpConnection,
        AttendantManager $attendantManager,
        CustomerRepositoryInterface $customerRepository,
        ResourceConnection $resource,
        LoggerInterface $logger
    ) {
        $this->erpConnection      = $erpConnection;
        $this->attendantManager   = $attendantManager;
        $this->customerRepository = $customerRepository;
        $this->resource           = $resource;
        $this->logger             = $logger;
    }

    public function execute(Observer $observer): void
    {
        $customerId = (int) $observer->getEvent()->getCustomerId();
        if (!$customerId) {
            return;
        }

        try {
            $this->assignAttendant($customerId);
        } catch (\Exception $e) {
            // Never block the approval flow
            $this->logger->error(sprintf(
                '[B2B AttendantAssign] Error for customer #%d: %s',
                $customerId,
                $e->getMessage()
            ));
        }
    }

    private function assignAttendant(int $customerId): void
    {
        // If already has attendant, let the ERP cron handle re-assignments
        $existing = $this->attendantManager->getCustomerAttendant($customerId);
        if ($existing) {
            return;
        }

        // Get the customer's ERP code attribute
        $customer = $this->customerRepository->getById($customerId);
        $erpAttr  = $customer->getCustomAttribute('erp_code');
        $erpCode  = $erpAttr ? trim((string) $erpAttr->getValue()) : '';

        if (empty($erpCode)) {
            $this->logger->info(sprintf(
                '[B2B AttendantAssign] Customer #%d has no erp_code, falling back to auto-assign',
                $customerId
            ));
            $this->attendantManager->autoAssignCustomer($customerId);
            return;
        }

        if (!$this->erpConnection->hasAvailableDriver()) {
            $this->logger->warning('[B2B AttendantAssign] ERP driver unavailable, falling back to auto-assign');
            $this->attendantManager->autoAssignCustomer($customerId);
            return;
        }

        // Fetch VENDPREF from ERP
        $row = $this->erpConnection->fetchOne(
            "SELECT f.VENDPREF FROM dbo.FN_FORNECEDORES f WHERE f.CKCLIENTE = 'S' AND f.CODIGO = ?",
            [$erpCode]
        );

        $vendPref = $row ? (int) ($row['VENDPREF'] ?? 0) : 0;

        if ($vendPref <= 0) {
            $this->logger->info(sprintf(
                '[B2B AttendantAssign] Customer #%d (erp=%s) has no VENDPREF, falling back to auto-assign',
                $customerId,
                $erpCode
            ));
            $this->attendantManager->autoAssignCustomer($customerId);
            return;
        }

        // Find matching attendant by erp_seller_code
        $connection = $this->resource->getConnection();
        $attendant  = $connection->fetchRow(
            $connection->select()
                ->from($this->resource->getTableName('grupoawamotos_b2b_attendants'))
                ->where('erp_seller_code = ?', $vendPref)
                ->where('is_active = ?', 1)
        );

        if (!$attendant) {
            $this->logger->warning(sprintf(
                '[B2B AttendantAssign] No attendant for VENDPREF=%d (customer #%d), falling back to auto-assign',
                $vendPref,
                $customerId
            ));
            $this->attendantManager->autoAssignCustomer($customerId);
            return;
        }

        $this->attendantManager->assignCustomerToAttendant(
            $customerId,
            (int) $attendant['attendant_id'],
            sprintf('Aprovacao B2B — VENDPREF=%d ERP=%s', $vendPref, $erpCode)
        );

        $this->logger->info(sprintf(
            '[B2B AttendantAssign] Customer #%d assigned to "%s" (VENDPREF=%d)',
            $customerId,
            $attendant['name'],
            $vendPref
        ));
    }
}
