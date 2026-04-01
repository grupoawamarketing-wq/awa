<?php

declare(strict_types=1);

namespace GrupoAwamotos\ERPIntegration\Model\Dashboard;

use GrupoAwamotos\ERPIntegration\Api\ConnectionInterface;
use GrupoAwamotos\ERPIntegration\Api\DashboardStatsProviderInterface;
use Magento\Customer\Model\ResourceModel\Customer\CollectionFactory as CustomerCollectionFactory;
use Magento\Framework\App\CacheInterface;
use Psr\Log\LoggerInterface;

/**
 * Dashboard Stats Provider
 *
 * Centralises every ERP (SQL Server) query used by the admin dashboard.
 * Results are cached for STATS_CACHE_TTL seconds to avoid repeated round-trips.
 */
class StatsProvider implements DashboardStatsProviderInterface
{
    private const STATS_CACHE_KEY = 'erp_dashboard_stats';
    private const STATS_CACHE_TTL = 300; // 5 minutes

    public function __construct(
        private readonly ConnectionInterface $connection,
        private readonly CustomerCollectionFactory $customerCollectionFactory,
        private readonly CacheInterface $cache,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getCustomerStats(): array
    {
        $row = $this->connection->fetchOne("
            SELECT
                COUNT(*) as total_fornecedores,
                SUM(CASE WHEN CKCLIENTE = 'S' THEN 1 ELSE 0 END) as total_clientes,
                SUM(CASE WHEN CKCLIENTE <> 'S' OR CKCLIENTE IS NULL THEN 1 ELSE 0 END) as total_fornecedores_only
            FROM FN_FORNECEDORES
        ");

        return [
            'total_fornecedores' => (int)($row['total_fornecedores'] ?? 0),
            'total_clientes'     => (int)($row['total_clientes'] ?? 0),
            'total_fornecedores_only' => (int)($row['total_fornecedores_only'] ?? 0),
        ];
    }

    /**
     * @inheritDoc
     */
    public function getOrderStats(): array
    {
        $row = $this->connection->fetchOne("
            SELECT
                COUNT(DISTINCT p.CODIGO) as total_pedidos,
                COUNT(DISTINCT p.CLIENTE) as clientes_com_pedidos,
                SUM(i.VLRTOTAL) as valor_total,
                AVG(i.VLRTOTAL) as ticket_medio
            FROM VE_PEDIDO p
            INNER JOIN VE_PEDIDOITENS i ON p.CODIGO = i.PEDIDO
            WHERE p.STATUS NOT IN ('C', 'X')
        ");

        return [
            'total_pedidos'       => (int)($row['total_pedidos'] ?? 0),
            'clientes_com_pedidos' => (int)($row['clientes_com_pedidos'] ?? 0),
            'valor_total'         => (float)($row['valor_total'] ?? 0),
            'ticket_medio'        => (float)($row['ticket_medio'] ?? 0),
        ];
    }

    /**
     * @inheritDoc
     */
    public function getRecentOrderStats(int $days = 30): array
    {
        $days = \max(1, \min($days, 365));

        $row = $this->connection->fetchOne("
            SELECT
                COUNT(DISTINCT p.CODIGO) as pedidos_30_dias,
                SUM(i.VLRTOTAL) as valor_30_dias,
                COUNT(DISTINCT p.CLIENTE) as clientes_ativos
            FROM VE_PEDIDO p
            INNER JOIN VE_PEDIDOITENS i ON p.CODIGO = i.PEDIDO
            WHERE p.STATUS NOT IN ('C', 'X')
            AND p.DTPEDIDO >= DATEADD(day, -{$days}, GETDATE())
        ");

        return [
            'pedidos_30_dias' => (int)($row['pedidos_30_dias'] ?? 0),
            'valor_30_dias'   => (float)($row['valor_30_dias'] ?? 0),
            'clientes_ativos' => (int)($row['clientes_ativos'] ?? 0),
        ];
    }

    /**
     * @inheritDoc
     */
    public function getTopCustomers(int $limit = 10): array
    {
        $limit = \max(1, \min($limit, 100));

        return $this->connection->query("
            SELECT TOP " . (int)$limit . "
                f.CODIGO,
                f.RAZAO,
                f.FANTASIA,
                f.CGC,
                f.CIDADE,
                f.UF,
                COUNT(DISTINCT p.CODIGO) as total_pedidos,
                SUM(i.VLRTOTAL) as valor_total
            FROM FN_FORNECEDORES f
            INNER JOIN VE_PEDIDO p ON f.CODIGO = p.CLIENTE
            INNER JOIN VE_PEDIDOITENS i ON p.CODIGO = i.PEDIDO
            WHERE p.STATUS NOT IN ('C', 'X')
            AND f.CKCLIENTE = 'S'
            GROUP BY f.CODIGO, f.RAZAO, f.FANTASIA, f.CGC, f.CIDADE, f.UF
            ORDER BY SUM(i.VLRTOTAL) DESC
        ");
    }

    /**
     * @inheritDoc
     */
    public function getTopProducts(int $limit = 10, int $days = 30): array
    {
        $limit = \max(1, \min($limit, 100));
        $days = \max(1, \min($days, 365));

        return $this->connection->query("
            SELECT TOP " . (int)$limit . "
                i.MATERIAL as sku,
                i.DESCRICAO as nome,
                COUNT(DISTINCT i.PEDIDO) as total_pedidos,
                SUM(i.QTDE) as quantidade_total,
                SUM(i.VLRTOTAL) as valor_total
            FROM VE_PEDIDOITENS i
            INNER JOIN VE_PEDIDO p ON i.PEDIDO = p.CODIGO
            WHERE p.STATUS NOT IN ('C', 'X')
            AND p.DTPEDIDO >= DATEADD(day, -{$days}, GETDATE())
            GROUP BY i.MATERIAL, i.DESCRICAO
            ORDER BY SUM(i.QTDE) DESC
        ");
    }

    /**
     * @inheritDoc
     */
    public function getAggregatedStats(): array
    {
        $cached = $this->cache->load(self::STATS_CACHE_KEY);
        if ($cached) {
            $decoded = json_decode($cached, true);
            if (is_array($decoded)) {
                return $decoded;
            }
            $this->logger->warning('[ERP Dashboard] Corrupted cache removed for key: ' . self::STATS_CACHE_KEY);
            $this->cache->remove(self::STATS_CACHE_KEY);
        }

        try {
            $customerStats = $this->getCustomerStats();
            $orderStats    = $this->getOrderStats();
            $recentStats   = $this->getRecentOrderStats(30);
            $topCustomers  = $this->getTopCustomers(10);
            $topProducts   = $this->getTopProducts(10, 30);
            $magentoCustomers = $this->customerCollectionFactory->create()->getSize();

            $stats = [
                'customers' => [
                    'total_erp'     => $customerStats['total_fornecedores'],
                    'clientes'      => $customerStats['total_clientes'],
                    'fornecedores'  => $customerStats['total_fornecedores_only'],
                    'magento'       => $magentoCustomers,
                    'com_pedidos'   => $orderStats['clientes_com_pedidos'],
                ],
                'orders' => [
                    'total'        => $orderStats['total_pedidos'],
                    'valor_total'  => $orderStats['valor_total'],
                    'ticket_medio' => $orderStats['ticket_medio'],
                ],
                'recent' => [
                    'pedidos'         => $recentStats['pedidos_30_dias'],
                    'valor'           => $recentStats['valor_30_dias'],
                    'clientes_ativos' => $recentStats['clientes_ativos'],
                ],
                'top_customers' => $topCustomers,
                'top_products'  => $topProducts,
            ];

            $this->cache->save(
                (string)json_encode($stats),
                self::STATS_CACHE_KEY,
                ['erp_dashboard'],
                self::STATS_CACHE_TTL
            );

            return $stats;
        } catch (\Exception $e) {
            $this->logger->error('ERP Dashboard stats fetch failed', [
                'error' => $e->getMessage(),
            ]);
            return ['error' => $e->getMessage()];
        }
    }
}
