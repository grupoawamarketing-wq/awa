<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Model\Registration;

use GrupoAwamotos\B2B\Helper\CnpjValidator;
use Magento\Customer\Model\CustomerFactory;
use Magento\Customer\Model\CustomerRegistry;
use Magento\Customer\Model\ResourceModel\Customer as CustomerResource;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;

/**
 * Backfill b2b_razao_social from Magento CNPJ lookup cache (no external API calls).
 */
class BackfillRazaoSocialFromCnpjCacheService
{
    private const LOG_FILE = '/var/log/b2b_razao_social_backfill.log';
    private const ATTR_RAZAO = 'b2b_razao_social';

    private ?int $razaoAttrId = null;
    private ?int $cnpjAttrId = null;

    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly CnpjValidator $cnpjValidator,
        private readonly CustomerFactory $customerFactory,
        private readonly CustomerResource $customerResource,
        private readonly CustomerRegistry $customerRegistry,
        private readonly RegistrationBackupService $backupService
    ) {
    }

    /**
     * @return array{
     *     analyzed: int,
     *     updated: int,
     *     skipped: int,
     *     failed: int,
     *     no_cache: int,
     *     no_valid_razao_in_cache: int,
     *     details: list<string>,
     *     errors: list<string>,
     *     backup_path: string|null,
     *     snapshot: array{sem_b2b_razao_social: int}
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
            'no_cache' => 0,
            'no_valid_razao_in_cache' => 0,
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
            $cnpj = preg_replace('/\D/', '', (string) $row['cnpj']);

            if (strlen($cnpj) !== 14) {
                $report['skipped']++;
                continue;
            }

            $currentRazao = trim($this->readRazaoFromDb($customerId));
            if ($currentRazao !== '') {
                $report['skipped']++;
                continue;
            }

            $cached = $this->cnpjValidator->getCachedPayloadOnly($cnpj);
            if ($cached === null) {
                $report['no_cache']++;
                $report['skipped']++;
                continue;
            }

            $razao = $this->extractRazaoSocialFromCache($cached);
            if ($razao === null) {
                $report['no_valid_razao_in_cache']++;
                $report['skipped']++;
                continue;
            }

            $toUpdate[] = [
                'entity_id' => $customerId,
                'email' => $email,
                'cnpj' => $cnpj,
                'razao' => $razao,
            ];
        }

        if ($apply && $toUpdate !== []) {
            $backup = $this->backupService->exportCustomersBackup(
                'razao_social',
                array_column($toUpdate, 'entity_id'),
                ['b2b_cnpj', 'b2b_razao_social', 'b2b_phone', 'erp_customer_sync_status']
            );
            $report['backup_path'] = $backup['path'];
        }

        foreach ($toUpdate as $item) {
            $customerId = $item['entity_id'];
            $email = $item['email'];
            $cnpj = $item['cnpj'];
            $razao = $item['razao'];
            $currentLabel = 'NULL';

            $detail = sprintf(
                'customer_id=%d | email=%s | cnpj=%s | b2b_razao_atual=%s | razao_cache=%s | origem=cnpj_cache',
                $customerId,
                $email,
                $cnpj,
                $currentLabel,
                mb_substr($razao, 0, 60)
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
     * @param array<string, mixed> $payload
     */
    private function extractRazaoSocialFromCache(array $payload): ?string
    {
        if (!empty($payload['api_error']) || ($payload['source'] ?? '') === 'fallback') {
            return null;
        }

        if (isset($payload['valid']) && $payload['valid'] === false) {
            return null;
        }

        $razao = trim((string) ($payload['razao_social'] ?? ''));
        if ($razao === '') {
            $data = $payload['data'] ?? null;
            if (is_array($data)) {
                $razao = trim((string) ($data['nome'] ?? $data['razao_social'] ?? ''));
            }
        }

        return $razao !== '' ? $razao : null;
    }

    /**
     * @return list<array{entity_id: int, email: string, cnpj: string}>
     */
    private function fetchCandidates(?int $limit, ?int $fromId, ?int $toId): array
    {
        $connection = $this->resourceConnection->getConnection();
        $razaoAttrId = $this->resolveRazaoAttrId();
        $cnpjAttrId = $this->resolveCnpjAttrId();
        $varcharTable = $this->resourceConnection->getTableName('customer_entity_varchar');

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
            ->where('razao.value IS NULL OR razao.value = \'\'')
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

        return array_map(
            static fn (array $row): array => [
                'entity_id' => (int) $row['entity_id'],
                'email' => (string) $row['email'],
                'cnpj' => (string) $row['cnpj'],
            ],
            $rows
        );
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
                'customer_id=%d | email=%s | b2b_razao_atual=NULL | razao_cache=%s | origem=cnpj_cache | persisted=yes',
                $customerId,
                $email,
                mb_substr($newRazao, 0, 80)
            )
        );
    }

    /**
     * @return array{sem_b2b_razao_social: int}
     */
    public function collectSnapshot(): array
    {
        $connection = $this->resourceConnection->getConnection();
        $razaoAttrId = $this->resolveRazaoAttrId();
        $cnpjAttrId = $this->resolveCnpjAttrId();

        $semRazao = (int) $connection->fetchOne(
            "SELECT COUNT(DISTINCT ce.entity_id)
             FROM customer_entity ce
             INNER JOIN customer_entity_varchar cnpj
                ON cnpj.entity_id = ce.entity_id AND cnpj.attribute_id = {$cnpjAttrId} AND cnpj.value != ''
             LEFT JOIN customer_entity_varchar razao
                ON razao.entity_id = ce.entity_id AND razao.attribute_id = {$razaoAttrId}
             WHERE razao.value IS NULL OR razao.value = ''"
        );

        return ['sem_b2b_razao_social' => $semRazao];
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
