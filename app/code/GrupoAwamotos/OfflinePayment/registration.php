<?php

/**
 * GrupoAwamotos OfflinePayment
 * Método de pagamento "A Combinar"
 */

declare(strict_types=1);

use Magento\Framework\Component\ComponentRegistrar;

ComponentRegistrar::register(
    ComponentRegistrar::MODULE,
    'GrupoAwamotos_OfflinePayment',
    __DIR__
);
