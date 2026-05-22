<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Model\Registration;

use Magento\Customer\Model\CustomerFactory;
use Magento\Customer\Model\CustomerRegistry;
use Magento\Customer\Model\ResourceModel\Customer as CustomerResource;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;

/**
 * Backfill b2b_phone from customer address telephone (read-only on all other fields).
 */
class BackfillPhoneFromAddressService
{
    private const LOG_FILE = '/var/log/b2b_phone_backfill.log';
    private const ATTR_PHONE = 'b2b_phone';

    private ?int $phoneAttrId = null;

    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly CustomerFactory $customerFactory,
        private readonly CustomerResource $customerResource,
        private readonly CustomerRegistry $customerRegistry
    ) {
    }

    /**
     * @return array{
     *     analyzed: int,
     *     updated: int,
     *     skipped: int,
     *     failed: int,
     *     no_address_phone: int,
     *     details: list<string>,
     *     errors: list<string>,
     *     snapshot: array{sem_b2b_phone: int, pode_copiar_telefone: int}
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
            'no_address_phone' => 0,
            'details' => [],
            'errors' => [],
            'snapshot' => [],
        ];

        $candidates = $this->fetchCandidates($limit, $fromId, $toId);

        foreach ($candidates as $row) {
            $report['analyzed']++;
            $customerId = (int) $row['entity_id'];
            $email = (string) $row['email'];

            $currentPhone = trim($this->readPhoneFromDb($customerId));
            if ($currentPhone !== '') {
                $report['skipped']++;
                continue;
            }

            $resolved = $this->resolveAddressPhone($customerId);
            if ($resolved === null) {
                $report['no_address_phone']++;
                $report['skipped']++;
                continue;
            }

            $newPhone = $resolved['telephone'];
            $currentLabel = $currentPhone !== '' ? $currentPhone : 'NULL';
            $detail = sprintf(
                'customer_id=%d | email=%s | b2b_phone_atual=%s | telefone_origem=%s | address_id=%d | origem=%s',
                $customerId,
                $email,
                $currentLabel,
                $newPhone,
                $resolved['address_id'],
                $resolved['source']
            );
            $report['details'][] = $detail;
            $this->log($apply ? 'UPDATE' : 'DRY-RUN', $detail);

            if (!$apply) {
                $report['updated']++;
                continue;
            }

            try {
                $this->persistPhone($customerId, $email, $newPhone, $resolved);
                $report['updated']++;
            } catch (\Throwable $e) {
                $report['failed']++;
                $report['skipped']++;
                $error = sprintf('customer #%d (%s): %s', $customerId, $email, $e->getMessage());
                $report['errors'][] = $error;
                $this->log('ERROR', $error);
            }
        }

        $report['snapshot'] = $this->collectSnapshot();

        return $report;
    }

    /**
     * @return list<array{entity_id: int, email: string}>
     */
    private function fetchCandidates(?int $limit, ?int $fromId, ?int $toId): array
    {
        $connection = $this->resourceConnection->getConnection();
        $phoneAttrId = $this->resolvePhoneAttrId();
        $addressTable = $this->resourceConnection->getTableName('customer_address_entity');
        $varcharTable = $this->resourceConnection->getTableName('customer_entity_varchar');

        $select = $connection->select()
            ->from(['ce' => $this->resourceConnection->getTableName('customer_entity')], [
                'entity_id',
                'email',
            ])
            ->joinLeft(
                ['phone' => $varcharTable],
                "phone.entity_id = ce.entity_id AND phone.attribute_id = {$phoneAttrId}",
                []
            )
            ->joinInner(
                ['addr' => $addressTable],
                'addr.parent_id = ce.entity_id AND addr.telephone IS NOT NULL AND addr.telephone != \'\'',
                []
            )
            ->where('phone.value IS NULL OR phone.value = \'\'')
            ->group('ce.entity_id')
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
            ],
            $rows
        );
    }

    /**
     * @return array{telephone: string, address_id: int, source: string}|null
     */
    private function resolveAddressPhone(int $customerId): ?array
    {
        $connection = $this->resourceConnection->getConnection();
        $addressTable = $this->resourceConnection->getTableName('customer_address_entity');

        $defaults = $connection->fetchRow(
            'SELECT default_billing, default_shipping FROM customer_entity WHERE entity_id = ?',
            [$customerId]
        );

        if (!$defaults) {
            return null;
        }

        $billingId = (int) ($defaults['default_billing'] ?? 0);
        if ($billingId > 0) {
            $phone = $this->readAddressTelephone($billingId);
            if ($phone !== null) {
                return [
                    'telephone' => $phone,
                    'address_id' => $billingId,
                    'source' => 'default_billing',
                ];
            }
        }

        $shippingId = (int) ($defaults['default_shipping'] ?? 0);
        if ($shippingId > 0 && $shippingId !== $billingId) {
            $phone = $this->readAddressTelephone($shippingId);
            if ($phone !== null) {
                return [
                    'telephone' => $phone,
                    'address_id' => $shippingId,
                    'source' => 'default_shipping',
                ];
            }
        }

        $fallbackId = $connection->fetchOne(
            $connection->select()
                ->from($addressTable, ['entity_id'])
                ->where('parent_id = ?', $customerId)
                ->where('telephone IS NOT NULL AND telephone != \'\'')
                ->order('entity_id ASC')
                ->limit(1)
        );

        if ($fallbackId) {
            $phone = $this->readAddressTelephone((int) $fallbackId);
            if ($phone !== null) {
                return [
                    'telephone' => $phone,
                    'address_id' => (int) $fallbackId,
                    'source' => 'fallback_address',
                ];
            }
        }

        return null;
    }

    private function readAddressTelephone(int $addressId): ?string
    {
        $connection = $this->resourceConnection->getConnection();
        $phone = trim((string) $connection->fetchOne(
            'SELECT telephone FROM customer_address_entity WHERE entity_id = ?',
            [$addressId]
        ));

        return $phone !== '' ? $phone : null;
    }

    /**
     * @param array{telephone: string, address_id: int, source: string} $resolved
     * @throws LocalizedException
     */
    private function persistPhone(int $customerId, string $email, string $newPhone, array $resolved): void
    {
        $currentPhone = trim($this->readPhoneFromDb($customerId));
        if ($currentPhone !== '') {
            throw new LocalizedException(__('b2b_phone já preenchido para customer #%1.', $customerId));
        }

        $customerModel = $this->customerFactory->create();
        $this->customerResource->load($customerModel, $customerId);
        if (!$customerModel->getId()) {
            throw new LocalizedException(__('Cliente #%1 não encontrado.', $customerId));
        }

        $customerModel->setData(self::ATTR_PHONE, $newPhone);
        $this->customerResource->saveAttribute($customerModel, self::ATTR_PHONE);
        $this->customerRegistry->remove($customerId);

        $persistedDb = trim($this->readPhoneFromDb($customerId));
        if ($persistedDb !== $newPhone) {
            throw new LocalizedException(__(
                'Falha ao persistir b2b_phone para customer #%1. Esperado "%2", DB="%3".',
                $customerId,
                $newPhone,
                $persistedDb
            ));
        }

        $this->log(
            'PERSISTED',
            sprintf(
                'customer_id=%d | email=%s | b2b_phone_atual=NULL | telefone_origem=%s | address_id=%d | origem=%s | persisted=yes | db_after_reload=%s',
                $customerId,
                $email,
                $newPhone,
                $resolved['address_id'],
                $resolved['source'],
                $persistedDb
            )
        );
    }

    /**
     * @return array{sem_b2b_phone: int, pode_copiar_telefone: int}
     */
    public function collectSnapshot(): array
    {
        $connection = $this->resourceConnection->getConnection();
        $phoneAttrId = $this->resolvePhoneAttrId();
        $addressTable = $this->resourceConnection->getTableName('customer_address_entity');
        $varcharTable = $this->resourceConnection->getTableName('customer_entity_varchar');
        $cnpjAttrId = (int) $connection->fetchOne(
            "SELECT attribute_id FROM eav_attribute WHERE attribute_code = 'b2b_cnpj' AND entity_type_id = 1"
        );

        $baseFrom = "
            FROM customer_entity ce
            INNER JOIN customer_entity_varchar cnpj
                ON cnpj.entity_id = ce.entity_id
                AND cnpj.attribute_id = {$cnpjAttrId}
                AND cnpj.value != ''
        ";

        $semPhone = (int) $connection->fetchOne(
            "SELECT COUNT(DISTINCT ce.entity_id) {$baseFrom}
             LEFT JOIN customer_entity_varchar phone
                ON phone.entity_id = ce.entity_id AND phone.attribute_id = {$phoneAttrId}
             WHERE phone.value IS NULL OR phone.value = ''"
        );

        $podeCopiar = (int) $connection->fetchOne(
            "SELECT COUNT(DISTINCT ce.entity_id) {$baseFrom}
             LEFT JOIN customer_entity_varchar phone
                ON phone.entity_id = ce.entity_id AND phone.attribute_id = {$phoneAttrId}
             INNER JOIN {$addressTable} addr
                ON addr.parent_id = ce.entity_id AND addr.telephone IS NOT NULL AND addr.telephone != ''
             WHERE phone.value IS NULL OR phone.value = ''"
        );

        return [
            'sem_b2b_phone' => $semPhone,
            'pode_copiar_telefone' => $podeCopiar,
        ];
    }

    private function readPhoneFromDb(int $customerId): string
    {
        $connection = $this->resourceConnection->getConnection();
        $value = $connection->fetchOne(
            'SELECT value FROM customer_entity_varchar WHERE entity_id = ? AND attribute_id = ?',
            [$customerId, $this->resolvePhoneAttrId()]
        );

        return $value !== false ? (string) $value : '';
    }

    private function resolvePhoneAttrId(): int
    {
        if ($this->phoneAttrId !== null) {
            return $this->phoneAttrId;
        }

        $this->phoneAttrId = (int) $this->resourceConnection->getConnection()->fetchOne(
            "SELECT attribute_id FROM eav_attribute WHERE attribute_code = ? AND entity_type_id = 1",
            [self::ATTR_PHONE]
        );

        return $this->phoneAttrId;
    }

    private function log(string $level, string $message): void
    {
        $line = sprintf('[%s] [%s] %s', date('Y-m-d H:i:s'), $level, $message);
        $path = BP . self::LOG_FILE;
        @file_put_contents($path, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
}
