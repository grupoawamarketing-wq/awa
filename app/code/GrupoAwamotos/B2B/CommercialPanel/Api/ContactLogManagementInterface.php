<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\CommercialPanel\Api;

use GrupoAwamotos\B2B\CommercialPanel\Api\Data\ContactLogInterface;

interface ContactLogManagementInterface
{
    /**
     * Registra contato comercial após validar escopo da carteira.
     *
     * @param array<string, mixed> $data
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function registerContact(array $data, int $adminUserId): ContactLogInterface;
}
