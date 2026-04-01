<?php

/**
 * Gerenciador de Atendentes B2B
 * Sistema de distribuição e gestão de clientes por atendente
 */

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Model\Attendant;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Store\Model\ScopeInterface;
use Psr\Log\LoggerInterface;

class AttendantManager
{
    private const TABLE_ATTENDANTS = 'grupoawamotos_b2b_attendants';
    private const TABLE_CUSTOMER_ATTENDANT = 'grupoawamotos_b2b_customer_attendant';
    private const TABLE_ATTENDANT_LOG = 'grupoawamotos_b2b_attendant_log';

    private ScopeConfigInterface $scopeConfig;
    private ResourceConnection $resource;
    private LoggerInterface $logger;

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        ResourceConnection $resource,
        LoggerInterface $logger
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->resource = $resource;
        $this->logger = $logger;
    }

    /**
     * Obtém todos os atendentes ativos
     */
    public function getActiveAttendants(?string $department = null): array
    {
        $connection = $this->resource->getConnection();
        $table = $this->resource->getTableName(self::TABLE_ATTENDANTS);

        $select = $connection->select()
            ->from($table)
            ->where('is_active = ?', 1)
            ->order('name ASC');

        if ($department) {
            $select->where('department = ?', $department);
        }

        return $connection->fetchAll($select);
    }

    /**
     * Obtém atendente por ID
     */
    public function getAttendantById(int $attendantId): ?array
    {
        $connection = $this->resource->getConnection();
        $table = $this->resource->getTableName(self::TABLE_ATTENDANTS);

        $select = $connection->select()
            ->from($table)
            ->where('attendant_id = ?', $attendantId);

        $result = $connection->fetchRow($select);
        return $result ?: null;
    }

    /**
     * Obtém atendente responsável por um cliente
     */
    public function getCustomerAttendant(int $customerId): ?array
    {
        $connection = $this->resource->getConnection();
        $tableMap = $this->resource->getTableName(self::TABLE_CUSTOMER_ATTENDANT);
        $tableAttendant = $this->resource->getTableName(self::TABLE_ATTENDANTS);

        $select = $connection->select()
            ->from(['ca' => $tableMap])
            ->join(
                ['a' => $tableAttendant],
                'ca.attendant_id = a.attendant_id',
                ['name', 'email', 'phone', 'whatsapp', 'department', 'is_active']
            )
            ->where('ca.customer_id = ?', $customerId)
            ->where('a.is_active = ?', 1);

        $result = $connection->fetchRow($select);
        return $result ?: null;
    }

    /**
     * Atribui cliente a um atendente
     */
    public function assignCustomerToAttendant(int $customerId, int $attendantId, ?string $reason = null): bool
    {
        $connection = $this->resource->getConnection();
        $tableMap = $this->resource->getTableName(self::TABLE_CUSTOMER_ATTENDANT);
        $tableLog = $this->resource->getTableName(self::TABLE_ATTENDANT_LOG);

        try {
            // Remove atribuição anterior se existir
            $oldAttendant = $this->getCustomerAttendant($customerId);

            $connection->insertOnDuplicate($tableMap, [
                'customer_id' => $customerId,
                'attendant_id' => $attendantId,
                'assigned_at' => date('Y-m-d H:i:s')
            ], ['attendant_id', 'assigned_at']);

            // Registra log
            $connection->insert($tableLog, [
                'customer_id' => $customerId,
                'attendant_id' => $attendantId,
                'previous_attendant_id' => $oldAttendant['attendant_id'] ?? null,
                'action' => 'assigned',
                'reason' => $reason,
                'created_at' => date('Y-m-d H:i:s')
            ]);

            // Atualiza contador do atendente
            $this->updateAttendantCustomerCount($attendantId);
            if ($oldAttendant) {
                $this->updateAttendantCustomerCount((int) $oldAttendant['attendant_id']);
            }

            return true;
        } catch (\Exception $e) {
            $this->logger->error('Error assigning customer to attendant: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Atribui cliente automaticamente ao atendente com menos clientes
     */
    public function autoAssignCustomer(int $customerId, ?string $department = null): ?int
    {
        $attendant = $this->getAttendantWithLeastCustomers($department);

        if ($attendant) {
            $this->assignCustomerToAttendant(
                $customerId,
                (int) $attendant['attendant_id'],
                'Atribuição automática - distribuição equilibrada'
            );
            return (int) $attendant['attendant_id'];
        }

        return null;
    }

    /**
     * Obtém atendente com menos clientes ativos
     */
    public function getAttendantWithLeastCustomers(?string $department = null): ?array
    {
        $connection = $this->resource->getConnection();
        $table = $this->resource->getTableName(self::TABLE_ATTENDANTS);

        $select = $connection->select()
            ->from($table)
            ->where('is_active = ?', 1)
            ->order('customer_count ASC')
            ->limit(1);

        if ($department) {
            $select->where('department = ?', $department);
        }

        $result = $connection->fetchRow($select);
        return $result ?: null;
    }

    /**
     * Obtém clientes de um atendente
     */
    public function getAttendantCustomers(int $attendantId, int $limit = 100, int $offset = 0): array
    {
        $connection = $this->resource->getConnection();
        $tableMap = $this->resource->getTableName(self::TABLE_CUSTOMER_ATTENDANT);

        $select = $connection->select()
            ->from($tableMap)
            ->where('attendant_id = ?', $attendantId)
            ->order('assigned_at DESC')
            ->limit($limit, $offset);

        return $connection->fetchAll($select);
    }

    /**
     * Conta clientes de um atendente
     */
    public function countAttendantCustomers(int $attendantId): int
    {
        $connection = $this->resource->getConnection();
        $tableMap = $this->resource->getTableName(self::TABLE_CUSTOMER_ATTENDANT);

        $select = $connection->select()
            ->from($tableMap, ['count' => new \Magento\Framework\DB\Sql\Expression('COUNT(*)')])
            ->where('attendant_id = ?', $attendantId);

        return (int) $connection->fetchOne($select);
    }

    /**
     * Atualiza contador de clientes do atendente
     */
    public function updateAttendantCustomerCount(int $attendantId): void
    {
        $count = $this->countAttendantCustomers($attendantId);

        $connection = $this->resource->getConnection();
        $table = $this->resource->getTableName(self::TABLE_ATTENDANTS);

        $connection->update(
            $table,
            ['customer_count' => $count, 'updated_at' => date('Y-m-d H:i:s')],
            ['attendant_id = ?' => $attendantId]
        );
    }

    /**
     * Cria ou atualiza atendente
     */
    public function saveAttendant(array $data): int
    {
        $connection = $this->resource->getConnection();
        $table = $this->resource->getTableName(self::TABLE_ATTENDANTS);

        $attendantData = [
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'whatsapp' => $data['whatsapp'] ?? null,
            'department' => $data['department'] ?? 'sales',
            'is_active' => $data['is_active'] ?? 1,
            'max_customers' => $data['max_customers'] ?? 100,
            'updated_at' => date('Y-m-d H:i:s')
        ];

        if (!empty($data['attendant_id'])) {
            $connection->update($table, $attendantData, ['attendant_id = ?' => $data['attendant_id']]);
            return (int) $data['attendant_id'];
        } else {
            $attendantData['created_at'] = date('Y-m-d H:i:s');
            $attendantData['customer_count'] = 0;
            $connection->insert($table, $attendantData);
            return (int) $connection->lastInsertId($table);
        }
    }

    /**
     * Remove atendente (soft delete - desativa)
     */
    public function deactivateAttendant(int $attendantId, ?int $transferToId = null): bool
    {
        $connection = $this->resource->getConnection();
        $table = $this->resource->getTableName(self::TABLE_ATTENDANTS);

        try {
            // Transfere clientes se especificado
            if ($transferToId) {
                $this->transferAllCustomers($attendantId, $transferToId);
            }

            // Desativa atendente
            $connection->update(
                $table,
                ['is_active' => 0, 'updated_at' => date('Y-m-d H:i:s')],
                ['attendant_id = ?' => $attendantId]
            );

            return true;
        } catch (\Exception $e) {
            $this->logger->error('Error deactivating attendant: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Transfere todos os clientes de um atendente para outro
     * Usa batch UPDATE e insertMultiple para performance
     */
    public function transferAllCustomers(int $fromAttendantId, int $toAttendantId): int
    {
        $connection = $this->resource->getConnection();
        $tableMap = $this->resource->getTableName(self::TABLE_CUSTOMER_ATTENDANT);
        $tableLog = $this->resource->getTableName(self::TABLE_ATTENDANT_LOG);
        $now = date('Y-m-d H:i:s');

        // Obtém IDs dos clientes antes do batch update
        $select = $connection->select()
            ->from($tableMap, ['customer_id'])
            ->where('attendant_id = ?', $fromAttendantId);
        $customerIds = $connection->fetchCol($select);

        if (empty($customerIds)) {
            return 0;
        }

        // Batch UPDATE — uma query para todos
        $connection->update(
            $tableMap,
            ['attendant_id' => $toAttendantId, 'assigned_at' => $now],
            ['attendant_id = ?' => $fromAttendantId]
        );

        // Batch INSERT de logs
        $logData = [];
        foreach ($customerIds as $customerId) {
            $logData[] = [
                'customer_id' => (int) $customerId,
                'attendant_id' => $toAttendantId,
                'previous_attendant_id' => $fromAttendantId,
                'action' => 'transferred',
                'reason' => 'Transferência em massa',
                'created_at' => $now
            ];
        }
        $connection->insertMultiple($tableLog, $logData);

        // Atualiza contadores
        $this->updateAttendantCustomerCount($fromAttendantId);
        $this->updateAttendantCustomerCount($toAttendantId);

        return count($customerIds);
    }

    /**
     * Obtém estatísticas de um atendente
     */
    public function getAttendantStats(int $attendantId, int $days = 30): array
    {
        $connection = $this->resource->getConnection();
        $tableLog = $this->resource->getTableName(self::TABLE_ATTENDANT_LOG);

        $startDate = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        // Novos clientes atribuídos
        $selectNew = $connection->select()
            ->from($tableLog, ['count' => new \Magento\Framework\DB\Sql\Expression('COUNT(*)')])
            ->where('attendant_id = ?', $attendantId)
            ->where('action = ?', 'assigned')
            ->where('created_at >= ?', $startDate);

        $newCustomers = (int) $connection->fetchOne($selectNew);

        // Total de clientes atual
        $totalCustomers = $this->countAttendantCustomers($attendantId);

        return [
            'total_customers' => $totalCustomers,
            'new_customers_period' => $newCustomers,
            'period_days' => $days
        ];
    }

    /**
     * Obtém resumo de todos os atendentes
     */
    public function getAttendantsSummary(?string $department = null): array
    {
        $attendants = $this->getActiveAttendants($department);
        $summary = [];

        foreach ($attendants as $attendant) {
            $stats = $this->getAttendantStats((int) $attendant['attendant_id']);
            $summary[] = array_merge($attendant, $stats);
        }

        return $summary;
    }

    /**
     * Redistribui clientes equilibradamente entre atendentes
     * Usa batch UPDATE para evitar carregar todos os clientes em memória
     */
    public function redistributeCustomers(?string $department = null): array
    {
        $attendants = $this->getActiveAttendants($department);

        if (count($attendants) < 2) {
            return ['redistributed' => 0, 'message' => 'Mínimo de 2 atendentes necessários'];
        }

        $connection = $this->resource->getConnection();
        $tableMap = $this->resource->getTableName(self::TABLE_CUSTOMER_ATTENDANT);
        $tableLog = $this->resource->getTableName(self::TABLE_ATTENDANT_LOG);

        // Conta total de clientes sem carregar tudo em memória
        $countSelect = $connection->select()->from($tableMap, ['count' => new \Magento\Framework\DB\Sql\Expression('COUNT(*)')]);
        $totalCustomers = (int) $connection->fetchOne($countSelect);

        if ($totalCustomers === 0) {
            return ['redistributed' => 0, 'total_customers' => 0, 'attendants' => count($attendants)];
        }

        $attendantCount = count($attendants);
        $perAttendant = (int) ceil($totalCustomers / $attendantCount);
        $redistributed = 0;
        $batchSize = 500;
        $now = date('Y-m-d H:i:s');

        // Processa em batches usando LIMIT/OFFSET com ORDER BY RAND()
        foreach ($attendants as $index => $attendant) {
            $attendantId = (int) $attendant['attendant_id'];
            $offset = 0;

            // Calcula quantos clientes este atendente deve ter
            $targetCount = ($index === $attendantCount - 1)
                ? $totalCustomers - ($perAttendant * $index)
                : $perAttendant;

            // Processa em batches
            while ($offset < $targetCount) {
                $batchLimit = min($batchSize, $targetCount - $offset);

                // Seleciona clientes que NÃO estão com este atendente, em batches
                $select = $connection->select()
                    ->from($tableMap, ['customer_id'])
                    ->where('attendant_id != ?', $attendantId)
                    ->limit($batchLimit, 0);

                $customerIds = $connection->fetchCol($select);

                if (empty($customerIds)) {
                    break;
                }

                // Batch UPDATE
                $connection->update(
                    $tableMap,
                    ['attendant_id' => $attendantId, 'assigned_at' => $now],
                    ['customer_id IN (?)' => $customerIds]
                );

                // Log batch (single multi-row insert)
                $logData = [];
                foreach ($customerIds as $customerId) {
                    $logData[] = [
                        'customer_id' => (int) $customerId,
                        'attendant_id' => $attendantId,
                        'previous_attendant_id' => null,
                        'action' => 'redistributed',
                        'reason' => 'Redistribuição equilibrada (batch)',
                        'created_at' => $now
                    ];
                }
                if (!empty($logData)) {
                    $connection->insertMultiple($tableLog, $logData);
                }

                $redistributed += count($customerIds);
                $offset += $batchLimit;
            }
        }

        // Atualiza contadores de todos os atendentes de uma vez
        foreach ($attendants as $attendant) {
            $this->updateAttendantCustomerCount((int) $attendant['attendant_id']);
        }

        return [
            'redistributed' => $redistributed,
            'total_customers' => $totalCustomers,
            'attendants' => $attendantCount,
            'target_per_attendant' => $perAttendant
        ];
    }
}
