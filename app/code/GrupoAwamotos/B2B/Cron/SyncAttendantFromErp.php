<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Cron;

use GrupoAwamotos\B2B\Model\Attendant\AttendantManager;
use Magento\Framework\App\ResourceConnection;
use Psr\Log\LoggerInterface;

/**
 * Syncs customer-attendant assignments from ERP Sectra VENDPREF field.
 *
 * Runs daily. For each customer with an erp_code, looks up VENDPREF
 * in the ERP, matches to an attendant by erp_seller_code, and creates
 * the assignment if not already present.
 */
class SyncAttendantFromErp
{
    private ResourceConnection $resource;
    private AttendantManager $attendantManager;
    private LoggerInterface $logger;

    public function __construct(
        ResourceConnection $resource,
        AttendantManager $attendantManager,
        LoggerInterface $logger
    ) {
        $this->resource = $resource;
        $this->attendantManager = $attendantManager;
        $this->logger = $logger;
    }

    /**
     * Execute the sync
     */
    public function execute(): void
    {
        $this->logger->info('[B2B AttendantSync] Starting ERP attendant sync');

        $connection = $this->resource->getConnection();

        // Get all attendants indexed by erp_seller_code
        $attendants = $connection->fetchAll(
            $connection->select()
                ->from($this->resource->getTableName('grupoawamotos_b2b_attendants'))
                ->where('is_active = ?', 1)
                ->where('erp_seller_code IS NOT NULL')
        );

        $attendantByErpCode = [];
        foreach ($attendants as $att) {
            $attendantByErpCode[(int) $att['erp_seller_code']] = $att;
        }

        if (empty($attendantByErpCode)) {
            $this->logger->info('[B2B AttendantSync] No attendants with ERP codes found');
            return;
        }

        // Get the erp_code attribute ID
        $erpCodeAttrId = $connection->fetchOne(
            $connection->select()
                ->from($this->resource->getTableName('eav_attribute'), ['attribute_id'])
                ->where('attribute_code = ?', 'erp_code')
                ->where('entity_type_id = ?', 1) // customer
        );

        if (!$erpCodeAttrId) {
            $this->logger->warning('[B2B AttendantSync] erp_code attribute not found');
            return;
        }

        // Get all customers with ERP codes that DON'T have an attendant yet
        $select = $connection->select()
            ->from(
                ['cev' => $this->resource->getTableName('customer_entity_varchar')],
                ['entity_id', 'value']
            )
            ->joinLeft(
                ['ca' => $this->resource->getTableName('grupoawamotos_b2b_customer_attendant')],
                'cev.entity_id = ca.customer_id',
                []
            )
            ->where('cev.attribute_id = ?', $erpCodeAttrId)
            ->where('cev.value IS NOT NULL')
            ->where('cev.value != ?', '')
            ->where('ca.customer_id IS NULL'); // Not yet assigned

        $customers = $connection->fetchAll($select);
        $this->logger->info('[B2B AttendantSync] Found ' . count($customers) . ' unassigned customers with ERP codes');

        $assigned = 0;
        $noMatch = 0;

        // Check if we have an ERP connection to look up VENDPREF
        try {
            $erpConnection = \Magento\Framework\App\ObjectManager::getInstance()
                ->get(\GrupoAwamotos\ERPIntegration\Api\ConnectionInterface::class);

            foreach (array_chunk($customers, 50) as $chunk) {
                $erpCodes = array_column($chunk, 'value');
                $placeholders = implode(',', array_fill(0, count($erpCodes), '?'));

                $erpData = $erpConnection->query(
                    "SELECT f.CODIGO, f.VENDPREF
                     FROM dbo.FN_FORNECEDORES f
                     WHERE f.CKCLIENTE = 'S' AND f.CODIGO IN ($placeholders)",
                    $erpCodes
                );

                $vendMap = [];
                foreach ($erpData as $row) {
                    $vendMap[(string) $row['CODIGO']] = (int) ($row['VENDPREF'] ?? 0);
                }

                foreach ($chunk as $customer) {
                    $customerId = (int) $customer['entity_id'];
                    $erpCode = (string) $customer['value'];
                    $vendPref = $vendMap[$erpCode] ?? 0;

                    if ($vendPref > 0 && isset($attendantByErpCode[$vendPref])) {
                        $attendantId = (int) $attendantByErpCode[$vendPref]['attendant_id'];
                        $this->attendantManager->assignCustomerToAttendant(
                            $customerId,
                            $attendantId,
                            'Sync ERP VENDPREF=' . $vendPref
                        );
                        $assigned++;
                    } else {
                        $noMatch++;
                    }
                }
            }
        } catch (\Exception $e) {
            $this->logger->error('[B2B AttendantSync] ERP connection error: ' . $e->getMessage());
        }

        $this->logger->info("[B2B AttendantSync] Done: {$assigned} assigned, {$noMatch} no match");
    }
}
