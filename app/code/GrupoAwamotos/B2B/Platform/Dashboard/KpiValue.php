<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Platform\Dashboard;

/**
 * Valor de KPI com indicação de disponibilidade da fonte.
 */
class KpiValue
{
    public function __construct(
        private readonly mixed $value,
        private readonly bool $available,
        private readonly string $formatted = ''
    ) {
    }

    public static function available(mixed $value, string $formatted = ''): self
    {
        return new self($value, true, $formatted !== '' ? $formatted : (string) $value);
    }

    public static function unavailable(): self
    {
        return new self(null, false, (string) __('Fonte indisponível'));
    }

    public function isAvailable(): bool
    {
        return $this->available;
    }

    public function getValue(): mixed
    {
        return $this->value;
    }

    public function getFormatted(): string
    {
        return $this->formatted;
    }

    /**
     * @return array{value: mixed, available: bool, formatted: string}
     */
    public function toArray(): array
    {
        return [
            'value' => $this->value,
            'available' => $this->available,
            'formatted' => $this->formatted,
        ];
    }
}
