<?php
declare(strict_types=1);

namespace GrupoAwamotos\ERPIntegration\Api;

use Magento\Customer\Api\Data\CustomerInterface;

interface CustomerSyncInterface
{
    /**
     * Sincroniza todos os clientes ativos do ERP para o Magento
     *
     * @return array Resultado da sincronização [created, updated, errors, skipped]
     */
    public function syncAll(): array;

    /**
     * Busca cliente no ERP pelo CPF/CNPJ
     *
     * @param string $taxvat CPF ou CNPJ (com ou sem formatação)
     * @return array|null Dados do cliente ou null se não encontrado
     */
    public function getErpCustomerByTaxvat(string $taxvat): ?array;

    /**
     * Busca cliente no ERP pelo código interno
     *
     * @param int $code Código do cliente no ERP
     * @return array|null Dados do cliente ou null se não encontrado
     */
    public function getErpCustomerByCode(int $code): ?array;

    /**
     * Cria ou atualiza cliente no Magento baseado nos dados do ERP
     *
     * @param array $erpData Dados do cliente vindos do ERP
     * @param bool $createIfNotExists Se deve criar o cliente caso não exista
     * @return CustomerInterface|null Cliente criado/atualizado ou null em caso de erro
     */
    public function createOrUpdateCustomer(array $erpData, bool $createIfNotExists = true): ?CustomerInterface;

    /**
     * Sincroniza endereços de um cliente específico
     *
     * @param int $customerId ID do cliente no Magento
     * @param int $erpCode Código do cliente no ERP
     * @return bool True se sincronizado com sucesso
     */
    public function syncCustomerAddresses(int $customerId, int $erpCode): bool;

    /**
     * Vincula um cliente Magento existente a um cliente ERP
     *
     * @param int $customerId ID do cliente no Magento
     * @param int $erpCode Código do cliente no ERP
     * @return bool True se vinculado com sucesso
     */
    public function linkMagentoToErp(int $customerId, int $erpCode): bool;

    /**
     * Obtém o código ERP de um cliente Magento
     *
     * @param int $customerId ID do cliente no Magento
     * @return int|null Código ERP ou null se não vinculado
     */
    public function getErpCodeByCustomerId(int $customerId): ?int;

    /**
     * Sincroniza um cliente específico pelo CPF/CNPJ
     *
     * @param string $taxvat CPF ou CNPJ
     * @return array Resultado [success, message, customer_id, erp_code]
     */
    public function syncByTaxvat(string $taxvat): array;
}
