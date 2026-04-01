<?php

declare(strict_types=1);

namespace GrupoAwamotos\ERPIntegration\Api;

interface OrderSyncInterface
{
    /**
     * Envia um pedido do Magento para o ERP
     *
     * @param \Magento\Sales\Api\Data\OrderInterface $order
     * @return array Resultado [success, erp_order_id, message, items_synced, execution_time]
     */
    public function sendOrder(\Magento\Sales\Api\Data\OrderInterface $order): array;

    /**
     * Obtém histórico de pedidos de um cliente no ERP
     *
     * @param int $erpClientCode Código do cliente no ERP
     * @param int $limit Limite de pedidos
     * @return array Lista de pedidos
     */
    public function getOrderHistory(int $erpClientCode, int $limit = 50): array;

    /**
     * Sincroniza status de pedidos do ERP para o Magento
     * Busca pedidos com status atualizado no ERP e atualiza no Magento
     *
     * @return array Resultado [synced, errors, skipped]
     */
    public function syncOrderStatuses(): array;

    /**
     * Obtém o status de um pedido específico no ERP
     *
     * @param int $erpOrderId ID do pedido no ERP
     * @return array|null Dados do status ou null se não encontrado
     */
    public function getErpOrderStatus(int $erpOrderId): ?array;

    /**
     * Sincroniza informações de rastreamento do ERP para o Magento
     *
     * @param int $magentoOrderId ID do pedido no Magento
     * @return bool True se sincronizado com sucesso
     */
    public function syncOrderTracking(int $magentoOrderId): bool;

    /**
     * Obtém informações de faturamento (NF-e) do pedido no ERP
     *
     * @param int $erpOrderId ID do pedido no ERP
     * @return array|null Dados da NF-e ou null se não encontrada
     */
    public function getOrderInvoiceData(int $erpOrderId): ?array;

    /**
     * Atualiza status de um pedido específico baseado no ERP
     *
     * @param int $magentoOrderId ID do pedido no Magento
     * @return array Resultado [success, message, new_status]
     */
    public function updateOrderStatus(int $magentoOrderId): array;
}
