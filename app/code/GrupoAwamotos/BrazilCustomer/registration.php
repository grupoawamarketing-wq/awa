<?php

/**
 * GrupoAwamotos BrazilCustomer
 * Atributos brasileiros para clientes (CPF, CNPJ, IE, RG)
 */

declare(strict_types=1);

use Magento\Framework\Component\ComponentRegistrar;

ComponentRegistrar::register(
    ComponentRegistrar::MODULE,
    'GrupoAwamotos_BrazilCustomer',
    __DIR__
);
