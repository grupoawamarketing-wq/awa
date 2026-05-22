<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Model\Registration;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Filesystem;

/**
 * Read-only audit of B2B customers with CNPJ and missing registration data.
 */
class RegistrationMissingDataAuditService
{
    /** @var array<string, int>|null */
    private ?array $attributeIds = null;

    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly Filesystem $filesystem
    ) {
    }

    /**
     * @return array{
     *     total_with_cnpj: int,
     *     no_phone: int,
     *     no_razao_social: int,
     *     no_address_phone: int,
     *     no_address_company: int,
     *     can_copy_phone: int,
     *     can_copy_razao_from_address: int,
     *     can_backfill_razao_from_oc_legacy: int,
     *     legacy_unknown: int,
     *     erp_status_empty: int,
     *     pending_backfill: int,
     *     problematic_total: int
     * }
     */
    public function collectSummary(): array
    {
        $connection = $this->resourceConnection->getConnection();
        $attrs = $this->resolveAttributeIds();
        $baseFrom = $this->buildCnpjBaseFrom($attrs);
        $addressTable = $this->resourceConnection->getTableName('customer_address_entity');

        $noPhone = $this->countWithCondition($baseFrom, $attrs, 'phone');
        $noRazao = $this->countWithCondition($baseFrom, $attrs, 'razao');
        $erpEmpty = $this->countWithCondition($baseFrom, $attrs, 'erp');

        $noAddressPhone = (int) $connection->fetchOne(
            "SELECT COUNT(DISTINCT ce.entity_id) {$baseFrom}
             LEFT JOIN {$addressTable} addr_phone
                ON addr_phone.parent_id = ce.entity_id
                AND addr_phone.telephone IS NOT NULL AND addr_phone.telephone != ''
             WHERE addr_phone.entity_id IS NULL"
        );

        $noAddressCompany = (int) $connection->fetchOne(
            "SELECT COUNT(DISTINCT ce.entity_id) {$baseFrom}
             LEFT JOIN {$addressTable} addr_co
                ON addr_co.parent_id = ce.entity_id
                AND addr_co.company IS NOT NULL AND addr_co.company != ''
             WHERE addr_co.entity_id IS NULL"
        );

        $canCopyPhone = (int) $connection->fetchOne(
            "SELECT COUNT(DISTINCT ce.entity_id) {$baseFrom}
             LEFT JOIN customer_entity_varchar phone
                ON phone.entity_id = ce.entity_id AND phone.attribute_id = {$attrs['b2b_phone']}
             INNER JOIN {$addressTable} addr
                ON addr.parent_id = ce.entity_id AND addr.telephone IS NOT NULL AND addr.telephone != ''
             WHERE phone.value IS NULL OR phone.value = ''"
        );

        $canCopyRazaoAddress = (int) $connection->fetchOne(
            "SELECT COUNT(DISTINCT ce.entity_id) {$baseFrom}
             LEFT JOIN customer_entity_varchar razao
                ON razao.entity_id = ce.entity_id AND razao.attribute_id = {$attrs['b2b_razao_social']}
             INNER JOIN {$addressTable} addr
                ON addr.parent_id = ce.entity_id AND addr.company IS NOT NULL AND addr.company != ''
             WHERE razao.value IS NULL OR razao.value = ''"
        );

        $mapTable = $this->resourceConnection->getTableName('oc_customer_id_map');
        $ocTable = $this->resourceConnection->getTableName('oc_customer');
        $canBackfillOcLegacy = (int) $connection->fetchOne(
            "SELECT COUNT(DISTINCT ce.entity_id) {$baseFrom}
             LEFT JOIN customer_entity_varchar razao
                ON razao.entity_id = ce.entity_id AND razao.attribute_id = {$attrs['b2b_razao_social']}
             INNER JOIN {$mapTable} map ON map.magento_customer_id = ce.entity_id
             INNER JOIN {$ocTable} oc ON oc.customer_id = map.old_oc_customer_id
             WHERE (razao.value IS NULL OR razao.value = '')
               AND oc.custom_field IS NOT NULL AND oc.custom_field != ''
               AND JSON_UNQUOTE(JSON_EXTRACT(oc.custom_field, '$.\"1\"')) != ''
               AND CHAR_LENGTH(JSON_UNQUOTE(JSON_EXTRACT(oc.custom_field, '$.\"1\"'))) >= 3"
        );

        $legacyUnknown = (int) $connection->fetchOne(
            "SELECT COUNT(DISTINCT ce.entity_id) {$baseFrom}
             INNER JOIN customer_entity_varchar origin
                ON origin.entity_id = ce.entity_id
                AND origin.attribute_id = {$attrs['b2b_origin_host']}
                AND origin.value = 'legacy_unknown'"
        );

        $pendingBackfill = (int) $connection->fetchOne(
            "SELECT COUNT(DISTINCT ce.entity_id) {$baseFrom}
             LEFT JOIN customer_entity_varchar erp ON erp.entity_id = ce.entity_id AND erp.attribute_id = {$attrs['erp_customer_sync_status']}
             LEFT JOIN customer_entity_varchar origin ON origin.entity_id = ce.entity_id AND origin.attribute_id = {$attrs['b2b_origin_host']}
             LEFT JOIN customer_entity_varchar camp ON camp.entity_id = ce.entity_id AND camp.attribute_id = {$attrs['b2b_registration_campaign']}
             WHERE (erp.value IS NULL OR erp.value = '')
                OR (origin.value IS NULL OR origin.value = '')
                OR (camp.value IS NULL OR camp.value = '')"
        );

        $totalWithCnpj = (int) $connection->fetchOne(
            "SELECT COUNT(DISTINCT ce.entity_id) {$baseFrom}"
        );

        $problematicTotal = (int) $connection->fetchOne(
            "SELECT COUNT(DISTINCT ce.entity_id) {$baseFrom}
             LEFT JOIN customer_entity_varchar phone
                ON phone.entity_id = ce.entity_id AND phone.attribute_id = {$attrs['b2b_phone']}
             LEFT JOIN customer_entity_varchar razao
                ON razao.entity_id = ce.entity_id AND razao.attribute_id = {$attrs['b2b_razao_social']}
             LEFT JOIN customer_entity_varchar erp
                ON erp.entity_id = ce.entity_id AND erp.attribute_id = {$attrs['erp_customer_sync_status']}
             WHERE (phone.value IS NULL OR phone.value = '')
                OR (razao.value IS NULL OR razao.value = '')
                OR (erp.value IS NULL OR erp.value = '')"
        );

        return [
            'total_with_cnpj' => $totalWithCnpj,
            'no_phone' => $noPhone,
            'no_razao_social' => $noRazao,
            'no_address_phone' => $noAddressPhone,
            'no_address_company' => $noAddressCompany,
            'can_copy_phone' => $canCopyPhone,
            'can_copy_razao_from_address' => $canCopyRazaoAddress,
            'can_backfill_razao_from_oc_legacy' => $canBackfillOcLegacy,
            'legacy_unknown' => $legacyUnknown,
            'erp_status_empty' => $erpEmpty,
            'pending_backfill' => $pendingBackfill,
            'problematic_total' => $problematicTotal,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function fetchProblematicCustomers(
        ?int $limit = null,
        ?int $fromId = null,
        ?int $toId = null
    ): array {
        $connection = $this->resourceConnection->getConnection();
        $attrs = $this->resolveAttributeIds();
        $addressTable = $this->resourceConnection->getTableName('customer_address_entity');

        $select = $connection->select()
            ->from(['ce' => $this->resourceConnection->getTableName('customer_entity')], [
                'entity_id',
                'email',
                'default_billing',
                'default_shipping',
            ])
            ->joinInner(
                ['cnpj' => $this->resourceConnection->getTableName('customer_entity_varchar')],
                "cnpj.entity_id = ce.entity_id AND cnpj.attribute_id = {$attrs['b2b_cnpj']} AND cnpj.value != ''",
                ['cnpj' => 'value']
            )
            ->joinLeft(
                ['phone' => $this->resourceConnection->getTableName('customer_entity_varchar')],
                "phone.entity_id = ce.entity_id AND phone.attribute_id = {$attrs['b2b_phone']}",
                ['b2b_phone' => 'value']
            )
            ->joinLeft(
                ['razao' => $this->resourceConnection->getTableName('customer_entity_varchar')],
                "razao.entity_id = ce.entity_id AND razao.attribute_id = {$attrs['b2b_razao_social']}",
                ['b2b_razao_social' => 'value']
            )
            ->joinLeft(
                ['erp' => $this->resourceConnection->getTableName('customer_entity_varchar')],
                "erp.entity_id = ce.entity_id AND erp.attribute_id = {$attrs['erp_customer_sync_status']}",
                ['erp_status' => 'value']
            )
            ->joinLeft(
                ['origin' => $this->resourceConnection->getTableName('customer_entity_varchar')],
                "origin.entity_id = ce.entity_id AND origin.attribute_id = {$attrs['b2b_origin_host']}",
                ['b2b_origin_host' => 'value']
            )
            ->where(
                '(phone.value IS NULL OR phone.value = \'\')'
                . ' OR (razao.value IS NULL OR razao.value = \'\')'
                . ' OR (erp.value IS NULL OR erp.value = \'\')'
            )
            ->order('ce.entity_id ASC');

        if ($fromId !== null && $fromId > 0) {
            $select->where('ce.entity_id >= ?', $fromId);
        }
        if ($toId !== null && $toId > 0) {
            $select->where('ce.entity_id <= ?', $toId);
        }
        if ($limit !== null && $limit > 0) {
            $select->limit($limit);
        }

        $rows = $connection->fetchAll($select);
        $result = [];

        foreach ($rows as $row) {
            $customerId = (int) $row['entity_id'];
            $issues = [];
            $b2bPhone = trim((string) ($row['b2b_phone'] ?? ''));
            $razao = trim((string) ($row['b2b_razao_social'] ?? ''));
            $erp = trim((string) ($row['erp_status'] ?? ''));

            if ($b2bPhone === '') {
                $issues[] = 'sem_b2b_phone';
            }
            if ($razao === '') {
                $issues[] = 'sem_razao_social';
            }
            if ($erp === '') {
                $issues[] = 'erp_vazio';
            }

            $addressData = $this->resolveBestAddressFields(
                $customerId,
                (int) ($row['default_billing'] ?? 0),
                (int) ($row['default_shipping'] ?? 0),
                $addressTable,
                $connection
            );

            $result[] = [
                'entity_id' => $customerId,
                'email' => (string) $row['email'],
                'cnpj' => (string) ($row['cnpj'] ?? ''),
                'b2b_phone' => $b2bPhone,
                'address_phone' => $addressData['telephone'],
                'b2b_razao_social' => $razao,
                'address_company' => $addressData['company'],
                'b2b_origin_host' => trim((string) ($row['b2b_origin_host'] ?? '')),
                'erp_status' => $erp,
                'issues' => implode(', ', $issues),
            ];
        }

        return $result;
    }

    /**
     * @return array{path: string, rows: int}
     */
    public function exportProblematicCustomersCsv(
        string $relativePath,
        ?int $fromId = null,
        ?int $toId = null
    ): array {
        $rows = $this->fetchProblematicCustomers(null, $fromId, $toId);
        $directory = $this->filesystem->getDirectoryWrite(DirectoryList::VAR_DIR);
        $directory->create('export');

        $relativePath = ltrim($relativePath, '/');
        if (!str_starts_with($relativePath, 'export/')) {
            $relativePath = 'export/' . basename($relativePath);
        }

        $absolutePath = $directory->getAbsolutePath($relativePath);
        $handle = fopen($absolutePath, 'wb');
        if ($handle === false) {
            throw new \RuntimeException('Não foi possível criar arquivo CSV: ' . $absolutePath);
        }

        fputcsv($handle, [
            'entity_id',
            'email',
            'cnpj',
            'b2b_phone',
            'telefone_endereco',
            'b2b_razao_social',
            'company_endereco',
            'b2b_origin_host',
            'erp_customer_sync_status',
            'problemas',
        ], ';', '"', '\\');

        foreach ($rows as $row) {
            fputcsv($handle, [
                $row['entity_id'],
                $row['email'],
                $row['cnpj'],
                $row['b2b_phone'],
                $row['address_phone'],
                $row['b2b_razao_social'],
                $row['address_company'],
                $row['b2b_origin_host'],
                $row['erp_status'],
                $row['issues'],
            ], ';', '"', '\\');
        }

        fclose($handle);

        return [
            'path' => $absolutePath,
            'rows' => count($rows),
        ];
    }

    /**
     * @return array{telephone: string, company: string}
     */
    private function resolveBestAddressFields(
        int $customerId,
        int $billingId,
        int $shippingId,
        string $addressTable,
        \Magento\Framework\DB\Adapter\AdapterInterface $connection
    ): array {
        foreach ([$billingId, $shippingId] as $addressId) {
            if ($addressId <= 0) {
                continue;
            }
            $row = $connection->fetchRow(
                "SELECT telephone, company FROM {$addressTable} WHERE entity_id = ?",
                [$addressId]
            );
            if ($row) {
                return [
                    'telephone' => trim((string) ($row['telephone'] ?? '')),
                    'company' => trim((string) ($row['company'] ?? '')),
                ];
            }
        }

        $row = $connection->fetchRow(
            $connection->select()
                ->from($addressTable, ['telephone', 'company'])
                ->where('parent_id = ?', $customerId)
                ->order('entity_id ASC')
                ->limit(1)
        );

        return [
            'telephone' => trim((string) ($row['telephone'] ?? '')),
            'company' => trim((string) ($row['company'] ?? '')),
        ];
    }

    private function countWithCondition(string $baseFrom, array $attrs, string $type): int
    {
        $connection = $this->resourceConnection->getConnection();
        $map = [
            'phone' => $attrs['b2b_phone'],
            'razao' => $attrs['b2b_razao_social'],
            'erp' => $attrs['erp_customer_sync_status'],
        ];
        $alias = $type . '_attr';

        return (int) $connection->fetchOne(
            "SELECT COUNT(DISTINCT ce.entity_id) {$baseFrom}
             LEFT JOIN customer_entity_varchar {$alias}
                ON {$alias}.entity_id = ce.entity_id AND {$alias}.attribute_id = {$map[$type]}
             WHERE {$alias}.value IS NULL OR {$alias}.value = ''"
        );
    }

    /**
     * @return array<string, int>
     */
    private function resolveAttributeIds(): array
    {
        if ($this->attributeIds !== null) {
            return $this->attributeIds;
        }

        $connection = $this->resourceConnection->getConnection();
        $codes = [
            'b2b_cnpj',
            'b2b_phone',
            'b2b_razao_social',
            'b2b_origin_host',
            'b2b_registration_campaign',
            'erp_customer_sync_status',
        ];

        $this->attributeIds = [];
        foreach ($codes as $code) {
            $this->attributeIds[$code] = (int) $connection->fetchOne(
                "SELECT ea.attribute_id FROM eav_attribute ea
                 INNER JOIN eav_entity_type et ON et.entity_type_id = ea.entity_type_id
                 WHERE ea.attribute_code = ? AND et.entity_type_code = 'customer'",
                [$code]
            );
        }

        return $this->attributeIds;
    }

    /**
     * @param array<string, int> $attrs
     */
    private function buildCnpjBaseFrom(array $attrs): string
    {
        return "
            FROM customer_entity ce
            INNER JOIN customer_entity_varchar cnpj
                ON cnpj.entity_id = ce.entity_id
                AND cnpj.attribute_id = {$attrs['b2b_cnpj']}
                AND cnpj.value != ''
        ";
    }
}
