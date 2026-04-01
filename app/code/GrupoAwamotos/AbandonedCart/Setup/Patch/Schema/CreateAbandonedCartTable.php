<?php

declare(strict_types=1);

namespace GrupoAwamotos\AbandonedCart\Setup\Patch\Schema;

use Magento\Framework\Setup\Patch\SchemaPatchInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\DB\Ddl\Table;

class CreateAbandonedCartTable implements SchemaPatchInterface
{
    private SchemaSetupInterface $schemaSetup;

    public function __construct(SchemaSetupInterface $schemaSetup)
    {
        $this->schemaSetup = $schemaSetup;
    }

    public function apply()
    {
        $this->schemaSetup->startSetup();

        $tableName = $this->schemaSetup->getTable('grupoawamotos_abandoned_cart');

        if (!$this->schemaSetup->getConnection()->isTableExists($tableName)) {
            $table = $this->schemaSetup->getConnection()
                ->newTable($tableName)
                ->addColumn(
                    'entity_id',
                    Table::TYPE_INTEGER,
                    null,
                    ['identity' => true, 'unsigned' => true, 'nullable' => false, 'primary' => true],
                    'Entity ID'
                )
                ->addColumn(
                    'quote_id',
                    Table::TYPE_INTEGER,
                    null,
                    ['unsigned' => true, 'nullable' => false],
                    'Quote ID'
                )
                ->addColumn(
                    'customer_id',
                    Table::TYPE_INTEGER,
                    null,
                    ['unsigned' => true, 'nullable' => true],
                    'Customer ID'
                )
                ->addColumn(
                    'customer_email',
                    Table::TYPE_TEXT,
                    255,
                    ['nullable' => false],
                    'Customer Email'
                )
                ->addColumn(
                    'customer_name',
                    Table::TYPE_TEXT,
                    255,
                    ['nullable' => true],
                    'Customer Name'
                )
                ->addColumn(
                    'store_id',
                    Table::TYPE_SMALLINT,
                    null,
                    ['unsigned' => true, 'nullable' => false, 'default' => '0'],
                    'Store ID'
                )
                ->addColumn(
                    'cart_value',
                    Table::TYPE_DECIMAL,
                    '12,4',
                    ['nullable' => false, 'default' => '0.0000'],
                    'Cart Value'
                )
                ->addColumn(
                    'items_count',
                    Table::TYPE_INTEGER,
                    null,
                    ['unsigned' => true, 'nullable' => false, 'default' => '0'],
                    'Items Count'
                )
                ->addColumn(
                    'abandoned_at',
                    Table::TYPE_TIMESTAMP,
                    null,
                    ['nullable' => false, 'default' => Table::TIMESTAMP_INIT],
                    'Abandoned At'
                )
                ->addColumn(
                    'email_1_sent',
                    Table::TYPE_SMALLINT,
                    null,
                    ['unsigned' => true, 'nullable' => false, 'default' => '0'],
                    'Email 1 Sent'
                )
                ->addColumn(
                    'email_1_sent_at',
                    Table::TYPE_TIMESTAMP,
                    null,
                    ['nullable' => true],
                    'Email 1 Sent At'
                )
                ->addColumn(
                    'email_2_sent',
                    Table::TYPE_SMALLINT,
                    null,
                    ['unsigned' => true, 'nullable' => false, 'default' => '0'],
                    'Email 2 Sent'
                )
                ->addColumn(
                    'email_2_sent_at',
                    Table::TYPE_TIMESTAMP,
                    null,
                    ['nullable' => true],
                    'Email 2 Sent At'
                )
                ->addColumn(
                    'email_3_sent',
                    Table::TYPE_SMALLINT,
                    null,
                    ['unsigned' => true, 'nullable' => false, 'default' => '0'],
                    'Email 3 Sent'
                )
                ->addColumn(
                    'email_3_sent_at',
                    Table::TYPE_TIMESTAMP,
                    null,
                    ['nullable' => true],
                    'Email 3 Sent At'
                )
                ->addColumn(
                    'recovered',
                    Table::TYPE_SMALLINT,
                    null,
                    ['unsigned' => true, 'nullable' => false, 'default' => '0'],
                    'Recovered'
                )
                ->addColumn(
                    'recovered_at',
                    Table::TYPE_TIMESTAMP,
                    null,
                    ['nullable' => true],
                    'Recovered At'
                )
                ->addColumn(
                    'coupon_code',
                    Table::TYPE_TEXT,
                    50,
                    ['nullable' => true],
                    'Coupon Code'
                )
                ->addColumn(
                    'status',
                    Table::TYPE_TEXT,
                    20,
                    ['nullable' => false, 'default' => 'pending'],
                    'Status'
                )
                ->addColumn(
                    'created_at',
                    Table::TYPE_TIMESTAMP,
                    null,
                    ['nullable' => false, 'default' => Table::TIMESTAMP_INIT],
                    'Created At'
                )
                ->addColumn(
                    'updated_at',
                    Table::TYPE_TIMESTAMP,
                    null,
                    ['nullable' => false, 'default' => Table::TIMESTAMP_INIT_UPDATE],
                    'Updated At'
                )
                ->addIndex(
                    $this->schemaSetup->getIdxName($tableName, ['quote_id']),
                    ['quote_id'],
                    ['type' => 'unique']
                )
                ->addIndex(
                    $this->schemaSetup->getIdxName($tableName, ['customer_email']),
                    ['customer_email']
                )
                ->addIndex(
                    $this->schemaSetup->getIdxName($tableName, ['customer_id']),
                    ['customer_id']
                )
                ->addIndex(
                    $this->schemaSetup->getIdxName($tableName, ['store_id']),
                    ['store_id']
                )
                ->addIndex(
                    $this->schemaSetup->getIdxName($tableName, ['status']),
                    ['status']
                )
                ->addIndex(
                    $this->schemaSetup->getIdxName($tableName, ['abandoned_at']),
                    ['abandoned_at']
                )
                ->addIndex(
                    $this->schemaSetup->getIdxName($tableName, ['recovered']),
                    ['recovered']
                )
                ->setComment('Grupo Awamotos Abandoned Cart');

            $this->schemaSetup->getConnection()->createTable($table);
        }

        $this->schemaSetup->endSetup();

        return $this;
    }

    public static function getDependencies()
    {
        return [];
    }

    public function getAliases()
    {
        return [];
    }
}
