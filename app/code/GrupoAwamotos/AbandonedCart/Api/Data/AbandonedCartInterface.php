<?php

declare(strict_types=1);

namespace GrupoAwamotos\AbandonedCart\Api\Data;

interface AbandonedCartInterface
{
    public const ENTITY_ID = 'entity_id';
    public const QUOTE_ID = 'quote_id';
    public const CUSTOMER_ID = 'customer_id';
    public const CUSTOMER_EMAIL = 'customer_email';
    public const CUSTOMER_NAME = 'customer_name';
    public const STORE_ID = 'store_id';
    public const CART_VALUE = 'cart_value';
    public const ITEMS_COUNT = 'items_count';
    public const ABANDONED_AT = 'abandoned_at';
    public const EMAIL_1_SENT = 'email_1_sent';
    public const EMAIL_1_SENT_AT = 'email_1_sent_at';
    public const EMAIL_2_SENT = 'email_2_sent';
    public const EMAIL_2_SENT_AT = 'email_2_sent_at';
    public const EMAIL_3_SENT = 'email_3_sent';
    public const EMAIL_3_SENT_AT = 'email_3_sent_at';
    public const RECOVERED = 'recovered';
    public const RECOVERED_AT = 'recovered_at';
    public const COUPON_CODE = 'coupon_code';
    public const STATUS = 'status';
    public const CREATED_AT = 'created_at';
    public const UPDATED_AT = 'updated_at';
    public const ATTENDANT_ID = 'attendant_id';

    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_RECOVERED = 'recovered';
    public const STATUS_EXPIRED = 'expired';

    public function getEntityId(): ?int;
    public function setEntityId($id);

    public function getQuoteId(): ?int;
    public function setQuoteId(int $quoteId): self;

    public function getCustomerId(): ?int;
    public function setCustomerId(?int $customerId): self;

    public function getCustomerEmail(): ?string;
    public function setCustomerEmail(string $email): self;

    public function getCustomerName(): ?string;
    public function setCustomerName(?string $name): self;

    public function getStoreId(): ?int;
    public function setStoreId(int $storeId): self;

    public function getCartValue(): float;
    public function setCartValue(float $value): self;

    public function getItemsCount(): int;
    public function setItemsCount(int $count): self;

    public function getAbandonedAt(): ?string;
    public function setAbandonedAt(string $datetime): self;

    public function isEmail1Sent(): bool;
    public function setEmail1Sent(bool $sent): self;

    public function getEmail1SentAt(): ?string;
    public function setEmail1SentAt(?string $datetime): self;

    public function isEmail2Sent(): bool;
    public function setEmail2Sent(bool $sent): self;

    public function getEmail2SentAt(): ?string;
    public function setEmail2SentAt(?string $datetime): self;

    public function isEmail3Sent(): bool;
    public function setEmail3Sent(bool $sent): self;

    public function getEmail3SentAt(): ?string;
    public function setEmail3SentAt(?string $datetime): self;

    public function isRecovered(): bool;
    public function setRecovered(bool $recovered): self;

    public function getRecoveredAt(): ?string;
    public function setRecoveredAt(?string $datetime): self;

    public function getCouponCode(): ?string;
    public function setCouponCode(?string $code): self;

    public function getStatus(): string;
    public function setStatus(string $status): self;

    public function getAttendantId(): ?int;
    public function setAttendantId(?int $attendantId): self;
}
