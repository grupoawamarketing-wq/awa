<?php

declare(strict_types=1);

namespace GrupoAwamotos\MarketingIntelligence\Observer;

use GrupoAwamotos\MarketingIntelligence\Model\Service\FunnelEventService;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Psr\Log\LoggerInterface;

/**
 * Sends ProposalSent CAPI event when admin responds to a B2B quote.
 * Event: grupoawamotos_b2b_quote_responded
 */
class SendProposalSent implements ObserverInterface
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

            $quoteId = $observer->getData('quote_request_id');
            $totalValue = $observer->getData('total_value');

            $this->funnelEventService->sendFunnelEvent(
                'ProposalSent',
                $customerId,
                [
                    'content_category' => 'b2b_quote',
                    'quote_request_id' => (string) ($quoteId ?? ''),
                    'value' => $totalValue !== null ? (float) $totalValue : 0.0,
                ]
            );
        } catch (\Exception $e) {
            $this->logger->error(
                'SendProposalSent observer failed — ' . $e->getMessage()
            );
        }
    }
}
