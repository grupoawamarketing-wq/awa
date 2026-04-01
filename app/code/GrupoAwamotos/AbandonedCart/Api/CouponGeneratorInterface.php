<?php

declare(strict_types=1);

namespace GrupoAwamotos\AbandonedCart\Api;

interface CouponGeneratorInterface
{
    public function generate(
        float $discount,
        string $type,
        int $quoteId,
        ?string $customerEmail = null
    ): string;

    public function getCouponByCode(string $code): ?array;

    public function invalidateCoupon(string $code): bool;
}
