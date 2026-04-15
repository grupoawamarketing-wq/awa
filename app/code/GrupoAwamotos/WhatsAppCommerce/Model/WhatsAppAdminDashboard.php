<?php
declare(strict_types=1);

namespace GrupoAwamotos\WhatsAppCommerce\Model;

use GrupoAwamotos\WhatsAppCommerce\Api\AdminDashboardInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Psr\Log\LoggerInterface;

class WhatsAppAdminDashboard implements AdminDashboardInterface
{
    public function __construct(
        private readonly ResourceConnection $resource,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly StockRegistryInterface $stockRegistry,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly LoggerInterface $logger,
    ) {}

    public function salesToday(): array
    {
        try {
            $connection = $this->resource->getConnection();
            $table = $this->resource->getTableName('sales_order');
            $today = date('Y-m-d');

            $sql = $connection->select()
                ->from($table, [
                    'total_orders' => new \Zend_Db_Expr('COUNT(*)'),
                    'total_revenue' => new \Zend_Db_Expr('COALESCE(SUM(grand_total), 0)'),
                    'avg_ticket' => new \Zend_Db_Expr('COALESCE(AVG(grand_total), 0)'),
                    'total_items' => new \Zend_Db_Expr('COALESCE(SUM(total_qty_ordered), 0)'),
                ])
                ->where('DATE(created_at) = ?', $today)
                ->where('state NOT IN (?)', ['canceled', 'closed']);

            $row = $connection->fetchRow($sql);

            $sqlYesterday = $connection->select()
                ->from($table, [
                    'total_orders' => new \Zend_Db_Expr('COUNT(*)'),
                    'total_revenue' => new \Zend_Db_Expr('COALESCE(SUM(grand_total), 0)'),
                ])
                ->where('DATE(created_at) = ?', date('Y-m-d', strtotime('-1 day')))
                ->where('state NOT IN (?)', ['canceled', 'closed']);

            $yesterday = $connection->fetchRow($sqlYesterday);

            return [
                'date' => $today,
                'orders' => (int) ($row['total_orders'] ?? 0),
                'revenue' => round((float) ($row['total_revenue'] ?? 0), 2),
                'avg_ticket' => round((float) ($row['avg_ticket'] ?? 0), 2),
                'items_sold' => (int) ($row['total_items'] ?? 0),
                'yesterday_orders' => (int) ($yesterday['total_orders'] ?? 0),
                'yesterday_revenue' => round((float) ($yesterday['total_revenue'] ?? 0), 2),
                'message' => sprintf(
                    "📊 Vendas hoje (%s): %d pedidos | R$ %s | Ticket: R$ %s",
                    date('d/m'),
                    (int) ($row['total_orders'] ?? 0),
                    number_format((float) ($row['total_revenue'] ?? 0), 2, ',', '.'),
                    number_format((float) ($row['avg_ticket'] ?? 0), 2, ',', '.')
                ),
            ];
        } catch (\Exception $e) {
            $this->logger->error('AdminDashboard::salesToday error', ['error' => $e->getMessage()]);
            return ['error' => true, 'message' => 'Erro ao consultar vendas.'];
        }
    }

