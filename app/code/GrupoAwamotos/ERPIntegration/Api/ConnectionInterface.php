<?php

declare(strict_types=1);

namespace GrupoAwamotos\ERPIntegration\Api;

/**
 * SQL Server Connection Interface
 */
interface ConnectionInterface
{
    /**
     * Get PDO connection instance
     */
    public function getConnection(): \PDO;

    /**
     * Test connection and return diagnostic info
     */
    public function testConnection(): array;

    /**
     * Execute SELECT query and return all rows
     */
    public function query(string $sql, array $params = []): array;

    /**
     * Execute INSERT/UPDATE/DELETE and return affected rows
     */
    public function execute(string $sql, array $params = []): int;

    /**
     * Fetch single row
     */
    public function fetchOne(string $sql, array $params = []): ?array;

    /**
     * Fetch single column value
     */
    public function fetchColumn(string $sql, array $params = [], int $column = 0);

    /**
     * Begin database transaction
     */
    public function beginTransaction(): bool;

    /**
     * Commit current transaction
     */
    public function commit(): bool;

    /**
     * Rollback current transaction
     */
    public function rollback(): bool;

    /**
     * Disconnect from database
     */
    public function disconnect(): void;

    /**
     * Check if currently connected
     */
    public function isConnected(): bool;

    /**
     * Check if any SQL Server driver is available
     */
    public function hasAvailableDriver(): bool;

    /**
     * Get available PDO drivers
     */
    public function getAvailableDrivers(): array;
}
