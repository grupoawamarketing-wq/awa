<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\CommercialPanel\Api;

/**
 * Escopo de carteira comercial por perfil (vendedora, supervisora, admin comercial).
 */
interface PortfolioScopeInterface
{
    /**
     * Usuário com permissão para ver carteiras de todas as vendedoras.
     */
    public function canViewAllPortfolios(): bool;

    /**
     * Usuário restrito exclusivamente ao cockpit comercial (sem admin técnico).
     */
    public function isCockpitOnlyUser(): bool;

    /**
     * IDs de atendentes visíveis ao usuário logado.
     *
     * @return int[]
     */
    public function getVisibleAttendantIds(): array;

    /**
     * IDs de clientes visíveis ao usuário logado (carteira).
     *
     * @return int[]
     */
    public function getVisibleCustomerIds(): array;

    /**
     * Usuário com acesso técnico completo (ex.: AWA TI) — ignora escopo de carteira.
     */
    public function canBypassPortfolioScope(): bool;

    /**
     * Verifica se o cliente pertence ao escopo visível.
     */
    public function canAccessCustomer(int $customerId): bool;
}