    public function stockCheck(string $sku): array
    {
        try {
            $product = $this->productRepository->get($sku);
            $stockItem = $this->stockRegistry->getStockItemBySku($sku);

            return [
                'sku' => $sku,
                'name' => $product->getName(),
                'qty' => (int) $stockItem->getQty(),
                'is_in_stock' => $stockItem->getIsInStock(),
                'min_qty' => (int) $stockItem->getMinQty(),
                'price' => (float) $product->getPrice(),
                'message' => sprintf(
                    "📦 %s (SKU: %s): %d un em estoque%s | R$ %s",
                    $product->getName(),
                    $sku,
                    (int) $stockItem->getQty(),
                    $stockItem->getIsInStock() ? '' : ' ⚠️ FORA DE ESTOQUE',
                    number_format((float) $product->getPrice(), 2, ',', '.')
                ),
            ];
        } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
            return ['error' => true, 'message' => "Produto SKU '{$sku}' não encontrado."];
        } catch (\Exception $e) {
            $this->logger->error('AdminDashboard::stockCheck error', ['sku' => $sku, 'error' => $e->getMessage()]);
            return ['error' => true, 'message' => 'Erro ao consultar estoque.'];
        }
    }

    public function newCustomers(): array
    {
        try {
            $connection = $this->resource->getConnection();
            $table = $this->resource->getTableName('customer_entity');

            $counts = [];
            foreach (['1' => '24h', '7' => '7d', '30' => '30d'] as $days => $label) {
                $sql = $connection->select()
                    ->from($table, ['cnt' => new \Zend_Db_Expr('COUNT(*)')])
                    ->where('created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)', $days);
                $counts[$label] = (int) $connection->fetchOne($sql);
            }

            return [
                'last_24h' => $counts['24h'],
                'last_7d' => $counts['7d'],
                'last_30d' => $counts['30d'],
                'message' => sprintf(
                    "👥 Novos clientes: %d (24h) | %d (7d) | %d (30d)",
                    $counts['24h'], $counts['7d'], $counts['30d']
                ),
            ];
        } catch (\Exception $e) {
            $this->logger->error('AdminDashboard::newCustomers error', ['error' => $e->getMessage()]);
            return ['error' => true, 'message' => 'Erro ao consultar clientes.'];
        }
    }

    public function orderDetail(string $incrementId): array
    {
        try {
            $this->searchCriteriaBuilder->addFilter('increment_id', $incrementId);
            $searchCriteria = $this->searchCriteriaBuilder->setPageSize(1)->create();
            $orders = $this->orderRepository->getList($searchCriteria);

            if ($orders->getTotalCount() === 0) {
                return ['error' => true, 'message' => "Pedido #{$incrementId} não encontrado."];
            }

            $order = current($orders->getItems());
            $items = [];
            foreach ($order->getItems() as $item) {
                if ($item->getParentItemId()) {
                    continue;
                }
                $items[] = sprintf("%dx %s", (int) $item->getQtyOrdered(), $item->getName());
            }

            $trackings = [];
            foreach ($order->getShipmentsCollection() as $shipment) {
                foreach ($shipment->getAllTracks() as $track) {
                    $trackings[] = $track->getTrackNumber();
                }
            }

            return [
                'increment_id' => $incrementId,
                'status' => $order->getStatusLabel(),
                'state' => $order->getState(),
                'grand_total' => round((float) $order->getGrandTotal(), 2),
                'customer_name' => $order->getCustomerFirstname() . ' ' . $order->getCustomerLastname(),
                'created_at' => $order->getCreatedAt(),
                'items' => $items,
                'tracking' => $trackings,
                'payment_method' => $order->getPayment()->getMethod(),
                'message' => sprintf(
                    "📋 Pedido #%s\nStatus: %s\nCliente: %s\nTotal: R$ %s\nItens: %s%s",
                    $incrementId,
                    $order->getStatusLabel(),
                    $order->getCustomerFirstname() . ' ' . $order->getCustomerLastname(),
                    number_format((float) $order->getGrandTotal(), 2, ',', '.'),
                    implode(', ', $items),
                    $trackings ? "\nRastreio: " . implode(', ', $trackings) : ''
                ),
            ];
        } catch (\Exception $e) {
            $this->logger->error('AdminDashboard::orderDetail error', ['order' => $incrementId, 'error' => $e->getMessage()]);
            return ['error' => true, 'message' => 'Erro ao consultar pedido.'];
        }
    }

    public function topSelling(int $days = 30): array
    {
        try {
            $connection = $this->resource->getConnection();
            $sql = $connection->select()
                ->from(
                    ['oi' => $this->resource->getTableName('sales_order_item')],
                    [
                        'sku' => 'oi.sku',
                        'name' => 'oi.name',
                        'total_qty' => new \Zend_Db_Expr('SUM(oi.qty_ordered)'),
                        'total_revenue' => new \Zend_Db_Expr('SUM(oi.row_total)'),
                    ]
                )
                ->join(
                    ['o' => $this->resource->getTableName('sales_order')],
                    'oi.order_id = o.entity_id',
                    []
                )
                ->where('o.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)', $days)
                ->where('o.state NOT IN (?)', ['canceled', 'closed'])
                ->where('oi.parent_item_id IS NULL')
                ->group('oi.sku')
                ->order('total_qty DESC')
                ->limit(10);

            $rows = $connection->fetchAll($sql);

            $products = [];
            $lines = [];
            foreach ($rows as $i => $row) {
                $products[] = [
                    'sku' => $row['sku'],
                    'name' => $row['name'],
                    'qty' => (int) $row['total_qty'],
                    'revenue' => round((float) $row['total_revenue'], 2),
                ];
                $lines[] = sprintf("%d. %s — %d un (R$ %s)",
                    $i + 1, $row['name'], (int) $row['total_qty'],
                    number_format((float) $row['total_revenue'], 2, ',', '.')
                );
            }

            return [
                'period_days' => $days,
                'products' => $products,
                'message' => "🏆 Top 10 mais vendidos ({$days}d):\n" . implode("\n", $lines),
            ];
        } catch (\Exception $e) {
            $this->logger->error('AdminDashboard::topSelling error', ['error' => $e->getMessage()]);
            return ['error' => true, 'message' => 'Erro ao consultar mais vendidos.'];
        }
    }
}
