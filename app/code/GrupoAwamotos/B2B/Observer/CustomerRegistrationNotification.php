<?php
/**
 * Observer para notificar sobre novos cadastros B2B
 */
declare(strict_types=1);

namespace GrupoAwamotos\B2B\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use GrupoAwamotos\B2B\Model\Notification\WhatsAppPublisher;
use GrupoAwamotos\B2B\Model\Attendant\AttendantManager;
use GrupoAwamotos\B2B\Helper\Config as B2BConfig;
use Psr\Log\LoggerInterface;

class CustomerRegistrationNotification implements ObserverInterface
{
    private ScopeConfigInterface $scopeConfig;
    private WhatsAppPublisher $whatsAppPublisher;
    private AttendantManager $attendantManager;
    private LoggerInterface $logger;
    private B2BConfig $b2bConfig;

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        WhatsAppPublisher $whatsAppPublisher,
        AttendantManager $attendantManager,
        LoggerInterface $logger,
        B2BConfig $b2bConfig
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->whatsAppPublisher = $whatsAppPublisher;
        $this->attendantManager = $attendantManager;
        $this->logger = $logger;
        $this->b2bConfig = $b2bConfig;
    }

    public function execute(Observer $observer): void
    {
        try {
            $customer = $observer->getEvent()->getCustomer();
            $data = $observer->getEvent()->getData();

            // Verifica se é cliente B2B pendente
            $pendingGroupId = $this->b2bConfig->getPendingGroupId();
            if ($customer->getGroupId() != $pendingGroupId) {
                return;
            }

            $customerData = [
                'customer_id' => $customer->getId(),
                'customer_name' => $customer->getFirstname() . ' ' . $customer->getLastname(),
                'email' => $customer->getEmail(),
                'razao_social' => $data['razao_social'] ?? '',
                'cnpj' => $data['cnpj'] ?? '',
                'phone' => $data['phone'] ?? ''
            ];

            // Atribuição automática de atendente
            if ($this->isAutoAssignEnabled()) {
                $attendantId = $this->attendantManager->autoAssignCustomer(
                    (int) $customer->getId(),
                    'b2b'
                );

                if ($attendantId) {
                    $this->notifyAttendant($attendantId, $customerData);
                }
            }

            // Notificação via WhatsApp para equipe
            if ($this->isWhatsAppNotificationEnabled('notify_new_registration')) {
                $this->whatsAppPublisher->publish('new_registration', $customerData);
            }

        } catch (\Exception $e) {
            $this->logger->error('B2B Registration Notification Error: ' . $e->getMessage());
        }
    }

    private function isAutoAssignEnabled(): bool
    {
        return (bool) $this->scopeConfig->getValue(
            'grupoawamotos_b2b/attendants/auto_assign',
            ScopeInterface::SCOPE_STORE
        );
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

    private function notifyAttendant(int $attendantId, array $customerData): void
    {
        $attendant = $this->attendantManager->getAttendantById($attendantId);

        if (!$attendant || empty($attendant['whatsapp'])) {
            return;
        }

        $message = "🆕 *Novo Cliente Atribuído*\n\n" .
            "👤 *Nome:* {$customerData['customer_name']}\n" .
            "🏢 *Empresa:* {$customerData['razao_social']}\n" .
            "📧 *Email:* {$customerData['email']}\n" .
            "📱 *Telefone:* {$customerData['phone']}\n\n" .
            "Este cliente foi atribuído a você para atendimento.";

        $this->whatsAppPublisher->publishText($attendant['whatsapp'], $message);
    }
}
