<?php

declare(strict_types=1);

namespace GrupoAwamotos\TawkIntegration\Observer;

use GrupoAwamotos\B2B\Model\CustomerApproval;
use GrupoAwamotos\TawkIntegration\Model\AttendantService;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Psr\Log\LoggerInterface;

class CustomerLogin implements ObserverInterface
{
    private AttendantService $attendantService;
    private CustomerApproval $customerApproval;
    private LoggerInterface $logger;

    public function __construct(
        AttendantService $attendantService,
        CustomerApproval $customerApproval,
        LoggerInterface $logger
    ) {
        $this->attendantService = $attendantService;
        $this->customerApproval = $customerApproval;
        $this->logger = $logger;
    }

    public function execute(Observer $observer): void
    {
        try {
            /** @var \Magento\Customer\Model\Customer $customer */
            $customer   = $observer->getCustomer();
            $customerId = (int) $customer->getId();
            if ($customerId <= 0) {
                return;
            }

            // Assign attendant if not yet assigned (auto round-robin)
            $this->attendantService->getOrAssign($customerId);

            // Send login notification only for B2B / PJ customers
            $cnpj       = (string) $customer->getData('cnpj');
            $personType = (string) $customer->getData('person_type');
            $isB2B      = $cnpj !== ''
                || $personType === 'pj'
                || $this->customerApproval->isApproved($customerId);

            if (!$isB2B) {
                return;
            }

            $this->attendantService->sendLoginNotification($customerId, [
                'name'  => trim($customer->getFirstname() . ' ' . $customer->getLastname()),
                'email' => (string) $customer->getEmail(),
                'cnpj'  => $cnpj,
            ]);
        } catch (\Exception $e) {
            $this->logger->error(
                '[TawkIntegration] CustomerLogin observer error: ' . $e->getMessage()
            );
        }
    }
}
