<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Platform\Dashboard;

/**
 * Filtros read-only do Dashboard Executivo B2B.
 */
class DashboardFilter
{
    private const MAX_PERIOD_DAYS = 90;

    public function __construct(
        private readonly string $dateFrom,
        private readonly string $dateTo
    ) {
    }

    public static function fromRequestParams(?string $dateFrom, ?string $dateTo): self
    {
        $to = self::normalizeDate($dateTo) ?? date('Y-m-d');
        $fromDefault = (new \DateTimeImmutable($to))->modify('-30 days')->format('Y-m-d');
        $from = self::normalizeDate($dateFrom) ?? $fromDefault;

        if ($from > $to) {
            $from = $fromDefault;
        }

        $days = (new \DateTimeImmutable($from))->diff(new \DateTimeImmutable($to))->days;
        if ($days > self::MAX_PERIOD_DAYS) {
            $from = (new \DateTimeImmutable($to))->modify(sprintf('-%d days', self::MAX_PERIOD_DAYS))->format('Y-m-d');
        }

        return new self($from, $to);
    }

    public function getDateFrom(): string
    {
        return $this->dateFrom;
    }

    public function getDateTo(): string
    {
        return $this->dateTo;
    }

    public function getDateFromDatetime(): string
    {
        return $this->dateFrom . ' 00:00:00';
    }

    public function getDateToDatetime(): string
    {
        return $this->dateTo . ' 23:59:59';
    }

    /**
     * @return array{date_from: string, date_to: string}
     */
    public function toArray(): array
    {
        return [
            'date_from' => $this->dateFrom,
            'date_to' => $this->dateTo,
        ];
    }

    private static function normalizeDate(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('Y-m-d', $value);

        return $date !== false ? $date->format('Y-m-d') : null;
    }
}
