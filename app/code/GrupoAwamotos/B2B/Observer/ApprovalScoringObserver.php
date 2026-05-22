<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Observer;

use GrupoAwamotos\B2B\Api\ApprovalScoreServiceInterface;
use GrupoAwamotos\B2B\Helper\Config;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Psr\Log\LoggerInterface;

class ApprovalScoringObserver implements ObserverInterface
{
    private Config $config;
    private ApprovalScoreServiceInterface $approvalScoreService;
    private LoggerInterface $logger;

    public function __construct(
        Config $config,
        ApprovalScoreServiceInterface $approvalScoreService,
        LoggerInterface $logger
    ) {
        $this->config = $config;
        $this->approvalScoreService = $approvalScoreService;
        $this->logger = $logger;
    }

    public function execute(Observer $observer): void
    {
        if (!$this->config->isEnabled() || !$this->config->requireApproval()) {
            return;
        }

        if (!$this->config->isApprovalScoringEnabled()) {
            return;
        }

        try {
            /** @var \Magento\Customer\Model\Customer|null $customer */
            $customer = $observer->getEvent()->getCustomer();
            if ($customer === null || !$customer->getId()) {
                return;
            }

            $this->approvalScoreService->processRegistration((int) $customer->getId());
        } catch (\Exception $e) {
            $this->logger->error('B2B ApprovalScoringObserver error: ' . $e->getMessage());
        }
    }
}
