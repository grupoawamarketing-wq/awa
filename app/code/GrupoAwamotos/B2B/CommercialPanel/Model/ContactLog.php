<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\CommercialPanel\Model;

use GrupoAwamotos\B2B\CommercialPanel\Api\Data\ContactLogInterface;
use GrupoAwamotos\B2B\CommercialPanel\Model\ResourceModel\ContactLogResource;
use Magento\Framework\Model\AbstractModel;

class ContactLog extends AbstractModel implements ContactLogInterface
{
    protected function _construct(): void
    {
        $this->_init(ContactLogResource::class);
    }

    public function getContactId(): ?int
    {
        $value = $this->getData(self::CONTACT_ID);

        return $value !== null ? (int) $value : null;
    }

    public function setContactId(int $contactId): ContactLogInterface
    {
        return $this->setData(self::CONTACT_ID, $contactId);
    }

    public function getCustomerId(): int
    {
        return (int) $this->getData(self::CUSTOMER_ID);
    }

    public function setCustomerId(int $customerId): ContactLogInterface
    {
        return $this->setData(self::CUSTOMER_ID, $customerId);
    }

    public function getAttendantId(): ?int
    {
        $value = $this->getData(self::ATTENDANT_ID);

        return $value !== null ? (int) $value : null;
    }

    public function setAttendantId(?int $attendantId): ContactLogInterface
    {
        return $this->setData(self::ATTENDANT_ID, $attendantId);
    }

    public function getAdminUserId(): int
    {
        return (int) $this->getData(self::ADMIN_USER_ID);
    }

    public function setAdminUserId(int $adminUserId): ContactLogInterface
    {
        return $this->setData(self::ADMIN_USER_ID, $adminUserId);
    }

    public function getContactType(): string
    {
        return (string) $this->getData(self::CONTACT_TYPE);
    }

    public function setContactType(string $contactType): ContactLogInterface
    {
        return $this->setData(self::CONTACT_TYPE, $contactType);
    }

    public function getObservation(): string
    {
        return (string) $this->getData(self::OBSERVATION);
    }

    public function setObservation(string $observation): ContactLogInterface
    {
        return $this->setData(self::OBSERVATION, $observation);
    }

    public function getNextAction(): ?string
    {
        $value = $this->getData(self::NEXT_ACTION);

        return $value !== null && $value !== '' ? (string) $value : null;
    }

    public function setNextAction(?string $nextAction): ContactLogInterface
    {
        return $this->setData(self::NEXT_ACTION, $nextAction);
    }

    public function getNextActionAt(): ?string
    {
        $value = $this->getData(self::NEXT_ACTION_AT);

        return $value !== null && $value !== '' ? (string) $value : null;
    }

    public function setNextActionAt(?string $nextActionAt): ContactLogInterface
    {
        return $this->setData(self::NEXT_ACTION_AT, $nextActionAt);
    }

    public function getCreatedAt(): ?string
    {
        $value = $this->getData(self::CREATED_AT);

        return $value !== null ? (string) $value : null;
    }
}
