<?php

declare(strict_types=1);

namespace GrupoAwamotos\ERPIntegration\Model;

/**
 * Circuit Breaker State Enum
 *
 * CLOSED: Normal operation, requests flow through
 * OPEN: Circuit is tripped, requests are blocked
 * HALF_OPEN: Testing if service recovered, limited requests allowed
 */
class CircuitBreakerState
{
    public const CLOSED = 'closed';
    public const OPEN = 'open';
    public const HALF_OPEN = 'half_open';

    /**
     * Get all valid states
     */
    public static function getStates(): array
    {
        return [
            self::CLOSED,
            self::OPEN,
            self::HALF_OPEN,
        ];
    }

    /**
     * Check if state is valid
     */
    public static function isValid(string $state): bool
    {
        return in_array($state, self::getStates(), true);
    }
}
