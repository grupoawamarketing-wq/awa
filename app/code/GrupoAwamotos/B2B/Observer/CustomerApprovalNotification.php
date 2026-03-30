<?php
/**
 * Observer para notificar cliente sobre aprovação B2B via WhatsApp
 */
declare(strict_types=1);

namespace GrupoAwamotos\B2B\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use GrupoAwamotos\B2B\Model\Notification\WhatsAppPublisher;
use Psr\Log\LoggerInterface;

class CustomerApprovalNotification implements ObserverInterface
{
    private ScopeConfigInterface $scopeConfig;
    private WhatsAppPublisher $whatsAppPublisher;
    private CustomerRepositoryInterface $customerRepository;
    private LoggerInterface $logger;

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        WhatsAppPublisher $whatsAppPublisher,
        CustomerRepositoryInterface $customerRepository,
        LoggerInterface $logger
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->whatsAppPublisher = $whatsAppPublisher;
        $this->customerRepository = $customerRepository;
        $this->logger = $logger;
    }

    public function execute(Observer $observer): void
    {
        try {
            // Verifica se notificação está habilitada
            if (!$this->isWhatsAppNotificationEnabled()) {
                return;
            }

            $customer = $observer->getEvent()->getCustomer();

            if (!$customer) {
                return;
            }

            // Se recebemos o ID, carrega o customer
            if (is_numeric($customer)) {
                $customer = $this->customerRepository->getById($customer);
            }

            // Obtém telefone
            $phoneAttr = $customer->getCustomAttribute('b2b_phone');
            $phone = $phoneAttr ? $phoneAttr->getValue() : '';

            if (empty($phone)) {
                $this->logger->info('Customer approval notification skipped: no phone for customer ' . $customer->getId());
                return;
            }

            $customerData = [
                'customer_id' => $customer->getId(),
                'customer_name' => $customer->getFirstname() . ' ' . $customer->getLastname(),
                'phone' => $phone
            ];

            $this->whatsAppPublisher->publish('customer_approved', $customerData);

        } catch (\Exception $e) {
            $this->logger->error('B2B Approval WhatsApp Notification Error: ' . $e->getMessage());
        }
    }

    private function isWhatsAppNotificationEnabled(): bool
    {
        $enabled = $this->scopeConfig->getValue(
            'grupoawamotos_b2b/whatsapp/enabled',
            ScopeInterface::SCOPE_STORE
        );

        $typeEnabled = $this->scopeConfig->getValue(
            'grupoawamotos_b2b/whatsapp/notify_customer_approved',
            ScopeInterface::SCOPE_STORE
        );

        return $enabled && $typeEnabled;
    }
}
