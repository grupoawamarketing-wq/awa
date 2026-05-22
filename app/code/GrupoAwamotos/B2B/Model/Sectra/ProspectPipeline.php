<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Model\Sectra;

use GrupoAwamotos\B2B\Model\Customer\Attribute\Source\ApprovalStatus;
use GrupoAwamotos\B2B\Model\Customer\Attribute\Source\ErpCustomerSyncStatus;
use GrupoAwamotos\B2B\Model\CustomerCnpjResolver;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Framework\App\ResourceConnection;
use Psr\Log\LoggerInterface;

/**
 * Passive B2B prospect registration in Magento bridge tables (no Sectra write/import).
 */
class ProspectPipeline
{
    private const OC_CUSTOMER_ID_OFFSET = 200000;
    private const B2B_GROUP_IDS = [4, 5, 6];

    /** Max customers polled per cron run — avoids 7000+ sequential ERP queries. */
    private const POLL_BATCH_SIZE = 50;

    public function __construct(
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly CustomerCnpjResolver $cnpjResolver,
        private readonly ValidatorChecker $validatorChecker,
        private readonly SectraSyncLogger $syncLogger,
        private readonly ResourceConnection $resourceConnection,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @return array{
     *     success: bool,
     *     customer_id: int,
     *     sectra_chave: int|null,
     *     cnpj: string|null,
     *     erp_customer_sync_status: string,
     *     message: string
     * }
     */
    public function processApprovedCustomer(int $customerId): array
    {
        $result = [
            'success' => false,
            'customer_id' => $customerId,
            'sectra_chave' => null,
            'cnpj' => null,
            'erp_customer_sync_status' => '',
            'message' => '',
        ];

        try {
            $customer = $this->customerRepository->getById($customerId);
        } catch (\Exception $e) {
            $result['message'] = 'Cliente não encontrado.';
            return $result;
        }

        $statusAttr = $customer->getCustomAttribute('b2b_approval_status');
        if ($statusAttr === null || (string) $statusAttr->getValue() !== ApprovalStatus::STATUS_APPROVED) {
            $result['message'] = 'Cliente não está aprovado comercialmente.';
            return $result;
        }

        if (!in_array((int) $customer->getGroupId(), self::B2B_GROUP_IDS, true)) {
            $result['message'] = 'Cliente não pertence a grupo B2B.';
            return $result;
        }

        $resolved = $this->cnpjResolver->resolveWithSource($customer);
        if ($resolved === null) {
            $result['message'] = 'CNPJ ausente — prospect não enviado ao Sectra.';
            $this->setCustomerSyncStatus($customer, ErpCustomerSyncStatus::PROSPECT_MAGENTO);
            $result['erp_customer_sync_status'] = ErpCustomerSyncStatus::PROSPECT_MAGENTO;
            $result['success'] = true;
            return $result;
        }

        $cnpj = $resolved['digits'];
        $result['cnpj'] = $cnpj;

        if (!$this->cnpjResolver->isValidCnpj($cnpj)) {
            $result['message'] = 'CNPJ inválido — corrija antes de enviar ao Sectra.';
            $this->setCustomerSyncStatus($customer, ErpCustomerSyncStatus::PROSPECT_MAGENTO);
            $result['erp_customer_sync_status'] = ErpCustomerSyncStatus::PROSPECT_MAGENTO;
            $result['success'] = true;
            return $result;
        }

        $duplicateId = $this->findMagentoCustomerByCnpj($cnpj, $customerId);
        if ($duplicateId !== null) {
            $result['message'] = sprintf(
                'CNPJ já vinculado ao cliente Magento #%d — evitando duplicidade.',
                $duplicateId
            );
            $this->syncLogger->log(
                ProspectEvent::CUSTOMER_VALIDATION_PENDING,
                $result['message'],
                $customerId,
                null,
                $cnpj,
                null,
                'error'
            );
            return $result;
        }

        $this->ensureCustomerIdMap($customerId, $cnpj);
        $sectraChave = $this->validatorChecker->resolveSectraChave($customerId);
        $result['sectra_chave'] = $sectraChave;

        $this->syncLogger->log(
            ProspectEvent::CUSTOMER_CREATED_MAGENTO,
            sprintf('Cliente B2B #%d mapeado como prospect (CHAVE Sectra %d).', $customerId, $sectraChave),
            $customerId,
            null,
            $cnpj,
            $sectraChave
        );

        if ($this->validatorChecker->isCustomerValidatedInSectra($customerId)) {
            $this->setCustomerSyncStatus($customer, ErpCustomerSyncStatus::CUSTOMER_VALIDATED_IN_ERP);
            $result['erp_customer_sync_status'] = ErpCustomerSyncStatus::CUSTOMER_VALIDATED_IN_ERP;
            $result['message'] = 'Cliente validado no ERP — checkout liberado.';
            $result['success'] = true;

            $this->syncLogger->log(
                ProspectEvent::CUSTOMER_VALIDATOR_ACCEPTED,
                'Cliente confirmado em oc_customer_b2b_confirmed.',
                $customerId,
                null,
                $cnpj,
                $sectraChave,
                'success'
            );

            return $result;
        }

        if ($this->validatorChecker->isRegisteredInErpValidator($customerId)) {
            $result['message'] = 'Cliente no validador ERP — aguardando sincronização do bridge Magento.';
            $result['success'] = true;
            return $result;
        }

        $preRegSynced = $this->syncCustomerToPreRegistration($customerId);
        if ($preRegSynced) {
            $this->syncLogger->log(
                ProspectEvent::CUSTOMER_SENT_SECTRA,
                'Prospect registrado em oc_pre_registration (aguardando validação passiva no ERP).',
                $customerId,
                null,
                $cnpj,
                $sectraChave
            );
        }

        $this->setCustomerSyncStatus($customer, ErpCustomerSyncStatus::CUSTOMER_PENDING_ERP_VALIDATION);
        $result['erp_customer_sync_status'] = ErpCustomerSyncStatus::CUSTOMER_PENDING_ERP_VALIDATION;
        $result['message'] = 'Cadastro B2B aguardando validação no ERP. Checkout bloqueado até confirmação.';
        $result['success'] = true;

        $this->syncLogger->log(
            ProspectEvent::CUSTOMER_VALIDATION_PENDING,
            $result['message'],
            $customerId,
            null,
            $cnpj,
            $sectraChave
        );

        return $result;
    }

    /**
     * Poll Sectra validador for customers awaiting validation.
     *
     * @return array{validated: int, still_pending: int}
     */
    public function pollPendingValidations(): array
    {
        $counts = ['validated' => 0, 'still_pending' => 0];
        $customerIds = $this->getCustomersAwaitingValidation(self::POLL_BATCH_SIZE);

        foreach ($customerIds as $customerId) {
            if ($this->validatorChecker->isCustomerValidatedInSectra($customerId)) {
                try {
                    $customer = $this->customerRepository->getById($customerId);
                } catch (\Exception) {
                    $counts['still_pending']++;
                    continue;
                }

                $cnpj = $this->cnpjResolver->resolveDigits($customer);
                $sectraChave = $this->validatorChecker->resolveSectraChave($customerId);
                $this->setCustomerSyncStatus($customer, ErpCustomerSyncStatus::CUSTOMER_VALIDATED_IN_ERP);
                $this->syncLogger->log(
                    ProspectEvent::CUSTOMER_CONFIRMED_BY_ERP_POLL,
                    'Validação confirmada localmente — oc_customer_b2b_confirmed.',
                    $customerId,
                    null,
                    $cnpj,
                    $sectraChave,
                    'success'
                );
                $counts['validated']++;
                continue;
            }

            if (!$this->validatorChecker->isRegisteredInErpValidator($customerId)) {
                $counts['still_pending']++;
                continue;
            }

            // Validador ERP OK but bridge local ainda não confirmou — OpenCart bridge cron resolve.
            $counts['still_pending']++;
        }

        if ($counts['validated'] > 0 || $counts['still_pending'] > 0) {
            $this->logger->info('[B2B-Sectra] Poll batch concluído', [
                'batch_size' => count($customerIds),
                'validated' => $counts['validated'],
                'still_pending' => $counts['still_pending'],
            ]);
        }

        return $counts;
    }

    /**
     * @return int[]
     */
    private function getCustomersAwaitingValidation(int $limit = self::POLL_BATCH_SIZE): array
    {
        $connection = $this->resourceConnection->getConnection();
        $statusAttrId = $connection->fetchOne(
            "SELECT attribute_id FROM eav_attribute ea
             INNER JOIN eav_entity_type et ON et.entity_type_id = ea.entity_type_id
             WHERE ea.attribute_code = 'erp_customer_sync_status'
               AND et.entity_type_code = 'customer'"
        );

        if (!$statusAttrId) {
            return [];
        }

        $batchLimit = max(1, min(200, $limit));

        return array_map(
            'intval',
            $connection->fetchCol(
                'SELECT entity_id FROM customer_entity_varchar
                 WHERE attribute_id = ? AND value IN (?, ?)
                 ORDER BY entity_id DESC
                 LIMIT ' . $batchLimit,
                [
                    $statusAttrId,
                    ErpCustomerSyncStatus::CUSTOMER_PENDING_ERP_VALIDATION,
                    ErpCustomerSyncStatus::AWAITING_ERP_VALIDATION,
                ]
            )
        );
    }

    private function ensureCustomerIdMap(int $customerId, string $cnpjDigits): void
    {
        $connection = $this->resourceConnection->getConnection();
        $existing = $connection->fetchOne(
            'SELECT old_oc_customer_id FROM oc_customer_id_map WHERE magento_customer_id = ?',
            [$customerId]
        );

        if ($existing !== false) {
            $connection->update(
                'oc_customer_id_map',
                ['old_cnpj' => $cnpjDigits],
                ['magento_customer_id = ?' => $customerId]
            );
            return;
        }

        $connection->insert(
            'oc_customer_id_map',
            [
                'old_oc_customer_id' => $customerId + self::OC_CUSTOMER_ID_OFFSET,
                'old_email' => '',
                'old_cnpj' => $cnpjDigits,
                'magento_customer_id' => $customerId,
            ]
        );
    }

    private function syncCustomerToPreRegistration(int $customerId): bool
    {
        $connection = $this->resourceConnection->getConnection();
        $attrCnpj = 143;
        $attrRazao = 144;

        $sql = "
            INSERT INTO oc_pre_registration (
                customer_id, customer_group_id, store_id, language_id,
                firstname, lastname, email, telephone, fax,
                password, salt, cart, wishlist, newsletter, address_id,
                custom_field, ip, status, safe, token, code, date_added
            )
            SELECT
                map.old_oc_customer_id AS customer_id,
                2 AS customer_group_id,
                ce.store_id,
                2 AS language_id,
                COALESCE(ce.firstname, '') AS firstname,
                COALESCE(ce.lastname, '') AS lastname,
                COALESCE(ce.email, '') AS email,
                COALESCE(
                    REPLACE(REPLACE(REPLACE(REPLACE(ca.telephone,'(',''),')',''),'-',''),' ',''),
                    ''
                ) AS telephone,
                '' AS fax, '' AS password, '' AS salt,
                NULL AS cart, NULL AS wishlist, 0 AS newsletter, 0 AS address_id,
                CONCAT(
                    '{\"6\":\"',
                    REPLACE(REPLACE(REPLACE(REPLACE(
                        COALESCE(
                            (SELECT value FROM customer_entity_varchar
                             WHERE entity_id = ce.entity_id AND attribute_id = :attr_cnpj LIMIT 1),
                            ce.taxvat, ''
                        ), '.',''),'/',''),'-',''),' ',''),
                    '\",\"2\":\"\",\"3\":\"\",\"1\":\"',
                    REPLACE(
                        COALESCE(
                            (SELECT value FROM customer_entity_varchar
                             WHERE entity_id = ce.entity_id AND attribute_id = :attr_razao LIMIT 1),
                            CONCAT(COALESCE(ce.firstname,''), ' ', COALESCE(ce.lastname,''))
                        ),
                    '\"', '\\\\\"'),
                    '\"}'
                ) AS custom_field,
                '' AS ip, 1 AS status, 1 AS safe, '' AS token, '' AS code,
                ce.created_at AS date_added
            FROM oc_customer_id_map map
            INNER JOIN customer_entity ce ON ce.entity_id = map.magento_customer_id
            LEFT JOIN (
                SELECT parent_id, MIN(entity_id) AS entity_id
                FROM customer_address_entity GROUP BY parent_id
            ) first_addr ON first_addr.parent_id = ce.entity_id
            LEFT JOIN customer_address_entity ca ON ca.entity_id = first_addr.entity_id
            WHERE map.magento_customer_id = :customer_id
            ON DUPLICATE KEY UPDATE
                firstname = VALUES(firstname),
                lastname = VALUES(lastname),
                email = VALUES(email),
                telephone = VALUES(telephone),
                custom_field = VALUES(custom_field)
        ";

        try {
            $connection->query($sql, [
                'attr_cnpj' => $attrCnpj,
                'attr_razao' => $attrRazao,
                'customer_id' => $customerId,
            ]);
        } catch (\Exception $e) {
            $this->logger->warning('[B2B-Sectra] Falha ao sincronizar oc_pre_registration: ' . $e->getMessage());
            return false;
        }

        $sectraChave = $this->validatorChecker->resolveSectraChave($customerId);
        $exists = $connection->fetchOne(
            'SELECT customer_id FROM oc_pre_registration WHERE customer_id = ?',
            [$sectraChave]
        );

        return $exists !== false;
    }

    private function findMagentoCustomerByCnpj(string $cnpjDigits, int $excludeCustomerId): ?int
    {
        $connection = $this->resourceConnection->getConnection();

        $fromMap = $connection->fetchOne(
            'SELECT magento_customer_id FROM oc_customer_id_map
             WHERE old_cnpj = ? AND magento_customer_id != ? LIMIT 1',
            [$cnpjDigits, $excludeCustomerId]
        );
        if ($fromMap !== false) {
            return (int) $fromMap;
        }

        $b2bAttrId = $connection->fetchOne(
            "SELECT attribute_id FROM eav_attribute ea
             INNER JOIN eav_entity_type et ON et.entity_type_id = ea.entity_type_id
             WHERE ea.attribute_code = 'b2b_cnpj' AND et.entity_type_code = 'customer'"
        );
        if ($b2bAttrId) {
            $fromEav = $connection->fetchOne(
                'SELECT entity_id FROM customer_entity_varchar
                 WHERE attribute_id = ? AND REPLACE(REPLACE(REPLACE(value, ".", ""), "/", ""), "-", "") = ?
                   AND entity_id != ? LIMIT 1',
                [$b2bAttrId, $cnpjDigits, $excludeCustomerId]
            );
            if ($fromEav !== false) {
                return (int) $fromEav;
            }
        }

        return null;
    }

    private function setCustomerSyncStatus(CustomerInterface $customer, string $status): void
    {
        try {
            $customer->setCustomAttribute('erp_customer_sync_status', $status);
            $this->customerRepository->save($customer);
        } catch (\Exception $e) {
            $this->logger->warning(sprintf(
                '[B2B-Sectra] Customer #%d: falha ao gravar erp_customer_sync_status — %s',
                $customer->getId(),
                $e->getMessage()
            ));
        }
    }
}
