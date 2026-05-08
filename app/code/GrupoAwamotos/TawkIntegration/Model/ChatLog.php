<?php

declare(strict_types=1);

namespace GrupoAwamotos\TawkIntegration\Model;

use Magento\Framework\Model\AbstractModel;

class ChatLog extends AbstractModel
{
    protected function _construct(): void
    {
        $this->_init(ResourceModel\ChatLog::class);
    }

    public function getChatId(): string
    {
        return (string) $this->getData('chat_id');
    }

    public function setChatId(string $chatId): self
    {
        return $this->setData('chat_id', $chatId);
    }

    public function getCustomerId(): ?int
    {
        $value = $this->getData('customer_id');
        return $value !== null ? (int) $value : null;
    }

    public function setCustomerId(?int $customerId): self
    {
        return $this->setData('customer_id', $customerId);
    }

    public function getVisitorName(): ?string
    {
        return $this->getData('visitor_name');
    }

    public function setVisitorName(?string $name): self
    {
        return $this->setData('visitor_name', $name);
    }

    public function getVisitorEmail(): ?string
    {
        return $this->getData('visitor_email');
    }

    public function setVisitorEmail(?string $email): self
    {
        return $this->setData('visitor_email', $email);
    }

    public function getEvent(): string
    {
        return (string) $this->getData('event');
    }

    public function setEvent(string $event): self
    {
        return $this->setData('event', $event);
    }

    public function getPayload(): ?string
    {
        return $this->getData('payload');
    }

    public function setPayload(?string $payload): self
    {
        return $this->setData('payload', $payload);
    }

    public function getVisitorCity(): ?string
    {
        return $this->getData('visitor_city');
    }

    public function setVisitorCity(?string $city): self
    {
        return $this->setData('visitor_city', $city);
    }

    public function getVisitorCountry(): ?string
    {
        return $this->getData('visitor_country');
    }

    public function setVisitorCountry(?string $country): self
    {
        return $this->setData('visitor_country', $country);
    }

    public function getStartedAt(): ?string
    {
        return $this->getData('started_at');
    }

    public function setStartedAt(?string $startedAt): self
    {
        return $this->setData('started_at', $startedAt);
    }

    public function getEndedAt(): ?string
    {
        return $this->getData('ended_at');
    }

    public function setEndedAt(?string $endedAt): self
    {
        return $this->setData('ended_at', $endedAt);
    }
}
