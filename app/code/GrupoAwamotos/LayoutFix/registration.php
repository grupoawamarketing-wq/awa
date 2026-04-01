<?php

declare(strict_types=1);

/**
 * GrupoAwamotos LayoutFix
 * Fixes layout reference issues in admin
 */

use Magento\Framework\Component\ComponentRegistrar;

ComponentRegistrar::register(
    ComponentRegistrar::MODULE,
    'GrupoAwamotos_LayoutFix',
    __DIR__
);
