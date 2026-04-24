<?php

/**
 * Block para o Dashboard do Atendente B2B.
 * Fornece dados personalizados do atendente logado.
 */

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Block\Adminhtml\Attendant;

use GrupoAwamotos\B2B\Helper\CurrentAttendant;
use GrupoAwamotos\B2B\Model\Attendant\AttendantManager;
use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\App\ResourceConnection;

class Dashboard extends Template
{
    private CurrentAttendant $currentAttendant;
    private AttendantManager $attendantManager;
    private ResourceConnection $resource;

    public function __construct(
        Context $context,
        CurrentAttendant $currentAttendant,
        AttendantManager $attendantManager,
        ResourceConnection $resource,
        array $data = []
    ) {
        $this->currentAttendant = $currentAttendant;
        $this->attendantManager = $attendantManager;
        $this->resource = $resource;
        parent::__construct($context, $data);
    }

    /**
     * Retorna dados do atendente logado.
     *
     * @return array<string, mixed>|null
     */
    public function getAttendant(): ?array
    {
        return $this->currentAttendant->get();
    }

    public function isAttendant(): bool
    {
        return $this->currentAttendant->isAttendant();
    }

    /**
     * Retorna IDs dos clientes atribuídos ao atendente.
     *
     * @return int[]
     */
    public function getMyCustomerIds(): array
    {
        $attId = $this->currentAttendant->getId();
        if (!$attId) {
            return [];
        }

        $connection = $this->resource->getConnection();
        $table = $this->resource->getTableName('grupoawamotos_b2b_customer_attendant');

        return array_map('intval', $connection->fetchCol(
            $connection->select()->from($table, ['customer_id'])->where('attendant_id = ?', $attId)
        ));
    }

    /**
     * Clientes recentes do atendente (últimos 10).
     *
     * @return array<int, array<string, mixed>>
     */
    public function getRecentCustomers(int $limit = 10): array
    {
        $attId = $this->currentAttendant->getId();
        if (!$attId) {
            return [];
        }

        return $this->attendantManager->getAttendantCustomers($attId, $limit);
    }

    /**
     * Pedidos recentes dos clientes do atendente.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getRecentOrders(int $limit = 15): array
    {
        $customerIds = $this->getMyCustomerIds();
        if (empty($customerIds)) {
            return [];
        }

        $connection = $this->resource->getConnection();
        $select = $connection->select()
            ->from(
                ['o' => $this->resource->getTableName('sales_order')],
                [
                    'entity_id', 'increment_id', 'customer_id',
                    'customer_firstname', 'customer_lastname', 'customer_email',
                    'grand_total', 'status', 'created_at',
                ]
            )
            ->where('o.customer_id IN (?)', $customerIds)
            ->order('o.created_at DESC')
            ->limit($limit);

        return $connection->fetchAll($select);
    }

    /**
     * Cotações pendentes dos clientes do atendente.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getPendingQuotes(int $limit = 10): array
    {
        $customerIds = $this->getMyCustomerIds();
        if (empty($customerIds)) {
            return [];
        }

        $connection = $this->resource->getConnection();
        $table = $this->resource->getTableName('grupoawamotos_b2b_quote_request');

        if (!$connection->isTableExists($table)) {
            return [];
        }

        $select = $connection->select()
            ->from($table)
            ->where('customer_id IN (?)', $customerIds)
            ->where('status = ?', 'pending')
            ->order('created_at DESC')
            ->limit($limit);

        return $connection->fetchAll($select);
    }

    /**
     * Métricas resumidas do atendente.
     *
     * @return array<string, mixed>
     */
    public function getMetrics(): array
    {
        $attId = $this->currentAttendant->getId();
        $att = $this->getAttendant();
        if (!$attId || !$att) {
            return [];
        }

        $connection = $this->resource->getConnection();
        $customerIds = $this->getMyCustomerIds();
        $now = new \DateTime();
        $thirtyDaysAgo = (clone $now)->modify('-30 days')->format('Y-m-d H:i:s');

        // Pedidos dos meus clientes (últimos 30 dias)
        $ordersCount = 0;
        $ordersTotal = 0.0;
        if (!empty($customerIds)) {
            $orderTable = $this->resource->getTableName('sales_order');
            $row = $connection->fetchRow(
                $connection->select()
                    ->from($orderTable, [
                        'cnt' => new \Magento\Framework\DB\Sql\Expression('COUNT(*)'),
                        'total' => new \Magento\Framework\DB\Sql\Expression('COALESCE(SUM(grand_total), 0)'),
                    ])
                    ->where('customer_id IN (?)', $customerIds)
                    ->where('created_at >= ?', $thirtyDaysAgo)
            );
            $ordersCount = (int) ($row['cnt'] ?? 0);
            $ordersTotal = (float) ($row['total'] ?? 0);
        }

        // Novos clientes atribuídos (últimos 30 dias)
        $logTable = $this->resource->getTableName('grupoawamotos_b2b_attendant_log');
        $newCustomers = (int) $connection->fetchOne(
            $connection->select()
                ->from($logTable, ['cnt' => new \Magento\Framework\DB\Sql\Expression('COUNT(*)')])
                ->where('attendant_id = ?', $attId)
                ->where('action = ?', 'assigned')
                ->where('created_at >= ?', $thirtyDaysAgo)
        );

        return [
            'customer_count' => (int) $att['customer_count'],
            'max_customers' => (int) $att['max_customers'],
            'capacity_pct' => (int) $att['max_customers'] > 0
                ? round(((int) $att['customer_count'] / (int) $att['max_customers']) * 100)
                : 0,
            'orders_30d' => $ordersCount,
            'orders_total_30d' => $ordersTotal,
            'new_customers_30d' => $newCustomers,
        ];
    }


