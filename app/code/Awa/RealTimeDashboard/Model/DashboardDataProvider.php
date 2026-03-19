<?php
declare(strict_types=1);

namespace Awa\RealTimeDashboard\Model;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;

class DashboardDataProvider
{
    private ResourceConnection $resource;
    private AdapterInterface $connection;

    public function __construct(ResourceConnection $resource)
    {
        $this->resource = $resource;
        $this->connection = $resource->getConnection();
    }

    public function getData(): array
    {
        return [
            'summary'          => $this->getSummary(),
            'visitors'         => $this->getRecentVisitors(),
            'viewed_products'  => $this->getViewedProducts(),
            'active_carts'     => $this->getActiveCarts(),
            'recent_searches'  => $this->getRecentSearches(),
            'recent_orders'    => $this->getRecentOrders(),
            'events_timeline'  => $this->getEventsTimeline(),
            'hourly_visitors'  => $this->getHourlyVisitors(),
            'server_time'      => date('Y-m-d H:i:s'),
        ];
    }

    protected function getSummary(): array
    {
        $visitorTable = $this->resource->getTableName('customer_visitor');
        $eventTable = $this->resource->getTableName('report_event');
        $quoteTable = $this->resource->getTableName('quote');
        $orderTable = $this->resource->getTableName('sales_order');
        $searchTable = $this->resource->getTableName('search_query');

        // Visitantes nos últimos 15 minutos (online agora)
        $online15 = (int) $this->connection->fetchOne(
            "SELECT COUNT(*) FROM {$visitorTable} WHERE last_visit_at >= NOW() - INTERVAL 15 MINUTE"
        );

        // Visitantes última hora
        $online1h = (int) $this->connection->fetchOne(
            "SELECT COUNT(*) FROM {$visitorTable} WHERE last_visit_at >= NOW() - INTERVAL 1 HOUR"
        );

        // Visitantes 24h
        $online24h = (int) $this->connection->fetchOne(
            "SELECT COUNT(*) FROM {$visitorTable} WHERE last_visit_at >= NOW() - INTERVAL 24 HOUR"
        );

        // Eventos (pageviews) 24h
        $events24h = (int) $this->connection->fetchOne(
            "SELECT COUNT(*) FROM {$eventTable} WHERE logged_at >= NOW() - INTERVAL 24 HOUR"
        );

        // Eventos última hora
        $events1h = (int) $this->connection->fetchOne(
            "SELECT COUNT(*) FROM {$eventTable} WHERE logged_at >= NOW() - INTERVAL 1 HOUR"
        );

        // Carrinhos ativos 24h
        $carts24h = (int) $this->connection->fetchOne(
            "SELECT COUNT(*) FROM {$quoteTable} WHERE is_active = 1 AND updated_at >= NOW() - INTERVAL 24 HOUR"
        );

        // Valor total em carrinhos ativos
        $cartsValue = (float) $this->connection->fetchOne(
            "SELECT COALESCE(SUM(grand_total), 0) FROM {$quoteTable} WHERE is_active = 1 AND updated_at >= NOW() - INTERVAL 24 HOUR AND items_count > 0"
        );

        // Pedidos hoje
        $ordersToday = (int) $this->connection->fetchOne(
            "SELECT COUNT(*) FROM {$orderTable} WHERE created_at >= CURDATE()"
        );

        // Receita hoje
        $revenueToday = (float) $this->connection->fetchOne(
            "SELECT COALESCE(SUM(grand_total), 0) FROM {$orderTable} WHERE created_at >= CURDATE() AND status NOT IN ('canceled', 'closed')"
        );

        // Buscas hoje
        $searchesToday = (int) $this->connection->fetchOne(
            "SELECT COALESCE(SUM(popularity), 0) FROM {$searchTable} WHERE updated_at >= CURDATE()"
        );

        return [
            'online_now'     => $online15,
            'online_1h'      => $online1h,
            'online_24h'     => $online24h,
            'events_24h'     => $events24h,
            'events_1h'      => $events1h,
            'carts_active'   => $carts24h,
            'carts_value'    => number_format($cartsValue, 2, ',', '.'),
            'orders_today'   => $ordersToday,
            'revenue_today'  => number_format($revenueToday, 2, ',', '.'),
            'searches_today' => $searchesToday,
        ];
    }

    protected function getRecentVisitors(): array
    {
        $visitorTable = $this->resource->getTableName('customer_visitor');
        $customerTable = $this->resource->getTableName('customer_entity');

        return $this->connection->fetchAll(
            "SELECT cv.visitor_id, cv.last_visit_at, cv.created_at, cv.customer_id,
                    ce.email, ce.firstname, ce.lastname
             FROM {$visitorTable} cv
             LEFT JOIN {$customerTable} ce ON cv.customer_id = ce.entity_id
             ORDER BY cv.last_visit_at DESC
             LIMIT 15"
        );
    }

