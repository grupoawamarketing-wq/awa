<?php

declare(strict_types=1);

namespace GrupoAwamotos\ERPIntegration\Api;

/**
 * REST API for ERP/Sectra to pull B2B customer data from Magento.
 *
 * The Sectra "OpenCardB2B - Cadastro de Cliente" sync needs a list of
 * B2B customers with their ERP codes to register in GR_INTEGRACAOVALIDADOR.
 *
 * Endpoints:
 * - GET /V1/erp/customers/b2b                  List all B2B customers
 * - GET /V1/erp/customers/b2b/unregistered      List customers not in Sectra validador
 * - GET /V1/erp/customers/b2b/:erpCode          Get single customer detail
 */
interface CustomerPullInterface
{
    /**
     * List all B2B customers with ERP codes.
     *
     * @param int $limit Max results
     * @param int $offset Pagination offset
     * @return mixed[]
     */
    public function getB2BCustomers(int $limit = 100, int $offset = 0): array;

    /**
     * List B2B customers not yet registered in Sectra's GR_INTEGRACAOVALIDADOR.
     *
     * @param int $limit Max results
     * @return mixed[]
     */
    public function getUnregisteredCustomers(int $limit = 100): array;

    /**
     * Get single B2B customer detail by ERP code.
     *
     * @param int $erpCode ERP client code (FN_FORNECEDORES.CODIGO)
     * @return mixed[]
     */
    public function getCustomerByErpCode(int $erpCode): array;

    /**
     * Get SQL to register unregistered customers in Sectra's GR_INTEGRACAOVALIDADOR.
     *
     * Returns ready-to-execute T-SQL. Designed to be consumed by a scheduled
     * task on the Sectra server that pipes the SQL to sqlcmd.
     *
     * @param int $limit Max customers to process per call
     * @return mixed[]
     */
    public function getRegistrationSQL(int $limit = 500): array;

    /**
     * Health check for the ERP integration.
     *
     * Returns connection status, sync stats, and pending items.
     *
     * @return mixed[]
     */
    public function getHealthStatus(): array;
}