    /**
     * Follow-ups pendentes/vencidos do atendente.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getPendingFollowups(int $limit = 10): array
    {
        $attId = $this->currentAttendant->getId();
        if (!$attId) {
            return [];
        }

        $connection = $this->resource->getConnection();
        $table = $this->resource->getTableName('grupoawamotos_b2b_followup');
        $today = date('Y-m-d 23:59:59');

        $select = $connection->select()
            ->from(['f' => $table])
            ->joinLeft(
                ['c' => $this->resource->getTableName('customer_entity')],
                'c.entity_id = f.customer_id',
                ['customer_firstname' => 'c.firstname', 'customer_lastname' => 'c.lastname', 'customer_email' => 'c.email']
            )
            ->where('f.attendant_id = ?', $attId)
            ->where('f.status != ?', 'done')
            ->where('(f.next_contact_at IS NULL OR f.next_contact_at <= ?)', $today)
            ->order('f.next_contact_at ASC')
            ->limit($limit);

        return $connection->fetchAll($select);
    }

    /**
     * Carrinhos abandonados dos clientes do atendente.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAbandonedCarts(int $limit = 10): array
    {
        $attId = $this->currentAttendant->getId();
        if (!$attId) {
            return [];
        }

        $connection = $this->resource->getConnection();
        $table = $this->resource->getTableName('grupoawamotos_abandoned_cart');

        if (!$connection->isTableExists($table)) {
            return [];
        }

        $select = $connection->select()
            ->from($table)
            ->where('attendant_id = ?', $attId)
            ->where('status != ?', 'recovered')
            ->order('abandoned_at DESC')
            ->limit($limit);

        return $connection->fetchAll($select);
    }

    /**
     * URL para salvar follow-up via AJAX.
     */
    public function getFollowupSaveUrl(): string
    {
        return $this->getUrl('grupoawamotos_b2b/followup/save');
    }

    /**
     * URL para listar follow-ups via AJAX.
     */
    public function getFollowupListUrl(?int $customerId = null): string
    {
        $params = [];
        if ($customerId) {
            $params['customer_id'] = $customerId;
        }
        return $this->getUrl('grupoawamotos_b2b/followup/listAction', $params);
    }

    /**
     * URL da gestão de atendentes.
     */
    public function getAttendantListUrl(): string
    {
        return $this->getUrl('grupoawamotos_b2b/attendant/index');
    }

    /**
     * URL da ajuda.
     */
    public function getHelpUrl(): string
    {
        return $this->getUrl('grupoawamotos_b2b/attendant/help');
    }

    /**
     * URL do pedido.
     */
    public function getOrderViewUrl(int $orderId): string
    {
        return $this->getUrl('sales/order/view', ['order_id' => $orderId]);
    }

    /**
     * URL do cliente.
     */
    public function getCustomerViewUrl(int $customerId): string
    {
        return $this->getUrl('customer/index/edit', ['id' => $customerId]);
    }
}
