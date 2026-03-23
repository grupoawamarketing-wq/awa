<?php

declare(strict_types=1);

namespace GrupoAwamotos\MarketingIntelligence\Observer;

use GrupoAwamotos\MarketingIntelligence\Model\Service\FunnelEventService;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Psr\Log\LoggerInterface;

/**
 * Sends CreditApproved CAPI event when B2B credit limit is assigned/increased.
 * Event: grupoawamotos_b2b_credit_approved
 */
class SendCreditApproved implements ObserverInterface
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

            $creditLimit = $observer->getData('credit_limit');

            $this->funnelEventService->sendFunnelEvent(
                'CreditApproved',
                $customerId,
                [
                    'content_category' => 'b2b_credit',
                    'value' => $creditLimit !== null ? (float) $creditLimit : 0.0,
                ]
            );
        } catch (\Exception $e) {
            $this->logger->error(
                'SendCreditApproved observer failed — ' . $e->getMessage()
            );
        }
    }
}
