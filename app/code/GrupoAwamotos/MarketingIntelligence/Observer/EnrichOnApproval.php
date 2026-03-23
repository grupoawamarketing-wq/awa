<?php

declare(strict_types=1);

namespace GrupoAwamotos\MarketingIntelligence\Observer;

use GrupoAwamotos\MarketingIntelligence\Model\Service\LeadEnricher;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Psr\Log\LoggerInterface;

/**
 * On B2B customer approval, cross-reference prospects.
 */
class EnrichOnApproval implements ObserverInterface
{
    public function __construct(
        private readonly LeadEnricher $leadEnricher,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(Observer $observer): void
    {
        try {
            $matched = $this->leadEnricher->enrichAll();
            $customerId = (int) $observer->getData('customer_id');

            $this->logger->info(sprintf(
                'MarketingIntelligence: enrichment triggered by B2B approval of customer #%d — %d prospect(s) matched.',
                $customerId,
                $matched
            ));
        } catch (\Exception $e) {
            $this->logger->error(
                'MarketingIntelligence: enrichment failed after B2B approval — ' . $e->getMessage()
            );
        }
    }
}
