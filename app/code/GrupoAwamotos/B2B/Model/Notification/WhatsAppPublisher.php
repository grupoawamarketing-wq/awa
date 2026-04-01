<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Model\Notification;

use GrupoAwamotos\B2B\Api\Data\WhatsAppMessageInterface;
use GrupoAwamotos\B2B\Api\Data\WhatsAppMessageInterfaceFactory;
use Magento\Framework\MessageQueue\PublisherInterface;

class WhatsAppPublisher
{
    private const TOPIC_NAME = 'grupoawamotos.b2b.whatsapp.send';

    public function __construct(
        private readonly PublisherInterface $publisher,
        private readonly WhatsAppMessageInterfaceFactory $messageFactory
    ) {
    }

    /**
     * Enqueue a WhatsApp notification for async processing.
     *
     * @param string $type  Notification type (new_registration, customer_approved, etc.)
     * @param array  $data  Payload data
     */
    public function publish(string $type, array $data): void
    {
        /** @var WhatsAppMessageInterface $msg */
        $msg = $this->messageFactory->create();
        $msg->setType($type)->setPayload((string) json_encode($data));
        $this->publisher->publish(self::TOPIC_NAME, $msg);
    }

    /**
     * Enqueue a raw text message for async delivery.
     *
     * @param string $phone   Target phone number
     * @param string $text    Message text
     */
    public function publishText(string $phone, string $text): void
    {
        /** @var WhatsAppMessageInterface $msg */
        $msg = $this->messageFactory->create();
        $msg->setType('send_text')->setPayload((string) json_encode(['phone' => $phone, 'message_text' => $text]));
        $this->publisher->publish(self::TOPIC_NAME, $msg);
    }
}
