<?php

declare(strict_types=1);

namespace GrupoAwamotos\ERPIntegration\Model;

use Magento\Framework\Exception\LocalizedException;

/**
 * Exception thrown when circuit breaker is open
 */
class CircuitBreakerOpenException extends LocalizedException
{
}
