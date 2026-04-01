<?php

declare(strict_types=1);

namespace GrupoAwamotos\AbandonedCart\Model;

use GrupoAwamotos\AbandonedCart\Api\Data\AbandonedCartInterface;
use GrupoAwamotos\AbandonedCart\Model\ResourceModel\AbandonedCart as ResourceModel;
use Magento\Framework\Model\AbstractModel;

class AbandonedCart extends AbstractModel implements AbandonedCartInterface
{
    protected $_eventPrefix = 'grupoawamotos_abandoned_cart';
    protected $_eventObject = 'abandoned_cart';

    protected function _construct()
    {
        $this->_init(ResourceModel::class);
    }

    public function getEntityId(): ?int
    {
        return $this->getData(self::ENTITY_ID) ? (int) $this->getData(self::ENTITY_ID) : null;
    }

    public function setEntityId($id)
    {
        return $this->setData(self::ENTITY_ID, $id);
    }

    public function getQuoteId(): ?int
    {
        return $this->getData(self::QUOTE_ID) ? (int) $this->getData(self::QUOTE_ID) : null;
    }

    public function setQuoteId(int $quoteId): self
    {
        return $this->setData(self::QUOTE_ID, $quoteId);
    }

    public function getCustomerId(): ?int
    {
        $id = $this->getData(self::CUSTOMER_ID);
        return $id ? (int) $id : null;
    }

    public function setCustomerId(?int $customerId): self
    {
        return $this->setData(self::CUSTOMER_ID, $customerId);
    }

    public function getCustomerEmail(): ?string
    {
        return $this->getData(self::CUSTOMER_EMAIL);
    }

    public function setCustomerEmail(string $email): self
    {
        return $this->setData(self::CUSTOMER_EMAIL, $email);
    }

    public function getCustomerName(): ?string
    {
        return $this->getData(self::CUSTOMER_NAME);
    }

    public function setCustomerName(?string $name): self
    {
        return $this->setData(self::CUSTOMER_NAME, $name);
    }

    public function getStoreId(): ?int
    {
        return $this->getData(self::STORE_ID) ? (int) $this->getData(self::STORE_ID) : null;
    }

    public function setStoreId(int $storeId): self
    {
        return $this->setData(self::STORE_ID, $storeId);
    }

    public function getCartValue(): float
    {
        return (float) $this->getData(self::CART_VALUE);
    }

    public function setCartValue(float $value): self
    {
        return $this->setData(self::CART_VALUE, $value);
    }

    public function getItemsCount(): int
    {
        return (int) $this->getData(self::ITEMS_COUNT);
    }

    public function setItemsCount(int $count): self
    {
        return $this->setData(self::ITEMS_COUNT, $count);
    }

    public function getAbandonedAt(): ?string
    {
        return $this->getData(self::ABANDONED_AT);
    }

    public function setAbandonedAt(string $datetime): self
    {
        return $this->setData(self::ABANDONED_AT, $datetime);
    }

    public function isEmail1Sent(): bool
    {
        return (bool) $this->getData(self::EMAIL_1_SENT);
    }

    public function setEmail1Sent(bool $sent): self
    {
        return $this->setData(self::EMAIL_1_SENT, $sent);
    }

    public function getEmail1SentAt(): ?string
    {
        return $this->getData(self::EMAIL_1_SENT_AT);
    }

    public function setEmail1SentAt(?string $datetime): self
    {
        return $this->setData(self::EMAIL_1_SENT_AT, $datetime);
    }

    public function isEmail2Sent(): bool
    {
        return (bool) $this->getData(self::EMAIL_2_SENT);
    }

    public function setEmail2Sent(bool $sent): self
    {
        return $this->setData(self::EMAIL_2_SENT, $sent);
    }

    public function getEmail2SentAt(): ?string
    {
        return $this->getData(self::EMAIL_2_SENT_AT);
    }

    public function setEmail2SentAt(?string $datetime): self
    {
        return $this->setData(self::EMAIL_2_SENT_AT, $datetime);
    }

    public function isEmail3Sent(): bool
    {
        return (bool) $this->getData(self::EMAIL_3_SENT);
    }

    public function setEmail3Sent(bool $sent): self
    {
        return $this->setData(self::EMAIL_3_SENT, $sent);
    }

    public function getEmail3SentAt(): ?string
    {
        return $this->getData(self::EMAIL_3_SENT_AT);
    }

    public function setEmail3SentAt(?string $datetime): self
    {
        return $this->setData(self::EMAIL_3_SENT_AT, $datetime);
    }

    public function isRecovered(): bool
    {
        return (bool) $this->getData(self::RECOVERED);
    }

    public function setRecovered(bool $recovered): self
    {
        return $this->setData(self::RECOVERED, $recovered);
    }

    public function getRecoveredAt(): ?string
    {
        return $this->getData(self::RECOVERED_AT);
    }

    public function setRecoveredAt(?string $datetime): self
    {
        return $this->setData(self::RECOVERED_AT, $datetime);
    }

    public function getCouponCode(): ?string
    {
        return $this->getData(self::COUPON_CODE);
    }

    public function setCouponCode(?string $code): self
    {
        return $this->setData(self::COUPON_CODE, $code);
    }

    public function getStatus(): string
    {
        return $this->getData(self::STATUS) ?? self::STATUS_PENDING;
    }

    public function setStatus(string $status): self
    {
        return $this->setData(self::STATUS, $status);
    }
}
