<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Api\Data;

interface WhatsAppMessageInterface
{
    /**
     * Notification type key (e.g. new_registration, customer_approved)
     *
     * @return string
     */
    public function getType(): string;

    /**
     * @param string $type
     * @return $this
     */
    public function setType(string $type): self;

    /**
     * JSON-encoded payload array
     *
     * @return string
     */
    public function getPayload(): string;

    /**
     * @param string $payload
     * @return $this
     */
    public function setPayload(string $payload): self;
}
