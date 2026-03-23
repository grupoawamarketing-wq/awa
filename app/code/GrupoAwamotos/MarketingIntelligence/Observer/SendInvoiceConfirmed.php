<?php

declare(strict_types=1);

namespace GrupoAwamotos\MarketingIntelligence\Observer;

use GrupoAwamotos\MarketingIntelligence\Model\Service\FunnelEventService;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Psr\Log\LoggerInterface;

/**
 * Sends InvoiceConfirmed CAPI event when ERP confirms invoice/NF-e.
 * Event: grupoawamotos_erp_order_invoiced
 */
class SendInvoiceConfirmed implements ObserverInterface
{
    public function __construct(
        private readonly FunnelEventService $funnelEventService,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(Observer $observer): void
    {
        try {
            $customerId = (int) $observer->getData('customer_id');
            if ($customerId <= 0) {
                return;
            }

            $orderId = $observer->getData('order_increment_id');
            $orderTotal = $observer->getData('order_total');

            $this->funnelEventService->sendFunnelEvent(
                'InvoiceConfirmed',
                $customerId,
                [
                    'content_category' => 'erp_invoice',
                    'order_id' => (string) ($orderId ?? ''),
                    'value' => $orderTotal !== null ? (float) $orderTotal : 0.0,
                ]
            );
        } catch (\Exception $e) {
            $this->logger->error(
                'SendInvoiceConfirmed observer failed — ' . $e->getMessage()
            );
        }
    }
}
