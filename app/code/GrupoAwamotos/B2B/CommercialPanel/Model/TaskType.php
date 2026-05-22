<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\CommercialPanel\Model;

final class TaskType
{
    public const NO_PURCHASE = 'no_purchase';
    public const PENDING_NO_CONTACT = 'pending_no_contact';
    public const QUOTE_NO_RESPONSE = 'quote_no_response';
    public const ABANDONED_CART = 'abandoned_cart';
    public const NEW_CUSTOMER_NO_CONTACT = 'new_customer_no_contact';
    public const MANUAL = 'manual';

    /** @return string[] */
    public static function all(): array
    {
        return [
            self::NO_PURCHASE,
            self::PENDING_NO_CONTACT,
            self::QUOTE_NO_RESPONSE,
            self::ABANDONED_CART,
            self::NEW_CUSTOMER_NO_CONTACT,
            self::MANUAL,
        ];
    }
}
