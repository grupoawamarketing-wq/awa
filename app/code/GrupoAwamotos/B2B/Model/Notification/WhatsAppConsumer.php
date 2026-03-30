<?php
declare(strict_types=1);

namespace GrupoAwamotos\B2B\Model\Notification;

use GrupoAwamotos\B2B\Api\Data\WhatsAppMessageInterface;
use Psr\Log\LoggerInterface;

class WhatsAppConsumer
{
    public function __construct(
        private readonly WhatsAppService $whatsAppService,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Process a queued WhatsApp notification message.
     *
     * @param WhatsAppMessageInterface $message
     */
    public function process(WhatsAppMessageInterface $message): void
    {
        $type = $message->getType();
        $data = json_decode($message->getPayload(), true) ?? [];

        try {
            switch ($type) {
                case 'new_registration':
                    $this->whatsAppService->notifyNewB2BRegistration($data);
                    break;
                case 'new_quote':
                    $this->whatsAppService->notifyNewQuote($data);
                    break;
                case 'new_order':
                    $this->whatsAppService->notifyNewOrder($data);
                    break;
                case 'customer_approved':
                    $this->whatsAppService->notifyCustomerApproval($data);
                    break;
                case 'customer_rejected':
                    $this->whatsAppService->notifyCustomerRejection($data);
                    break;
                case 'quote_response':
                    $this->whatsAppService->notifyQuoteResponse($data);
                    break;
                case 'order_status':
                    $this->whatsAppService->notifyOrderStatusUpdate($data);
                    break;
                case 'send_text':
                    $this->whatsAppService->sendText($data['phone'] ?? '', $data['message_text'] ?? '');
                    break;
                default:
                    $this->logger->warning('[B2B WhatsAppConsumer] Unknown message type: ' . $type, [
                        'type' => $type,
                        'payload' => $message->getPayload(),
                    ]);
            }
        } catch (\Exception $e) {
            $this->logger->error('[B2B WhatsAppConsumer] Failed to process message.', [
                'type' => $type,
                'error' => $e->getMessage(),
                'payload' => $message->getPayload(),
            ]);
        }
    }
}
