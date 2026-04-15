<?php

declare(strict_types=1);

namespace GrupoAwamotos\ERPIntegration\Observer;

use GrupoAwamotos\SmartSuggestions\Api\WhatsappSenderInterface;
use GrupoAwamotos\ERPIntegration\Helper\Data as Helper;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use Magento\Sales\Model\Order\Shipment;
use Psr\Log\LoggerInterface;

/**
 * Observer for Shipment Creation
 *
 * Sends WhatsApp notification when a shipment is created with tracking information.
 */
class ShipmentSaveAfter implements ObserverInterface
{
    public function __construct(
        private readonly WhatsappSenderInterface $whatsappSender,
        private readonly Helper $helper,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(Observer $observer): void
    {
        if (!$this->helper->isWhatsAppOrderStatusEnabled()) {
            return;
        }

        /** @var Shipment $shipment */
        $shipment = $observer->getEvent()->getShipment();

        if (!$shipment || !$shipment->getId()) {
            return;
        }

        if (!$shipment->isObjectNew()) {
            return;
        }

        $order = $shipment->getOrder();
        if (!$order) {
            return;
        }

        $this->sendShipmentNotification($order, $shipment);
    }

    private function sendShipmentNotification($order, Shipment $shipment): void
    {
        try {
            $phoneNumber = $this->getCustomerPhone($order);
            if (!$phoneNumber) {
                return;
            }

            $trackingCode = null;
            foreach ($shipment->getAllTracks() as $track) {
                if ($track->getTrackNumber()) {
                    $trackingCode = $track->getTrackNumber();
                    break;
                }
            }

            $message = $trackingCode
                ? "🚚 Seu pedido #{$order->getIncrementId()} foi enviado!\nRastreio: *{$trackingCode}*\nAcompanhe a entrega com seu código."
                : "🚚 Seu pedido #{$order->getIncrementId()} foi enviado!\nEm breve você receberá o código de rastreio.";

            $result = $this->whatsappSender->sendMessage($phoneNumber, $message);

            if ($result['success'] ?? false) {
                $this->logger->info(sprintf(
                    '[WhatsApp Shipment] Notification sent for order %s (tracking: %s)',
                    $order->getIncrementId(),
                    $trackingCode ?? 'N/A'
                ));
                $shipment->addComment(
                    sprintf('WhatsApp: Notificação de envio enviada. Rastreio: %s', $trackingCode ?? 'N/A')
                );
            } else {
                $this->logger->warning(sprintf(
                    '[WhatsApp Shipment] Failed for order %s: %s',
                    $order->getIncrementId(),
                    $result['message'] ?? 'unknown error'
                ));
            }
        } catch (\Exception $e) {
            $this->logger->error(sprintf(
                '[WhatsApp Shipment] Error for order %s: %s',
                $order->getIncrementId(),
                $e->getMessage()
            ));
        }
    }

    private function getCustomerPhone($order): ?string
    {
        $shippingAddress = $order->getShippingAddress();
        if ($shippingAddress && $shippingAddress->getTelephone()) {
            return $shippingAddress->getTelephone();
        }

        $billingAddress = $order->getBillingAddress();
        if ($billingAddress && $billingAddress->getTelephone()) {
            return $billingAddress->getTelephone();
        }

        return null;
    }
}
