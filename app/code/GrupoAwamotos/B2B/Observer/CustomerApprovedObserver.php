<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use GrupoAwamotos\B2B\Model\CompanyService;
use Psr\Log\LoggerInterface;

class CustomerApprovedObserver implements ObserverInterface
{
    private CompanyService $companyService;
    private LoggerInterface $logger;

    public function __construct(
        CompanyService $companyService,
        LoggerInterface $logger
    ) {
        $this->companyService = $companyService;
        $this->logger = $logger;
    }

    public function execute(Observer $observer): void
    {
        $customer = $observer->getEvent()->getCustomer();
        if (!$customer) {
            return;
        }

        $customerId = (int) $customer->getId();
        try {
            $this->companyService->findOrCreateByCustomer($customerId);
        } catch (\Exception $e) {
            $this->logger->error('B2B Company auto-create error: ' . $e->getMessage());
        }
    }
}
