<?php

declare(strict_types=1);

namespace GrupoAwamotos\TawkIntegration\Model;

use GrupoAwamotos\TawkIntegration\Model\ResourceModel\Attendant as AttendantResource;
use GrupoAwamotos\TawkIntegration\Model\ResourceModel\Attendant\CollectionFactory;
use Magento\Framework\App\Area;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

class AttendantService
{
    /**
     * Mapa de atendentes: código => [email, name]
     * Inclui apenas atendentes ativos em awamotos.com.br
     */
    public const ATTENDANTS = [
        'suporte1'  => ['email' => 'suporte1.vendas@awamotos.com.br',  'name' => 'Ana Carolina'],
        'suporte2'  => ['email' => 'suporte2.vendas@awamotos.com.br',  'name' => 'Adrielly'],
        'suporte3'  => ['email' => 'suporte3.vendas@awamotos.com.br',  'name' => 'Livia'],
        'suporte4'  => ['email' => 'suporte4.vendas@awamotos.com.br',  'name' => 'Nathalia'],
        'suporte5'  => ['email' => 'suporte5.vendas@awamotos.com.br',  'name' => 'Claudia'],
        'suporte8'  => ['email' => 'suporte8.vendas@awamotos.com.br',  'name' => 'Maria Carolina'],
        'suporte9'  => ['email' => 'suporte9.vendas@awamotos.com.br',  'name' => 'Adriana Morgana'],
        'suporte11' => ['email' => 'suporte11.vendas@awamotos.com.br', 'name' => 'Tamiris'],
    ];

    private const TEMPLATE_LOGIN = 'tawk_attendant_login_notification';
    private const TEMPLATE_CHAT  = 'tawk_attendant_chat_notification';

    private AttendantFactory $attendantFactory;
    private AttendantResource $attendantResource;
    private CollectionFactory $collectionFactory;
    private TransportBuilder $transportBuilder;
    private ScopeConfigInterface $scopeConfig;
    private StoreManagerInterface $storeManager;
    private LoggerInterface $logger;

    public function __construct(
        AttendantFactory $attendantFactory,
        AttendantResource $attendantResource,
        CollectionFactory $collectionFactory,
        TransportBuilder $transportBuilder,
        ScopeConfigInterface $scopeConfig,
        StoreManagerInterface $storeManager,
        LoggerInterface $logger
    ) {
        $this->attendantFactory  = $attendantFactory;
        $this->attendantResource = $attendantResource;
        $this->collectionFactory = $collectionFactory;
        $this->transportBuilder  = $transportBuilder;
        $this->scopeConfig       = $scopeConfig;
        $this->storeManager      = $storeManager;
        $this->logger            = $logger;
    }

    /**
     * @return array<string, array{email: string, name: string}>
     */
    public function getAttendantList(): array
    {
        return self::ATTENDANTS;
    }

    /**
     * @return array{code: string, email: string, name: string}|null
     */
    public function getAttendantByCode(string $code): ?array
    {
        $info = self::ATTENDANTS[$code] ?? null;
        if ($info === null) {
            return null;
        }
        return ['code' => $code, 'email' => $info['email'], 'name' => $info['name']];
    }

    /**
     * Get assigned attendant for a customer
     *
     * @return array{code: string, email: string, name: string}|null
     */
    public function getAssignedAttendant(int $customerId): ?array
    {
        $model = $this->attendantFactory->create();
        $this->attendantResource->load($model, $customerId, 'customer_id');
        if (!$model->getId()) {
            return null;
        }
        $code = $model->getAttendantCode();
        $info = self::ATTENDANTS[$code] ?? null;
        if ($info === null) {
            return null;
        }
        return ['code' => $code, 'email' => $info['email'], 'name' => $info['name']];
    }

    /**
     * Get existing assignment or auto-assign (round-robin)
     *
     * @return array{code: string, email: string, name: string}
     */
    public function getOrAssign(int $customerId): array
    {
        $attendant = $this->getAssignedAttendant($customerId);
        if ($attendant !== null) {
            return $attendant;
        }
        return $this->autoAssign($customerId);
    }

    /**
     * Auto-assign using least-loaded attendant (round-robin)
     *
     * @return array{code: string, email: string, name: string}
     */
    public function autoAssign(int $customerId): array
    {
        $counts = array_fill_keys(array_keys(self::ATTENDANTS), 0);
        $collection = $this->collectionFactory->create();
        foreach ($collection as $item) {
            $code = (string) $item->getAttendantCode();
            if (isset($counts[$code])) {
                $counts[$code]++;
            }
        }
        asort($counts);
        $code = (string) array_key_first($counts);
        $this->assignAttendant($customerId, $code, true);
        $info = self::ATTENDANTS[$code];
        return ['code' => $code, 'email' => $info['email'], 'name' => $info['name']];
    }

