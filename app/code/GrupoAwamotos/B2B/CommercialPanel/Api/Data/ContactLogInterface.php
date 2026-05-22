<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\CommercialPanel\Api\Data;

interface ContactLogInterface
{
    public const CONTACT_ID = 'contact_id';
    public const CUSTOMER_ID = 'customer_id';
    public const ATTENDANT_ID = 'attendant_id';
    public const ADMIN_USER_ID = 'admin_user_id';
    public const CONTACT_TYPE = 'contact_type';
    public const OBSERVATION = 'observation';
    public const NEXT_ACTION = 'next_action';
    public const NEXT_ACTION_AT = 'next_action_at';
    public const CREATED_AT = 'created_at';

    public function getContactId(): ?int;

    public function setContactId(int $contactId): self;

    public function getCustomerId(): int;

    public function setCustomerId(int $customerId): self;

    public function getAttendantId(): ?int;

    public function setAttendantId(?int $attendantId): self;

    public function getAdminUserId(): int;

    public function setAdminUserId(int $adminUserId): self;

    public function getContactType(): string;

    public function setContactType(string $contactType): self;

    public function getObservation(): string;

    public function setObservation(string $observation): self;

    public function getNextAction(): ?string;

    public function setNextAction(?string $nextAction): self;

    public function getNextActionAt(): ?string;

    public function setNextActionAt(?string $nextActionAt): self;

    public function getCreatedAt(): ?string;
}
