<?php

/**
 * GrupoAwamotos B2B Module
 *
 * Módulo profissional para funcionalidades B2B:
 * - Ocultação de preços para visitantes
 * - Aprovação manual de clientes
 * - Grupos de clientes com preços diferenciados
 * - Quantidade mínima por produto
 * - Sistema de cotação (RFQ)
 * - Campos B2B no cadastro (CNPJ, Razão Social, IE)
 *
 * @author GrupoAwamotos
 * @package GrupoAwamotos_B2B
 */

declare(strict_types=1);

use Magento\Framework\Component\ComponentRegistrar;

ComponentRegistrar::register(
    ComponentRegistrar::MODULE,
    'GrupoAwamotos_B2B',
    __DIR__
);
