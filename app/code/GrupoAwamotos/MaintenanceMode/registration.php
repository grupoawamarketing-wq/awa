<?php

declare(strict_types=1);

/**
 * GrupoAwamotos MaintenanceMode Module
 *
 * Modo de manutenção customizado com controle via admin
 */

use Magento\Framework\Component\ComponentRegistrar;

ComponentRegistrar::register(
    ComponentRegistrar::MODULE,
    'GrupoAwamotos_MaintenanceMode',
    __DIR__
);
