<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Model\Registration;

use Magento\Customer\Model\CustomerFactory;
use Magento\Customer\Model\CustomerRegistry;
use Magento\Customer\Model\ResourceModel\Customer as CustomerResource;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\Serializer\Json;

/**
 * Backfill b2b_razao_social from OpenCart legacy oc_customer.custom_field (key "1").
 * Does not call external APIs or modify Sectra/orders.
 */
class BackfillRazaoSocialFromOcLegacyService
{
    private const LOG_FILE = '/var/log/b2b_razao_social_backfill.log';
    private const ATTR_RAZAO = 'b2b_razao_social';
    private const OC_RAZAO_KEY = '1';
    private const OC_CNPJ_KEY = '6';

    private ?int $razaoAttrId = null;
    private ?int $cnpjAttrId = null;

    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly CustomerFactory $customerFactory,
        private readonly CustomerResource $customerResource,
        private readonly CustomerRegistry $customerRegistry,
        private readonly RegistrationBackupService $backupService,
        private readonly Json $json
    ) {
    }

    /**
     * @return array{
     *     analyzed: int,
     *     updated: int,
     *     skipped: int,
     *     failed: int,
     *     no_oc_mapping: int,
     *     no_valid_razao_in_oc: int,
     *     cnpj_mismatch: int,
     *     details: list<string>,
     *     errors: list<string>,
     *     backup_path: string|null,
     *     snapshot: array{sem_b2b_razao_social: int, pode_backfill_oc_legacy: int}
     * }
     */
    public function execute(
        bool $apply = false,
        ?int $limit = null,
        ?int $fromId = null,
        ?int $toId = null
    ): array {
        $report = [
            'analyzed' => 0,
            'updated' => 0,
            'skipped' => 0,
            'failed' => 0,
            'no_oc_mapping' => 0,
            'no_valid_razao_in_oc' => 0,
            'cnpj_mismatch' => 0,
            'details' => [],
            'errors' => [],
            'backup_path' => null,
            'snapshot' => [],
        ];

        $candidates = $this->fetchCandidates($limit, $fromId, $toId);
        $toUpdate = [];

        foreach ($candidates as $row) {
            $report['analyzed']++;
            $customerId = (int) $row['entity_id'];
            $email = (string) $row['email'];
            $cnpjMagento = preg_replace('/\D/', '', (string) $row['cnpj']);

            $currentRazao = trim($this->readRazaoFromDb($customerId));
            if ($currentRazao !== '') {
                $report['skipped']++;
                continue;
            }

            $customField = trim((string) ($row['custom_field'] ?? ''));
            if ($customField === '') {
                $report['no_oc_mapping']++;
                $report['skipped']++;
                continue;
            }

            $parsed = $this->parseOcCustomField($customField);
            if ($parsed === null) {
                $report['no_valid_razao_in_oc']++;
                $report['skipped']++;
                continue;
            }

            if ($cnpjMagento !== '' && $parsed['cnpj'] !== '' && $cnpjMagento !== $parsed['cnpj']) {
                $report['cnpj_mismatch']++;
                $report['skipped']++;
                $this->log('SKIP', sprintf(
                    'customer_id=%d | email=%s | cnpj_magento=%s | cnpj_oc=%s | motivo=cnpj_mismatch',
                    $customerId,
                    $email,
                    $cnpjMagento,
                    $parsed['cnpj']
                ));
                continue;
            }

            $toUpdate[] = [
                'entity_id' => $customerId,
                'email' => $email,
                'cnpj' => $cnpjMagento,
                'razao' => $parsed['razao'],
                'old_oc_customer_id' => (int) ($row['old_oc_customer_id'] ?? 0),
            ];
        }

        if ($apply && $toUpdate !== []) {
            $backup = $this->backupService->exportCustomersBackup(
                'razao_social_oc_legacy',
                array_column($toUpdate, 'entity_id'),
                ['b2b_cnpj', 'b2b_razao_social', 'b2b_phone', 'erp_customer_sync_status']
            );
            $report['backup_path'] = $backup['path'];
        }

        foreach ($toUpdate as $item) {
            $customerId = $item['entity_id'];
            $email = $item['email'];
            $razao = $item['razao'];

            $detail = sprintf(
                'customer_id=%d | email=%s | cnpj=%s | b2b_razao_atual=NULL | razao_oc=%s | oc_customer_id=%d | origem=oc_custom_field',
                $customerId,
                $email,
                $item['cnpj'],
                mb_substr($razao, 0, 60),
                $item['old_oc_customer_id']
            );
            $report['details'][] = $detail;
            $this->log($apply ? 'UPDATE' : 'DRY-RUN', $detail);

            if (!$apply) {
                $report['updated']++;
                continue;
            }

            try {
                $this->persistRazao($customerId, $email, $razao);
                $report['updated']++;
            } catch (\Throwable $e) {
                $report['failed']++;
                $error = sprintf('customer #%d (%s): %s', $customerId, $email, $e->getMessage());
                $report['errors'][] = $error;
                $this->log('ERROR', $error);
            }
        }

        $report['snapshot'] = $this->collectSnapshot();

        return $report;
    }

    /**
     * @return array{razao: string, cnpj: string}|null
     */
    private function parseOcCustomField(string $customField): ?array
    {
        try {
            $data = $this->json->unserialize($customField);
        } catch (\InvalidArgumentException) {
            return null;
        }

        if (!is_array($data)) {
            return null;
        }

        $razao = trim((string) ($data[self::OC_RAZAO_KEY] ?? ''));
        if ($razao === '' || mb_strlen($razao) < 3) {
            return null;
        }

        $cnpj = preg_replace('/\D/', '', (string) ($data[self::OC_CNPJ_KEY] ?? ''));

        return [
            'razao' => $razao,
            'cnpj' => is_string($cnpj) ? $cnpj : '',
        ];
    }

    /**
     * @return list<array{entity_id: int, email: string, cnpj: string, custom_field: string|null, old_oc_customer_id: int|null}>
     */
    private function fetchCandidates(?int $limit, ?int $fromId, ?int $toId): array
    {
        $connection = $this->resourceConnection->getConnection();
        $razaoAttrId = $this->resolveRazaoAttrId();
        $cnpjAttrId = $this->resolveCnpjAttrId();
        $varcharTable = $this->resourceConnection->getTableName('customer_entity_varchar');
        $mapTable = $this->resourceConnection->getTableName('oc_customer_id_map');
        $ocTable = $this->resourceConnection->getTableName('oc_customer');

        $select = $connection->select()
            ->from(['ce' => $this->resourceConnection->getTableName('customer_entity')], [
                'entity_id',
                'email',
            ])
            ->joinInner(
                ['cnpj' => $varcharTable],
                "cnpj.entity_id = ce.entity_id AND cnpj.attribute_id = {$cnpjAttrId} AND cnpj.value != ''",
                ['cnpj' => 'value']
            )
            ->joinLeft(
                ['razao' => $varcharTable],
                "razao.entity_id = ce.entity_id AND razao.attribute_id = {$razaoAttrId}",
                []
            )
            ->joinInner(
                ['map' => $mapTable],
                'map.magento_customer_id = ce.entity_id',
                ['old_oc_customer_id' => 'old_oc_customer_id']
            )
            ->joinInner(
                ['oc' => $ocTable],
                'oc.customer_id = map.old_oc_customer_id',
                ['custom_field' => 'custom_field']
            )
            ->where('razao.value IS NULL OR razao.value = \'\'')
            ->where('oc.custom_field IS NOT NULL AND oc.custom_field != \'\'')
            ->order('ce.entity_id ASC');

        if ($fromId !== null && $fromId > 0) {
            $select->where('ce.entity_id >= ?', $fromId);
        }
        if ($toId !== null && $toId > 0) {
            $select->where('ce.entity_id <= ?', $toId);
        }
        if ($limit !== null && $limit > 0) {
            $select->limit($limit * 3);
        }

        $rows = $connection->fetchAll($select);
        $deduped = $this->dedupeCandidates($rows);

        if ($limit !== null && $limit > 0) {
            return array_slice($deduped, 0, $limit);
        }

        return $deduped;
    }

    /**
     * Quando há múltiplos mapeamentos OC para o mesmo Magento customer, escolhe o melhor.
     *
     * @param list<array{entity_id: int, email: string, cnpj: string, custom_field: string|null, old_oc_customer_id: int|null}> $rows
     * @return list<array{entity_id: int, email: string, cnpj: string, custom_field: string|null, old_oc_customer_id: int|null}>
     */
    private function dedupeCandidates(array $rows): array
    {
        $best = [];

        foreach ($rows as $row) {
            $customerId = (int) $row['entity_id'];
            if (!isset($best[$customerId]) || $this->scoreOcCandidate($row) > $this->scoreOcCandidate($best[$customerId])) {
                $best[$customerId] = $row;
            }
        }

        $result = array_values($best);
        usort($result, static fn (array $a, array $b): int => $a['entity_id'] <=> $b['entity_id']);

        return $result;
    }

    /**
     * @param array{entity_id: int, email: string, cnpj: string, custom_field: string|null, old_oc_customer_id: int|null} $row
     */
    private function scoreOcCandidate(array $row): int
    {
        $score = 0;
        $cnpjMagento = preg_replace('/\D/', '', (string) $row['cnpj']);
        $parsed = $this->parseOcCustomField(trim((string) ($row['custom_field'] ?? '')));
        $ocId = (int) ($row['old_oc_customer_id'] ?? 0);

        if ($parsed !== null && $cnpjMagento !== '' && $parsed['cnpj'] === $cnpjMagento) {
            $score += 100;
        }

        if ($ocId > 0 && $ocId < 90000) {
            $score += 20;
        } elseif ($ocId >= 90000) {
            $score -= 50;
        }

        if ($parsed !== null && mb_strlen($parsed['razao']) >= 5) {
            $score += 10;
        }

        return $score;
    }

    /**
     * @throws LocalizedException
     */
    private function persistRazao(int $customerId, string $email, string $newRazao): void
    {
        $currentRazao = trim($this->readRazaoFromDb($customerId));
        if ($currentRazao !== '') {
            throw new LocalizedException(__('b2b_razao_social já preenchido para customer #%1.', $customerId));
        }

        $customerModel = $this->customerFactory->create();
        $this->customerResource->load($customerModel, $customerId);
        if (!$customerModel->getId()) {
            throw new LocalizedException(__('Cliente #%1 não encontrado.', $customerId));
        }

        $customerModel->setData(self::ATTR_RAZAO, $newRazao);
        $this->customerResource->saveAttribute($customerModel, self::ATTR_RAZAO);
        $this->customerRegistry->remove($customerId);

        $persistedDb = trim($this->readRazaoFromDb($customerId));
        if ($persistedDb !== $newRazao) {
            throw new LocalizedException(__(
                'Falha ao persistir b2b_razao_social para customer #%1. Esperado "%2", DB="%3".',
                $customerId,
                $newRazao,
                $persistedDb
            ));
        }

        $this->log(
            'PERSISTED',
            sprintf(
                'customer_id=%d | email=%s | b2b_razao_atual=NULL | razao_oc=%s | origem=oc_custom_field | persisted=yes',
                $customerId,
                $email,
                mb_substr($newRazao, 0, 80)
            )
        );
    }

    /**
     * @return array{sem_b2b_razao_social: int, pode_backfill_oc_legacy: int}
     */
    public function collectSnapshot(): array
    {
        $connection = $this->resourceConnection->getConnection();
        $razaoAttrId = $this->resolveRazaoAttrId();
        $cnpjAttrId = $this->resolveCnpjAttrId();
        $mapTable = $this->resourceConnection->getTableName('oc_customer_id_map');
        $ocTable = $this->resourceConnection->getTableName('oc_customer');

        $baseFrom = "
            FROM customer_entity ce
            INNER JOIN customer_entity_varchar cnpj
                ON cnpj.entity_id = ce.entity_id AND cnpj.attribute_id = {$cnpjAttrId} AND cnpj.value != ''
            LEFT JOIN customer_entity_varchar razao
                ON razao.entity_id = ce.entity_id AND razao.attribute_id = {$razaoAttrId}
        ";

        $semRazao = (int) $connection->fetchOne(
            "SELECT COUNT(DISTINCT ce.entity_id) {$baseFrom}
             WHERE razao.value IS NULL OR razao.value = ''"
        );

        $podeOc = (int) $connection->fetchOne(
            "SELECT COUNT(DISTINCT ce.entity_id) {$baseFrom}
             INNER JOIN {$mapTable} map ON map.magento_customer_id = ce.entity_id
             INNER JOIN {$ocTable} oc ON oc.customer_id = map.old_oc_customer_id
             WHERE (razao.value IS NULL OR razao.value = '')
               AND oc.custom_field IS NOT NULL AND oc.custom_field != ''
               AND JSON_UNQUOTE(JSON_EXTRACT(oc.custom_field, '$.\"1\"')) != ''
               AND CHAR_LENGTH(JSON_UNQUOTE(JSON_EXTRACT(oc.custom_field, '$.\"1\"'))) >= 3"
        );

        return [
            'sem_b2b_razao_social' => $semRazao,
            'pode_backfill_oc_legacy' => $podeOc,
        ];
    }

    private function readRazaoFromDb(int $customerId): string
    {
        $connection = $this->resourceConnection->getConnection();
        $value = $connection->fetchOne(
            'SELECT value FROM customer_entity_varchar WHERE entity_id = ? AND attribute_id = ?',
            [$customerId, $this->resolveRazaoAttrId()]
        );

        return $value !== false ? (string) $value : '';
    }

    private function resolveRazaoAttrId(): int
    {
        if ($this->razaoAttrId !== null) {
            return $this->razaoAttrId;
        }

        $this->razaoAttrId = (int) $this->resourceConnection->getConnection()->fetchOne(
            "SELECT attribute_id FROM eav_attribute WHERE attribute_code = ? AND entity_type_id = 1",
            [self::ATTR_RAZAO]
        );

        return $this->razaoAttrId;
    }

    private function resolveCnpjAttrId(): int
    {
        if ($this->cnpjAttrId !== null) {
            return $this->cnpjAttrId;
        }

        $this->cnpjAttrId = (int) $this->resourceConnection->getConnection()->fetchOne(
            "SELECT attribute_id FROM eav_attribute WHERE attribute_code = 'b2b_cnpj' AND entity_type_id = 1"
        );

        return $this->cnpjAttrId;
    }

    private function log(string $level, string $message): void
    {
        $line = sprintf('[%s] [%s] %s', date('Y-m-d H:i:s'), $level, $message);
        @file_put_contents(BP . self::LOG_FILE, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
}
