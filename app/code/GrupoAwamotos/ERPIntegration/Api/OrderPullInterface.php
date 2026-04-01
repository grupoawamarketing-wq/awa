<?php

declare(strict_types=1);

namespace GrupoAwamotos\ERPIntegration\Api;

/**
 * REST API for ERP to PULL orders from Magento
 *
 * Endpoints:
 * - GET  /V1/erp/orders/pending          List orders not yet synced to ERP
 * - GET  /V1/erp/orders/:incrementId     Get full order details with ERP data
 * - POST /V1/erp/orders/:incrementId/ack ERP acknowledges receipt
 */
interface OrderPullInterface
{
    /**
     * Get list of orders pending sync to ERP.
     *
     * @param int $limit Maximum number of orders to return
     * @param string|null $fromDate Optional ISO date filter (orders after this date)
     * @return mixed[]
     */
    public function getPendingOrders(int $limit = 50, ?string $fromDate = null): array;

    /**
     * Get full order details with ERP-enriched data.
     *
     * @param string $incrementId Magento order increment ID
     * @return mixed[]
     */
    public function getOrderDetails(string $incrementId): array;

    /**
     * ERP acknowledges receipt of an order.
     *
     * @param string $incrementId Magento order increment ID
     * @param string $erpOrderId The ID assigned by the ERP
     * @param string|null $message Optional message from ERP
     * @return mixed[]
     */
    public function acknowledgeOrder(
        string $incrementId,
        string $erpOrderId,
        ?string $message = null
    ): array;

    /**
     * Get canceled orders that were previously pending for ERP.
     *
     * Returns orders that were placed but canceled before ERP pull.
     *
     * @param string|null $fromDate Optional ISO date filter
     * @return mixed[]
     */
    public function getCanceledOrders(?string $fromDate = null): array;

    /**
     * Get orders held back from ERP due to unregistered clients.
     *
     * These orders exist in Magento but cannot be imported by Sectra because
     * the client is not registered in GR_INTEGRACAOVALIDADOR.
     *
     * @param int $limit Maximum number of orders to return
     * @return mixed[]
     */
    public function getHeldOrders(int $limit = 50): array;
}
