<?php

declare(strict_types=1);

namespace GrupoAwamotos\ERPIntegration\Observer;

use GrupoAwamotos\ERPIntegration\Model\WhatsApp\ZApiClient;
use GrupoAwamotos\ERPIntegration\Helper\Data as Helper;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use Magento\Sales\Model\Order;
use Psr\Log\LoggerInterface;

/**
 * Observer for Order Status Changes
 *
 * Sends WhatsApp notifications via Z-API when order status changes to:
 * - processing (Pedido Confirmado)
 * - complete (Pedido Entregue)
 * - shipped (Pedido Enviado) - via shipment event
 * - canceled (Pedido Cancelado)
 */
class OrderStatusChange implements ObserverInterface
{
    private ZApiClient $zapiClient;
    private Helper $helper;
    private LoggerInterface $logger;

    /**
     * Status changes that trigger notifications
     */
    private array $notifiableStatuses = [
        'processing' => 'processing',
        'complete' => 'complete',
        'canceled' => 'canceled',
        'holded' => 'holded',
    ];

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

        /** @var Order $order */
        $order = $observer->getEvent()->getOrder();

        if (!$order || !$order->getId()) {
            return;
        }

        $newStatus = $order->getStatus();
        $originalStatus = $order->getOrigData('status');

        // Only notify on status change
        if ($newStatus === $originalStatus) {
            return;
        }

        // Check if this status should trigger a notification
        if (!isset($this->notifiableStatuses[$newStatus])) {
            return;
        }

        $this->sendOrderStatusNotification($order, $this->notifiableStatuses[$newStatus]);
    }

    /**
     * Send order status notification via WhatsApp Z-API
     */
    private function sendOrderStatusNotification(Order $order, string $status): void
    {
        try {
            $phoneNumber = $this->getCustomerPhone($order);

            if (!$phoneNumber) {
                $this->logger->info(sprintf(
                    '[Z-API Order] No phone for order %s, skipping notification',
                    $order->getIncrementId()
                ));
                return;
            }

            $result = $this->zapiClient->sendOrderStatus(
                $phoneNumber,
                $order->getIncrementId(),
                $status,
                $this->getTrackingCode($order)
            );

            if ($result) {
                $this->logger->info(sprintf(
                    '[Z-API Order] Status notification sent for order %s (status: %s)',
                    $order->getIncrementId(),
                    $status
                ));

                // Add order comment
                $order->addCommentToStatusHistory(
                    sprintf('WhatsApp (Z-API): Notificacao de status "%s" enviada.', $status)
                );
            }
        } catch (\Exception $e) {
            $this->logger->error(sprintf(
                '[Z-API Order] Error sending notification for order %s: %s',
                $order->getIncrementId(),
                $e->getMessage()
            ));
        }
    }

    /**
     * Get customer phone number from order
     */
    private function getCustomerPhone(Order $order): ?string
    {
        // Try shipping address first
        $shippingAddress = $order->getShippingAddress();
        if ($shippingAddress && $shippingAddress->getTelephone()) {
            return $shippingAddress->getTelephone();
        }

        // Try billing address
        $billingAddress = $order->getBillingAddress();
        if ($billingAddress && $billingAddress->getTelephone()) {
            return $billingAddress->getTelephone();
        }

        return null;
    }

    /**
     * Get tracking code from shipment
     */
    private function getTrackingCode(Order $order): ?string
    {
        $shipments = $order->getShipmentsCollection();

        foreach ($shipments as $shipment) {
            $tracks = $shipment->getAllTracks();
            foreach ($tracks as $track) {
                if ($track->getTrackNumber()) {
                    return $track->getTrackNumber();
                }
            }
        }

        return null;
    }
}
