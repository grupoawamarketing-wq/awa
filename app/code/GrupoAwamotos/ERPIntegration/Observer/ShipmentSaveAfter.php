<?php

declare(strict_types=1);

namespace GrupoAwamotos\ERPIntegration\Observer;

use GrupoAwamotos\ERPIntegration\Model\WhatsApp\ZApiClient;
use GrupoAwamotos\ERPIntegration\Helper\Data as Helper;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use Magento\Sales\Model\Order\Shipment;
use Psr\Log\LoggerInterface;

/**
 * Observer for Shipment Creation
 *
 * Sends WhatsApp notification via Z-API when a shipment is created
 * with tracking information
 */
class ShipmentSaveAfter implements ObserverInterface
{
    private ZApiClient $zapiClient;
    private Helper $helper;
    private LoggerInterface $logger;

    public function __construct(
        ZApiClient $zapiClient,
        Helper $helper,
        LoggerInterface $logger
    ) {
        $this->zapiClient = $zapiClient;
        $this->helper = $helper;
        $this->logger = $logger;
    }

    /**
     * Execute observer
     */
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

        // Only notify on new shipments
        if (!$shipment->isObjectNew()) {
            return;
        }

        $order = $shipment->getOrder();

        if (!$order) {
            return;
        }

        $this->sendShipmentNotification($order, $shipment);
    }

    /**
     * Send shipment notification via WhatsApp Z-API
     */
    private function sendShipmentNotification($order, Shipment $shipment): void
    {
        try {
            $phoneNumber = $this->getCustomerPhone($order);

            if (!$phoneNumber) {
                return;
            }

            // Get tracking code
            $trackingCode = null;
            $tracks = $shipment->getAllTracks();
            foreach ($tracks as $track) {
                if ($track->getTrackNumber()) {
                    $trackingCode = $track->getTrackNumber();
                    break;
                }
            }

            $result = $this->zapiClient->sendOrderStatus(
                $phoneNumber,
                $order->getIncrementId(),
                'shipped',
                $trackingCode
            );

            if ($result) {
                $this->logger->info(sprintf(
                    '[Z-API Order] Shipment notification sent for order %s (tracking: %s)',
                    $order->getIncrementId(),
                    $trackingCode ?? 'N/A'
                ));

                // Add shipment comment
                $shipment->addComment(
                    sprintf('WhatsApp (Z-API): Notificacao de envio enviada. Rastreio: %s', $trackingCode ?? 'N/A')
                );
            }
        } catch (\Exception $e) {
            $this->logger->error(sprintf(
                '[Z-API Order] Error sending shipment notification: %s',
                $e->getMessage()
            ));
        }
    }

    /**
     * Get customer phone number from order
     */
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
