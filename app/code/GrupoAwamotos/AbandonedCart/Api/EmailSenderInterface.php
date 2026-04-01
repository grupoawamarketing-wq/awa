<?php

declare(strict_types=1);

namespace GrupoAwamotos\AbandonedCart\Api;

use GrupoAwamotos\AbandonedCart\Api\Data\AbandonedCartInterface;

interface EmailSenderInterface
{
    public function sendEmail(AbandonedCartInterface $abandonedCart, int $emailNumber): bool;

    public function sendTestEmail(string $email, int $emailNumber): bool;
}
