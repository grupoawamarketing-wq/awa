<?php

declare(strict_types=1);

namespace GrupoAwamotos\ERPIntegration\Model;

use GrupoAwamotos\ERPIntegration\Api\ConnectionInterface;
use GrupoAwamotos\ERPIntegration\Helper\Data as Helper;
use GrupoAwamotos\ERPIntegration\Model\ResourceModel\SyncLog as SyncLogResource;
use Psr\Log\LoggerInterface;

class OrderHistory
{
    private ConnectionInterface $connection;
    private Helper $helper;
    private SyncLogResource $syncLogResource;
    private LoggerInterface $logger;

    public function __construct(
        ConnectionInterface $connection,
        Helper $helper,
        SyncLogResource $syncLogResource,
        LoggerInterface $logger
    ) {
        $this->connection = $connection;
        $this->helper = $helper;
        $this->syncLogResource = $syncLogResource;
        $this->logger = $logger;
    }

    public function getProductSuggestions(int $erpClientCode): array
    {
        if (!$this->helper->isSuggestionsEnabled()) {
            return [];
        }

        $maxSuggestions = $this->helper->getMaxSuggestions();

        try {
            $sql = "SELECT pi.MATERIAL AS sku,
                        m.DESCRICAO AS name,
                        SUM(pi.QTDE) AS total_qty,
                        COUNT(DISTINCT p.CODIGO) AS total_orders,
                        MAX(p.DTPEDIDO) AS last_order_date,
                        AVG(pi.VLRUNITARIO) AS avg_price
                    FROM VE_PEDIDO p
                    JOIN VE_PEDIDOITENS pi ON pi.PEDIDO = p.CODIGO
                    JOIN MT_MATERIAL m ON m.CODIGO = pi.MATERIAL
                    WHERE p.CLIENTE = :cliente
                      AND p.STATUS NOT IN ('C', 'X')
                      AND m.CCKATIVO = 'S'
                    GROUP BY pi.MATERIAL, m.DESCRICAO
                    ORDER BY total_orders DESC, last_order_date DESC
                    OFFSET 0 ROWS FETCH NEXT :limit ROWS ONLY";

            return $this->connection->query($sql, [
                ':limit' => $maxSuggestions,
                ':cliente' => $erpClientCode,
            ]);
        } catch (\Exception $e) {
            $this->logger->error('[ERP] Product suggestions error: ' . $e->getMessage());
            return [];
        }
    }

    public function getReorderSuggestions(int $erpClientCode): array
    {
        if (!$this->helper->isSuggestionsEnabled()) {
            return [];
        }

        try {
            $sql = "SELECT pi.MATERIAL AS sku,
                        m.DESCRICAO AS name,
                        pi.QTDE AS last_qty,
                        pi.VLRUNITARIO AS last_price,
                        p.DTPEDIDO AS last_order_date,
                        p.CODIGO AS last_order_id
                    FROM VE_PEDIDOITENS pi
                    JOIN VE_PEDIDO p ON p.CODIGO = pi.PEDIDO
                    JOIN MT_MATERIAL m ON m.CODIGO = pi.MATERIAL
                    WHERE p.CODIGO = (
                        SELECT CODIGO FROM VE_PEDIDO
                        WHERE CLIENTE = :cliente AND STATUS NOT IN ('C', 'X')
                        ORDER BY DTPEDIDO DESC
                        OFFSET 0 ROWS FETCH NEXT 1 ROWS ONLY
                    )
                    AND m.CCKATIVO = 'S'
                    ORDER BY pi.VLRTOTAL DESC
                    OFFSET 0 ROWS FETCH NEXT :limit ROWS ONLY";

            return $this->connection->query($sql, [
                ':limit' => $this->helper->getMaxSuggestions(),
                ':cliente' => $erpClientCode,
            ]);
        } catch (\Exception $e) {
            $this->logger->error('[ERP] Reorder suggestions error: ' . $e->getMessage());
            return [];
        }
    }

    public function getErpClientCodeByCustomerId(int $customerId): ?int
    {
        $erpCode = $this->syncLogResource->getErpCodeByMagentoId('customer', $customerId);
        return $erpCode ? (int) $erpCode : null;
    }
}
