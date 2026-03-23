<?php

declare(strict_types=1);

namespace GrupoAwamotos\MarketingIntelligence\Observer;

use GrupoAwamotos\MarketingIntelligence\Model\Service\FunnelEventService;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Psr\Log\LoggerInterface;

/**
 * Sends QualifiedLead CAPI event when a B2B customer is approved.
 * Event: grupoawamotos_b2b_customer_approved
 */
class SendQualifiedLead implements ObserverInterface
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

            $this->funnelEventService->sendFunnelEvent(
                'QualifiedLead',
                $customerId,
                [
                    'lead_type' => 'b2b_approved',
                    'content_category' => 'b2b_qualification',
                ]
            );
        } catch (\Exception $e) {
            $this->logger->error(
                'SendQualifiedLead observer failed — ' . $e->getMessage()
            );
        }
    }
}
