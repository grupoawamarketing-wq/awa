<?php

/**
 * Observer para notificar sobre pedidos B2B
 */

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use GrupoAwamotos\B2B\Model\Notification\WhatsAppPublisher;
use GrupoAwamotos\B2B\Model\Attendant\AttendantManager;
use GrupoAwamotos\B2B\Helper\Data as B2BHelper;
use Psr\Log\LoggerInterface;

class OrderNotification implements ObserverInterface
{
    private ScopeConfigInterface $scopeConfig;
    private WhatsAppPublisher $whatsAppPublisher;
    private AttendantManager $attendantManager;
    private CustomerRepositoryInterface $customerRepository;
    private B2BHelper $b2bHelper;
    private LoggerInterface $logger;

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        WhatsAppPublisher $whatsAppPublisher,
        AttendantManager $attendantManager,
        CustomerRepositoryInterface $customerRepository,
        B2BHelper $b2bHelper,
        LoggerInterface $logger
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->whatsAppPublisher = $whatsAppPublisher;
        $this->attendantManager = $attendantManager;
        $this->customerRepository = $customerRepository;
        $this->b2bHelper = $b2bHelper;
        $this->logger = $logger;
    }

    public function execute(Observer $observer): void
    {
        try {
            $order = $observer->getEvent()->getOrder();

            if (!$order || !$order->getCustomerId()) {
                return;
            }

            // Verifica se é cliente B2B
            $customer = $this->customerRepository->getById($order->getCustomerId());
            if (!$this->b2bHelper->isB2BGroup((int) $customer->getGroupId())) {
                return;
            }

            // Obtém telefone do cliente
            $phone = $this->getCustomerPhone($customer);

            // Obtém razão social
            $razaoSocial = '';
            $razaoAttr = $customer->getCustomAttribute('b2b_razao_social');
            if ($razaoAttr) {
                $razaoSocial = $razaoAttr->getValue();
            }

            $orderData = [
                'order_id' => $order->getIncrementId(),
                'customer_id' => $customer->getId(),
                'customer_name' => $customer->getFirstname() . ' ' . $customer->getLastname(),
                'customer_email' => $customer->getEmail(),
                'customer_phone' => $phone,
                'company' => $razaoSocial,
                'total' => 'R$ ' . number_format((float) $order->getGrandTotal(), 2, ',', '.'),
                'items_count' => $order->getTotalItemCount(),
                'city' => $order->getShippingAddress() ? $order->getShippingAddress()->getCity() : '',
                'status' => $order->getStatusLabel()
            ];

            // Notifica equipe via WhatsApp
            if ($this->isWhatsAppNotificationEnabled('notify_new_order')) {
                $this->whatsAppPublisher->publish('new_order', $orderData);
            }

            // Notifica atendente responsável
            if ($this->isAttendantNotificationEnabled('notify_attendant_new_order')) {
                $this->notifyCustomerAttendant((int) $customer->getId(), $orderData);
            }
        } catch (\Exception $e) {
            $this->logger->error('B2B Order Notification Error: ' . $e->getMessage());
        }
    }

    private function getCustomerPhone($customer): string
    {
        $phoneAttr = $customer->getCustomAttribute('b2b_phone');
        if ($phoneAttr && $phoneAttr->getValue()) {
            return $phoneAttr->getValue();
        }

        // Tenta pegar do endereço de cobrança
        $addresses = $customer->getAddresses();
        foreach ($addresses as $address) {
            if ($address->getTelephone()) {
                return $address->getTelephone();
            }
        }

        return '';
    }

    private function isWhatsAppNotificationEnabled(string $type): bool
    {
        $enabled = $this->scopeConfig->getValue(
            'grupoawamotos_b2b/whatsapp/enabled',
            ScopeInterface::SCOPE_STORE
        );

        $typeEnabled = $this->scopeConfig->getValue(
            "grupoawamotos_b2b/whatsapp/{$type}",
            ScopeInterface::SCOPE_STORE
        );

        return $enabled && $typeEnabled;
    }

    private function isAttendantNotificationEnabled(string $type): bool
    {
        $enabled = $this->scopeConfig->getValue(
            'grupoawamotos_b2b/attendants/enabled',
            ScopeInterface::SCOPE_STORE
        );

        $typeEnabled = $this->scopeConfig->getValue(
            "grupoawamotos_b2b/attendants/{$type}",
            ScopeInterface::SCOPE_STORE
        );

        return $enabled && $typeEnabled;
    }

    private function notifyCustomerAttendant(int $customerId, array $orderData): void
    {
        $attendant = $this->attendantManager->getCustomerAttendant($customerId);

        if (!$attendant || empty($attendant['whatsapp'])) {
            return;
        }

        $message = "🛒 *Novo Pedido do Seu Cliente*\n\n" .
            "📦 *Pedido:* #{$orderData['order_id']}\n" .
            "👤 *Cliente:* {$orderData['customer_name']}\n" .
            "🏢 *Empresa:* {$orderData['company']}\n" .
            "💰 *Total:* {$orderData['total']}\n" .
            "📦 *Itens:* {$orderData['items_count']}\n\n" .
            "Acompanhe no painel admin.";

        $this->whatsAppPublisher->publishText($attendant['whatsapp'], $message);
    }
}
