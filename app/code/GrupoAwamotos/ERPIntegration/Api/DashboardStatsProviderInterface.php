<?php

declare(strict_types=1);

namespace GrupoAwamotos\ERPIntegration\Api;

/**
 * Dashboard Stats Provider Interface
 *
 * Provides aggregated ERP statistics for the admin dashboard.
 * Centralises all raw SQL queries that were previously in the Block.
 */
interface DashboardStatsProviderInterface
{
    /**
     * Get customer statistics from ERP (total, clients, suppliers)
     *
     * @return array{total_fornecedores: int, total_clientes: int, total_fornecedores_only: int}
     */
    public function getCustomerStats(): array;

    /**
     * Get all-time order statistics (total, revenue, avg ticket)
     *
     * @return array{total_pedidos: int, clientes_com_pedidos: int, valor_total: float, ticket_medio: float}
     */
    public function getOrderStats(): array;

    /**
     * Get order statistics for the last N days
     *
     * @param int $days Number of days to look back (default 30)
     * @return array{pedidos_30_dias: int, valor_30_dias: float, clientes_ativos: int}
     */
    public function getRecentOrderStats(int $days = 30): array;

    /**
     * Get top customers by revenue
     *
     * @param int $limit Number of customers to return
     * @return array<int, array{CODIGO: int, RAZAO: string, FANTASIA: string, CGC: string, CIDADE: string, UF: string, total_pedidos: int, valor_total: float}>
     */
    public function getTopCustomers(int $limit = 10): array;

    /**
     * Get top products by quantity sold within last N days
     *
     * @param int $limit Number of products to return
     * @param int $days Number of days to look back
     * @return array<int, array{sku: string, nome: string, total_pedidos: int, quantidade_total: int, valor_total: float}>
     */
    public function getTopProducts(int $limit = 10, int $days = 30): array;

    /**
     * Get full aggregated dashboard stats (cached)
     *
     * @return array<string, mixed>
     */
    public function getAggregatedStats(): array;
}
