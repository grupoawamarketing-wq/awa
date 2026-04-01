<?php

/**
 * Observer para notificar sobre mudanças de status de pedido
 */

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Store\Model\ScopeInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use GrupoAwamotos\B2B\Model\Notification\WhatsAppPublisher;
use GrupoAwamotos\B2B\Helper\Data as B2BHelper;
use Psr\Log\LoggerInterface;

class OrderStatusNotification implements ObserverInterface
{
    private ScopeConfigInterface $scopeConfig;
    private State $appState;
    private WhatsAppPublisher $whatsAppPublisher;
    private CustomerRepositoryInterface $customerRepository;
    private B2BHelper $b2bHelper;
    private LoggerInterface $logger;
    private array $notifiedOrders = [];

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        State $appState,
        WhatsAppPublisher $whatsAppPublisher,
        CustomerRepositoryInterface $customerRepository,
        B2BHelper $b2bHelper,
        LoggerInterface $logger
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->appState = $appState;
        $this->whatsAppPublisher = $whatsAppPublisher;
        $this->customerRepository = $customerRepository;
        $this->b2bHelper = $b2bHelper;
        $this->logger = $logger;
    }

    public function execute(Observer $observer): void
    {
        try {
            // Garante area code definido (pode rodar via cron sem area)
            try {
                $this->appState->getAreaCode();
            } catch (\Magento\Framework\Exception\LocalizedException $e) {
                $this->appState->setAreaCode(Area::AREA_FRONTEND);
            }

            // Verifica se notificação está habilitada
            if (!$this->isWhatsAppNotificationEnabled()) {
                return;
            }

            $order = $observer->getEvent()->getOrder();

            if (!$order || !$order->getCustomerId()) {
                return;
            }

            // Verifica se houve mudança de status
            $originalStatus = $order->getOrigData('status');
            $newStatus = $order->getStatus();

            if ($originalStatus === $newStatus) {
                return;
            }

            // Evita notificações duplicadas na mesma requisição
            $orderKey = $order->getId() . '_' . $newStatus;
            if (isset($this->notifiedOrders[$orderKey])) {
                return;
            }
            $this->notifiedOrders[$orderKey] = true;

            // Verifica se é cliente B2B
            $customer = $this->customerRepository->getById($order->getCustomerId());
            if (!$this->b2bHelper->isB2BGroup((int) $customer->getGroupId())) {
                return;
            }

            // Obtém telefone
            $phoneAttr = $customer->getCustomAttribute('b2b_phone');
            $phone = $phoneAttr ? $phoneAttr->getValue() : '';

            if (empty($phone)) {
                return;
            }

            // Monta dados do pedido
            $trackingInfo = $this->getTrackingInfo($order);

            $orderData = [
                'order_id' => $order->getIncrementId(),
                'customer_name' => $customer->getFirstname(),
                'status' => $this->getStatusLabel($newStatus),
                'tracking_info' => $trackingInfo,
                'customer_phone' => $phone
            ];

            $this->whatsAppPublisher->publish('order_status', $orderData);
        } catch (\Exception $e) {
            $this->logger->error('B2B Order Status Notification Error: ' . $e->getMessage());
        }
    }

    private function isWhatsAppNotificationEnabled(): bool
    {
        $enabled = $this->scopeConfig->getValue(
            'grupoawamotos_b2b/whatsapp/enabled',
            ScopeInterface::SCOPE_STORE
        );

        $typeEnabled = $this->scopeConfig->getValue(
            'grupoawamotos_b2b/whatsapp/notify_order_status',
            ScopeInterface::SCOPE_STORE
        );

        return $enabled && $typeEnabled;
    }

    private function getTrackingInfo($order): string
    {
        $tracks = [];
        foreach ($order->getShipmentsCollection() as $shipment) {
            foreach ($shipment->getAllTracks() as $track) {
                $tracks[] = "🚚 *{$track->getTitle()}:* {$track->getNumber()}";
            }
        }

        return !empty($tracks) ? implode("\n", $tracks) : '';
    }

    private function getStatusLabel(string $status): string
    {
        $labels = [
            'pending' => '⏳ Pendente',
            'pending_payment' => '💳 Aguardando Pagamento',
            'processing' => '🔄 Em Processamento',
            'complete' => '✅ Entregue',
            'canceled' => '❌ Cancelado',
            'closed' => '🔒 Fechado',
            'holded' => '⏸️ Em Espera',
            'payment_review' => '🔍 Revisão de Pagamento',
            'fraud' => '⚠️ Suspeita de Fraude'
        ];

        return $labels[$status] ?? ucfirst($status);
    }
}
