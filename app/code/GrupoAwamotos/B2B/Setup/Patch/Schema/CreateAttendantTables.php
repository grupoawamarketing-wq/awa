<?php

/**
 * Cria tabelas para gestão de atendentes B2B
 */

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Setup\Patch\Schema;

use Magento\Framework\Setup\Patch\SchemaPatchInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\DB\Ddl\Table;

class CreateAttendantTables implements SchemaPatchInterface
{
    private SchemaSetupInterface $schemaSetup;

    public function __construct(SchemaSetupInterface $schemaSetup)
    {
        $this->schemaSetup = $schemaSetup;
    }

    public function apply(): self
    {
        $setup = $this->schemaSetup;
        $setup->startSetup();
        $connection = $setup->getConnection();

        // Tabela de Atendentes
        $tableName = $setup->getTable('grupoawamotos_b2b_attendants');
        if (!$connection->isTableExists($tableName)) {
            $table = $connection->newTable($tableName)
                ->addColumn(
                    'attendant_id',
                    Table::TYPE_INTEGER,
                    null,
                    ['identity' => true, 'unsigned' => true, 'nullable' => false, 'primary' => true],
                    'ID do Atendente'
                )
                ->addColumn(
                    'name',
                    Table::TYPE_TEXT,
                    255,
                    ['nullable' => false],
                    'Nome do Atendente'
                )
                ->addColumn(
                    'email',
                    Table::TYPE_TEXT,
                    255,
                    ['nullable' => false],
                    'Email'
                )
                ->addColumn(
                    'phone',
                    Table::TYPE_TEXT,
                    50,
                    ['nullable' => true],
                    'Telefone'
                )
                ->addColumn(
                    'whatsapp',
                    Table::TYPE_TEXT,
                    50,
                    ['nullable' => true],
                    'WhatsApp'
                )
                ->addColumn(
                    'department',
                    Table::TYPE_TEXT,
                    50,
                    ['nullable' => false, 'default' => 'sales'],
                    'Departamento (sales, support, b2b)'
                )
                ->addColumn(
                    'is_active',
                    Table::TYPE_SMALLINT,
                    null,
                    ['nullable' => false, 'default' => 1],
                    'Ativo'
                )
                ->addColumn(
                    'customer_count',
                    Table::TYPE_INTEGER,
                    null,
                    ['nullable' => false, 'default' => 0],
                    'Quantidade de Clientes'
                )
                ->addColumn(
                    'max_customers',
                    Table::TYPE_INTEGER,
                    null,
                    ['nullable' => false, 'default' => 100],
                    'Máximo de Clientes'
                )
                ->addColumn(
                    'admin_user_id',
                    Table::TYPE_INTEGER,
                    null,
                    ['unsigned' => true, 'nullable' => true],
                    'ID do Usuário Admin (se vinculado)'
                )
                ->addColumn(
                    'created_at',
                    Table::TYPE_TIMESTAMP,
                    null,
                    ['nullable' => false, 'default' => Table::TIMESTAMP_INIT],
                    'Data de Criação'
                )
                ->addColumn(
                    'updated_at',
                    Table::TYPE_TIMESTAMP,
                    null,
                    ['nullable' => false, 'default' => Table::TIMESTAMP_INIT_UPDATE],
                    'Data de Atualização'
                )
                ->addIndex(
                    $setup->getIdxName($tableName, ['email']),
                    ['email'],
                    ['type' => 'unique']
                )
                ->addIndex(
                    $setup->getIdxName($tableName, ['department', 'is_active']),
                    ['department', 'is_active']
                )
                ->setComment('Atendentes B2B');

            $connection->createTable($table);
        }

        // Tabela de Relacionamento Cliente-Atendente
        $tableMapName = $setup->getTable('grupoawamotos_b2b_customer_attendant');
        if (!$connection->isTableExists($tableMapName)) {
            $tableMap = $connection->newTable($tableMapName)
                ->addColumn(
                    'id',
                    Table::TYPE_INTEGER,
                    null,
                    ['identity' => true, 'unsigned' => true, 'nullable' => false, 'primary' => true],
                    'ID'
                )
                ->addColumn(
                    'customer_id',
                    Table::TYPE_INTEGER,
                    null,
                    ['unsigned' => true, 'nullable' => false],
                    'ID do Cliente'
                )
                ->addColumn(
                    'attendant_id',
                    Table::TYPE_INTEGER,
                    null,
                    ['unsigned' => true, 'nullable' => false],
                    'ID do Atendente'
                )
                ->addColumn(
                    'assigned_at',
                    Table::TYPE_TIMESTAMP,
                    null,
                    ['nullable' => false, 'default' => Table::TIMESTAMP_INIT],
                    'Data de Atribuição'
                )
                ->addColumn(
                    'notes',
                    Table::TYPE_TEXT,
                    null,
                    ['nullable' => true],
                    'Observações'
                )
                ->addIndex(
                    $setup->getIdxName($tableMapName, ['customer_id']),
                    ['customer_id'],
                    ['type' => 'unique']
                )
                ->addIndex(
                    $setup->getIdxName($tableMapName, ['attendant_id']),
                    ['attendant_id']
                )
                ->addForeignKey(
                    $setup->getFkName($tableMapName, 'attendant_id', 'grupoawamotos_b2b_attendants', 'attendant_id'),
                    'attendant_id',
                    $setup->getTable('grupoawamotos_b2b_attendants'),
                    'attendant_id',
                    Table::ACTION_CASCADE
                )
                ->setComment('Relacionamento Cliente-Atendente B2B');

            $connection->createTable($tableMap);
        }

        // Tabela de Log de Atendimento
        $tableLogName = $setup->getTable('grupoawamotos_b2b_attendant_log');
        if (!$connection->isTableExists($tableLogName)) {
            $tableLog = $connection->newTable($tableLogName)
                ->addColumn(
                    'log_id',
                    Table::TYPE_INTEGER,
                    null,
                    ['identity' => true, 'unsigned' => true, 'nullable' => false, 'primary' => true],
                    'ID do Log'
                )
                ->addColumn(
                    'customer_id',
                    Table::TYPE_INTEGER,
                    null,
                    ['unsigned' => true, 'nullable' => false],
                    'ID do Cliente'
                )
                ->addColumn(
                    'attendant_id',
                    Table::TYPE_INTEGER,
                    null,
                    ['unsigned' => true, 'nullable' => false],
                    'ID do Atendente'
                )
                ->addColumn(
                    'previous_attendant_id',
                    Table::TYPE_INTEGER,
                    null,
                    ['unsigned' => true, 'nullable' => true],
                    'ID do Atendente Anterior'
                )
                ->addColumn(
                    'action',
                    Table::TYPE_TEXT,
                    50,
                    ['nullable' => false],
                    'Ação (assigned, transferred, removed)'
                )
                ->addColumn(
                    'reason',
                    Table::TYPE_TEXT,
                    500,
                    ['nullable' => true],
                    'Motivo'
                )
                ->addColumn(
                    'admin_user_id',
                    Table::TYPE_INTEGER,
                    null,
                    ['unsigned' => true, 'nullable' => true],
                    'Admin que realizou a ação'
                )
                ->addColumn(
                    'created_at',
                    Table::TYPE_TIMESTAMP,
                    null,
                    ['nullable' => false, 'default' => Table::TIMESTAMP_INIT],
                    'Data'
                )
                ->addIndex(
                    $setup->getIdxName($tableLogName, ['customer_id']),
                    ['customer_id']
                )
                ->addIndex(
                    $setup->getIdxName($tableLogName, ['attendant_id']),
                    ['attendant_id']
                )
                ->addIndex(
                    $setup->getIdxName($tableLogName, ['created_at']),
                    ['created_at']
                )
                ->setComment('Log de Atendimento B2B');

            $connection->createTable($tableLog);
        }

        // Tabela de Notificações
        $tableNotifName = $setup->getTable('grupoawamotos_b2b_notifications');
        if (!$connection->isTableExists($tableNotifName)) {
            $tableNotif = $connection->newTable($tableNotifName)
                ->addColumn(
                    'notification_id',
                    Table::TYPE_INTEGER,
                    null,
                    ['identity' => true, 'unsigned' => true, 'nullable' => false, 'primary' => true],
                    'ID da Notificação'
                )
                ->addColumn(
                    'type',
                    Table::TYPE_TEXT,
                    50,
                    ['nullable' => false],
                    'Tipo (whatsapp, email, sms, push)'
                )
                ->addColumn(
                    'event',
                    Table::TYPE_TEXT,
                    100,
                    ['nullable' => false],
                    'Evento (new_registration, new_order, etc)'
                )
                ->addColumn(
                    'recipient',
                    Table::TYPE_TEXT,
                    255,
                    ['nullable' => false],
                    'Destinatário'
                )
                ->addColumn(
                    'recipient_name',
                    Table::TYPE_TEXT,
                    255,
                    ['nullable' => true],
                    'Nome do Destinatário'
                )
                ->addColumn(
                    'message',
                    Table::TYPE_TEXT,
                    null,
                    ['nullable' => false],
                    'Mensagem'
                )
                ->addColumn(
                    'status',
                    Table::TYPE_TEXT,
                    20,
                    ['nullable' => false, 'default' => 'pending'],
                    'Status (pending, sent, failed, delivered, read)'
                )
                ->addColumn(
                    'external_id',
                    Table::TYPE_TEXT,
                    255,
                    ['nullable' => true],
                    'ID Externo (do provider)'
                )
                ->addColumn(
                    'error_message',
                    Table::TYPE_TEXT,
                    null,
                    ['nullable' => true],
                    'Mensagem de Erro'
                )
                ->addColumn(
                    'entity_type',
                    Table::TYPE_TEXT,
                    50,
                    ['nullable' => true],
                    'Tipo de Entidade (customer, order, quote)'
                )
                ->addColumn(
                    'entity_id',
                    Table::TYPE_INTEGER,
                    null,
                    ['unsigned' => true, 'nullable' => true],
                    'ID da Entidade'
                )
                ->addColumn(
                    'sent_at',
                    Table::TYPE_TIMESTAMP,
                    null,
                    ['nullable' => true],
                    'Data de Envio'
                )
                ->addColumn(
                    'delivered_at',
                    Table::TYPE_TIMESTAMP,
                    null,
                    ['nullable' => true],
                    'Data de Entrega'
                )
                ->addColumn(
                    'read_at',
                    Table::TYPE_TIMESTAMP,
                    null,
                    ['nullable' => true],
                    'Data de Leitura'
                )
                ->addColumn(
                    'created_at',
                    Table::TYPE_TIMESTAMP,
                    null,
                    ['nullable' => false, 'default' => Table::TIMESTAMP_INIT],
                    'Data de Criação'
                )
                ->addIndex(
                    $setup->getIdxName($tableNotifName, ['type', 'status']),
                    ['type', 'status']
                )
                ->addIndex(
                    $setup->getIdxName($tableNotifName, ['event']),
                    ['event']
                )
                ->addIndex(
                    $setup->getIdxName($tableNotifName, ['entity_type', 'entity_id']),
                    ['entity_type', 'entity_id']
                )
                ->addIndex(
                    $setup->getIdxName($tableNotifName, ['created_at']),
                    ['created_at']
                )
                ->setComment('Notificações B2B');

            $connection->createTable($tableNotif);
        }

        $setup->endSetup();
        return $this;
    }

    public static function getDependencies(): array
    {
        return [];
    }

    public function getAliases(): array
    {
        return [];
    }
}