    protected function getViewedProducts(): array
    {
        $reportTable  = $this->resource->getTableName('report_viewed_product_index');
        $varcharTable = $this->resource->getTableName('catalog_product_entity_varchar');
        $eavTable     = $this->resource->getTableName('eav_attribute');

        // Pre-fetch attribute_id once — evita subquery correlacionada por linha
        $nameAttrId = (int) $this->connection->fetchOne(
            "SELECT attribute_id FROM {$eavTable}
             WHERE attribute_code = 'name' AND entity_type_id = 4 LIMIT 1"
        );

        return $this->connection->fetchAll(
            "SELECT rv.visitor_id, rv.customer_id, rv.product_id, rv.added_at,
                    cpev.value AS product_name
             FROM {$reportTable} rv
             LEFT JOIN {$varcharTable} cpev ON rv.product_id = cpev.entity_id
                AND cpev.attribute_id = {$nameAttrId}
                AND cpev.store_id = 0
             ORDER BY rv.added_at DESC
             LIMIT 20"
        );
    }

    protected function getActiveCarts(): array
    {
        $quoteTable     = $this->resource->getTableName('quote');
        $quoteItemTable = $this->resource->getTableName('quote_item');

        $carts = $this->connection->fetchAll(
            "SELECT q.entity_id, q.created_at, q.updated_at, q.items_count,
                    q.grand_total, q.customer_email, q.customer_firstname,
                    q.remote_ip, q.is_active
             FROM {$quoteTable} q
             WHERE q.is_active = 1 AND q.items_count > 0
             ORDER BY q.updated_at DESC
             LIMIT 15"
        );

        if (empty($carts)) {
            return $carts;
        }

        // Busca todos os itens em uma única query (elimina N+1)
        $cartIds    = implode(',', array_map('intval', array_column($carts, 'entity_id')));
        $allItems   = $this->connection->fetchAll(
            "SELECT qi.quote_id, qi.name, qi.sku, qi.qty, qi.price, qi.row_total
             FROM {$quoteItemTable} qi
             WHERE qi.quote_id IN ({$cartIds}) AND qi.parent_item_id IS NULL"
        );

        // Agrupa itens por quote_id em PHP
        $itemsByCart = [];
        foreach ($allItems as $item) {
            $itemsByCart[(int)$item['quote_id']][] = $item;
        }

        foreach ($carts as &$cart) {
            $cart['items'] = array_slice($itemsByCart[(int)$cart['entity_id']] ?? [], 0, 5);
        }

        return $carts;
    }

    protected function getRecentSearches(): array
    {
        $searchTable = $this->resource->getTableName('search_query');

        return $this->connection->fetchAll(
            "SELECT query_text, num_results, popularity, updated_at
             FROM {$searchTable}
             ORDER BY updated_at DESC
             LIMIT 20"
        );
    }

    protected function getRecentOrders(): array
    {
        $orderTable = $this->resource->getTableName('sales_order');

        return $this->connection->fetchAll(
            "SELECT entity_id, increment_id, created_at, grand_total,
                    customer_email, customer_firstname, customer_lastname,
                    remote_ip, status, total_item_count
             FROM {$orderTable}
             ORDER BY created_at DESC
             LIMIT 10"
        );
    }

    protected function getEventsTimeline(): array
    {
        $eventTable     = $this->resource->getTableName('report_event');
        $eventTypeTable = $this->resource->getTableName('report_event_types');
        $varcharTable   = $this->resource->getTableName('catalog_product_entity_varchar');
        $eavTable       = $this->resource->getTableName('eav_attribute');

        // Pre-fetch attribute_id once — evita subquery correlacionada por linha
        $nameAttrId = (int) $this->connection->fetchOne(
            "SELECT attribute_id FROM {$eavTable}
             WHERE attribute_code = 'name' AND entity_type_id = 4 LIMIT 1"
        );

        return $this->connection->fetchAll(
            "SELECT re.event_id, ret.event_name, re.logged_at, re.object_id,
                    cpev.value AS product_name
             FROM {$eventTable} re
             LEFT JOIN {$eventTypeTable} ret ON re.event_type_id = ret.event_type_id
             LEFT JOIN {$varcharTable} cpev ON re.object_id = cpev.entity_id
                AND cpev.attribute_id = {$nameAttrId}
                AND cpev.store_id = 0
             ORDER BY re.logged_at DESC
             LIMIT 30"
        );
    }

    protected function getHourlyVisitors(): array
    {
        $visitorTable = $this->resource->getTableName('customer_visitor');

        return $this->connection->fetchAll(
            "SELECT HOUR(last_visit_at) AS hora,
                    COUNT(*) AS total
             FROM {$visitorTable}
             WHERE last_visit_at >= NOW() - INTERVAL 24 HOUR
             GROUP BY HOUR(last_visit_at)
             ORDER BY hora ASC"
        );
    }
}
