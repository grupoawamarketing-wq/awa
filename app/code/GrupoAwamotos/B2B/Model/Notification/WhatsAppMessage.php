<?php
declare(strict_types=1);

namespace GrupoAwamotos\B2B\Model\Notification;

use GrupoAwamotos\B2B\Api\Data\WhatsAppMessageInterface;

class WhatsAppMessage implements WhatsAppMessageInterface
{
    private string $type = '';
    private string $payload = '{}';

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;
        return $this;
    }

    public function getPayload(): string
    {
        return $this->payload;
    }

    public function setPayload(string $payload): self
    {
        $this->payload = $payload;
        return $this;
    }
}