    /**
     * Manually or automatically assign an attendant to a customer
     *
     * @throws LocalizedException
     */
    public function assignAttendant(int $customerId, string $code, bool $auto = false): void
    {
        if (!isset(self::ATTENDANTS[$code])) {
            throw new LocalizedException(__('Código de atendente inválido: %1', $code));
        }
        $model = $this->attendantFactory->create();
        $this->attendantResource->load($model, $customerId, 'customer_id');
        $model->setCustomerId($customerId);
        $model->setAttendantCode($code);
        $model->setAutoAssigned($auto);
        $this->attendantResource->save($model);
    }

    /**
     * Send login notification email to assigned attendant
     *
     * @param array{name: string, email: string, cnpj: string} $customerData
     */
    public function sendLoginNotification(int $customerId, array $customerData): void
    {
        try {
            $attendant = $this->getAssignedAttendant($customerId);
            if ($attendant === null) {
                return;
            }
            $storeId = (int) $this->storeManager->getDefaultStoreView()->getId();
            $this->sendEmail(
                $attendant['email'],
                $attendant['name'],
                self::TEMPLATE_LOGIN,
                [
                    'attendant_name'  => $attendant['name'],
                    'customer_name'   => $customerData['name'],
                    'customer_email'  => $customerData['email'],
                    'cnpj'            => $customerData['cnpj'] ?: 'Não informado',
                    'customer_id'     => (string) $customerId,
                    'login_time'      => date('d/m/Y \à\s H:i:s'),
                    'store_url'       => (string) $this->scopeConfig->getValue('web/secure/base_url'),
                ],
                $storeId
            );
            $this->logger->info(sprintf(
                '[TawkIntegration] Login notification sent → %s (customer #%d)',
                $attendant['name'],
                $customerId
            ));
        } catch (\Exception $e) {
            $this->logger->error('[TawkIntegration] Login notification error: ' . $e->getMessage());
        }
    }

    /**
     * Send chat-start notification email to assigned attendant
     *
     * @param array{name: string, email: string, cnpj: string} $customerData
     */
    public function sendChatNotification(int $customerId, array $customerData, string $chatId): void
    {
        try {
            $attendant = $this->getAssignedAttendant($customerId);
            if ($attendant === null) {
                return;
            }
            $storeId = (int) $this->storeManager->getDefaultStoreView()->getId();
            $this->sendEmail(
                $attendant['email'],
                $attendant['name'],
                self::TEMPLATE_CHAT,
                [
                    'attendant_name'  => $attendant['name'],
                    'customer_name'   => $customerData['name'],
                    'customer_email'  => $customerData['email'],
                    'cnpj'            => $customerData['cnpj'] ?: 'Não informado',
                    'customer_id'     => (string) $customerId,
                    'chat_id'         => $chatId,
                    'chat_time'       => date('d/m/Y \à\s H:i:s'),
                    'store_url'       => (string) $this->scopeConfig->getValue('web/secure/base_url'),
                ],
                $storeId
            );
            $this->logger->info(sprintf(
                '[TawkIntegration] Chat notification sent → %s for chat %s (customer #%d)',
                $attendant['name'],
                $chatId,
                $customerId
            ));
        } catch (\Exception $e) {
            $this->logger->error('[TawkIntegration] Chat notification error: ' . $e->getMessage());
        }
    }

    /**
     * @param array<string, string> $vars
     * @throws \Exception
     */
    private function sendEmail(
        string $toEmail,
        string $toName,
        string $templateId,
        array $vars,
        int $storeId
    ): void {
        $senderEmail = (string) $this->scopeConfig->getValue('trans_email/ident_support/email');
        $senderName  = (string) $this->scopeConfig->getValue('trans_email/ident_support/name');
        if ($senderEmail === '') {
            $senderEmail = 'noreply@awamotos.com.br';
            $senderName  = 'AWA Motos';
        }
        $transport = $this->transportBuilder
            ->setTemplateIdentifier($templateId)
            ->setTemplateOptions(['area' => Area::AREA_FRONTEND, 'store' => $storeId])
            ->setTemplateVars($vars)
            ->setFromByScope(['email' => $senderEmail, 'name' => $senderName])
            ->addTo($toEmail, $toName)
            ->getTransport();
        $transport->sendMessage();
    }
}
