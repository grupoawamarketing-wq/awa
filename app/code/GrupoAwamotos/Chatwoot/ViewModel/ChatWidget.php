<?php

declare(strict_types=1);

namespace GrupoAwamotos\Chatwoot\ViewModel;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\GroupRepositoryInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Store\Model\ScopeInterface;
use Psr\Log\LoggerInterface;

class ChatWidget implements ArgumentInterface
{
    private const XML_PATH_PREFIX = 'grupoawamotos_chatwoot/general/';
    private const XML_APPEARANCE_PREFIX = 'grupoawamotos_chatwoot/appearance/';
    private const XML_NOTIFICATIONS_PREFIX = 'grupoawamotos_chatwoot/notifications/';

    /** Máximo de pedidos ERP pendentes a exibir no chat */
    private const ERP_PENDING_ORDERS_LIMIT = 3;

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly CustomerSession $customerSession,
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly GroupRepositoryInterface $groupRepository,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly EncryptorInterface $encryptor,
        private readonly ResourceConnection $resourceConnection,
        private readonly LoggerInterface $logger
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_PREFIX . 'enabled',
            ScopeInterface::SCOPE_STORE
        );
    }

    public function getBaseUrl(): string
    {
        return rtrim(
            (string) $this->scopeConfig->getValue(
                self::XML_PATH_PREFIX . 'base_url',
                ScopeInterface::SCOPE_STORE
            ),
            '/'
        );
    }

    public function getWebsiteToken(): string
    {
        return (string) $this->scopeConfig->getValue(
            self::XML_PATH_PREFIX . 'website_token',
            ScopeInterface::SCOPE_STORE
        );
    }

    public function getLocale(): string
    {
        return (string) $this->scopeConfig->getValue(
            self::XML_PATH_PREFIX . 'locale',
            ScopeInterface::SCOPE_STORE
        );
    }

    public function getPosition(): string
    {
        return (string) $this->scopeConfig->getValue(
            self::XML_PATH_PREFIX . 'position',
            ScopeInterface::SCOPE_STORE
        );
    }

    public function shouldIdentifyCustomer(): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_PREFIX . 'identify_customer',
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Retorna o HMAC token desencriptado para identity validation.
     */
    public function getHmacToken(): string
    {
        $value = (string) $this->scopeConfig->getValue(
            self::XML_PATH_PREFIX . 'hmac_token',
            ScopeInterface::SCOPE_STORE
        );

        if ($value === '') {
            return '';
        }

        return $this->encryptor->decrypt($value);
    }

    /**
     * Gera o HMAC hash do identificador do cliente (email).
     * Usado para identity validation no Chatwoot SDK.
     *
     * @return string Hash HMAC-SHA256 ou string vazia se desabilitado
     */
    public function getIdentifierHash(): string
    {
        $hmacToken = $this->getHmacToken();
        if ($hmacToken === '') {
            return '';
        }

        $email = $this->getCustomerEmail();
        if ($email === '') {
            return '';
        }

        return hash_hmac('sha256', $email, $hmacToken);
    }

    public function getDarkMode(): string
    {
        return (string) $this->scopeConfig->getValue(
            self::XML_APPEARANCE_PREFIX . 'dark_mode',
            ScopeInterface::SCOPE_STORE
        ) ?: 'auto';
    }

    public function showPopoutButton(): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_APPEARANCE_PREFIX . 'show_popout_button',
            ScopeInterface::SCOPE_STORE
        );
    }

    public function getWidgetColor(): string
    {
        return trim(
            (string) $this->scopeConfig->getValue(
                self::XML_APPEARANCE_PREFIX . 'widget_color',
                ScopeInterface::SCOPE_STORE
            )
        );
    }

    public function isSoundEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_NOTIFICATIONS_PREFIX . 'enable_sounds',
            ScopeInterface::SCOPE_STORE
        );
    }

    public function usePushNotifications(): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_NOTIFICATIONS_PREFIX . 'use_push_notifications',
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Retorna objeto de configurações do widget (chatwootSettings).
     *
     * @return array<string, mixed>
     */
    public function getWidgetSettings(): array
    {
        $settings = [
            'darkMode' => $this->getDarkMode(),
            'showPopoutButton' => $this->showPopoutButton(),
        ];

        $color = $this->getWidgetColor();
        if ($color !== '') {
            $settings['widgetColor'] = $color;
        }

        return $settings;
    }

    public function isCustomerLoggedIn(): bool
    {
        return $this->customerSession->isLoggedIn();
    }

    public function getCustomerName(): string
    {
        $customer = $this->customerSession->getCustomer();
        return $customer ? (string) $customer->getName() : '';
    }

    public function getCustomerEmail(): string
    {
        $customer = $this->customerSession->getCustomer();
        return $customer ? (string) $customer->getEmail() : '';
    }

    /**
     * @return array<string, string|null>
     */
    public function getCustomerIdentifier(): array
    {
        if (!$this->shouldIdentifyCustomer() || !$this->isCustomerLoggedIn()) {
            return [];
        }

        return [
            'email' => $this->getCustomerEmail(),
            'name' => $this->getCustomerName(),
        ];
    }

    /**
     * Dados B2B completos do cliente para exibir às atendentes via custom_attributes.
     *
     * @return array<string, string|null>
     */
    public function getB2bData(): array
    {
        if (!$this->isCustomerLoggedIn()) {
            return [];
        }

        try {
            $customerId = (int) $this->customerSession->getCustomerId();
            $customer = $this->customerRepository->getById($customerId);

            $groupName = $this->getGroupName((int) $customer->getGroupId());
            $cnpj = $this->getCustomerAttribute($customer, 'b2b_cnpj')
                ?: $this->getCustomerAttribute($customer, 'cnpj');
            $razaoSocial = $this->getCustomerAttribute($customer, 'b2b_razao_social')
                ?: $this->getCustomerAttribute($customer, 'company_name');
            $personType = $this->getCustomerAttribute($customer, 'b2b_person_type')
                ?: $this->getCustomerAttribute($customer, 'person_type');
            $approvalStatus = $this->getCustomerAttribute($customer, 'b2b_approval_status');
            $phone = $this->getCustomerAttribute($customer, 'b2b_phone');
            $ie = $this->getCustomerAttribute($customer, 'b2b_inscricao_estadual')
                ?: $this->getCustomerAttribute($customer, 'ie');

            $lastOrder = $this->getLastOrderInfo($customerId);

            $data = [
                'tipo_pessoa' => $this->formatPersonType($personType),
                'grupo' => $groupName,
            ];

            if ($cnpj) {
                $data['cnpj'] = $cnpj;
            }
            if ($razaoSocial) {
                $data['razao_social'] = $razaoSocial;
            }
            if ($ie) {
                $data['ie'] = $ie;
            }
            if ($phone) {
                $data['telefone'] = $phone;
            }
            if ($approvalStatus) {
                $data['status_b2b'] = $this->formatApprovalStatus($approvalStatus);
            }
            if ($lastOrder) {
                $data['ultimo_pedido'] = $lastOrder;
            }

            return $data;
        } catch (\Exception $e) {
            $this->logger->error('Chatwoot B2B data error', [
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Dados do ERP Sectra do cliente logado.
     * Inclui código ERP, pedidos pendentes e último pedido sincronizado.
     *
     * @return array<string, string>
     */
    public function getErpData(): array
    {
        if (!$this->isCustomerLoggedIn()) {
            return [];
        }

        try {
            $customerId = (int) $this->customerSession->getCustomerId();
            $connection = $this->resourceConnection->getConnection();

            // Código ERP e tipo do cliente na Sectra
            $erpClient = $connection->fetchRow(
                'SELECT erp_code, tipo_pessoa, cpf_cnpj FROM vw_sectra_clientes_b2b WHERE magento_customer_id = :cid LIMIT 1',
                [':cid' => $customerId]
            );

            if (empty($erpClient) || empty($erpClient['erp_code'])) {
                return [];
            }

            $data = [
                'erp_codigo' => $erpClient['erp_code'],
            ];

            // Pedidos pendentes (em processamento no ERP)
            $pending = $connection->fetchAll(
                'SELECT pedido_web, estado, total, data_pedido
                 FROM vw_sectra_pedidos_pendentes
                 WHERE customer_id = :cid
                 ORDER BY data_pedido DESC
                 LIMIT ' . (int) self::ERP_PENDING_ORDERS_LIMIT,
                [':cid' => $customerId]
            );

            if (!empty($pending)) {
                $lines = [];
                foreach ($pending as $p) {
                    $date = (new \DateTime($p['data_pedido']))->format('d/m/Y');
                    $total = 'R$ ' . number_format((float) $p['total'], 2, ',', '.');
                    $lines[] = sprintf('%s — %s — %s (%s)', $p['pedido_web'], $total, $p['estado'], $date);
                }
                $data['pedidos_erp_pendentes'] = implode(' | ', $lines);
            }

            return $data;
        } catch (\Exception $e) {
            $this->logger->error('Chatwoot ERP data error', [
                'customer_id' => $this->customerSession->getCustomerId(),
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * @param \Magento\Customer\Api\Data\CustomerInterface $customer
     */
    private function getCustomerAttribute($customer, string $code): ?string
    {
        $attr = $customer->getCustomAttribute($code);
        if ($attr === null) {
            return null;
        }
        $value = $attr->getValue();
        return $value !== null && $value !== '' ? (string) $value : null;
    }

    private function getGroupName(int $groupId): string
    {
        try {
            return $this->groupRepository->getById($groupId)->getCode();
        } catch (\Exception $e) {
            return 'Varejo';
        }
    }

    private function getLastOrderInfo(int $customerId): ?string
    {
        try {
            $searchCriteria = $this->searchCriteriaBuilder
                ->addFilter('customer_id', $customerId)
                ->setPageSize(1)
                ->setCurrentPage(1)
                ->create();

            $searchCriteria->setSortOrders([
                new \Magento\Framework\Api\SortOrder([
                    'field' => 'created_at',
                    'direction' => 'DESC',
                ]),
            ]);

            $orders = $this->orderRepository->getList($searchCriteria);
            $items = $orders->getItems();

            if (empty($items)) {
                return null;
            }

            $order = reset($items);
            $date = (new \DateTime($order->getCreatedAt()))->format('d/m/Y');
            $total = number_format((float) $order->getGrandTotal(), 2, ',', '.');

            return sprintf(
                '#%s — R$ %s (%s) — %s',
                $order->getIncrementId(),
                $total,
                $order->getStatusLabel(),
                $date
            );
        } catch (\Exception $e) {
            return null;
        }
    }

    private function formatPersonType(?string $type): string
    {
        return match ($type) {
            'pj' => 'Pessoa Jurídica',
            'pf' => 'Pessoa Física',
            default => 'Não informado',
        };
    }

    private function formatApprovalStatus(?string $status): string
    {
        return match ($status) {
            'approved' => 'Aprovado',
            'pending' => 'Pendente',
            'rejected' => 'Rejeitado',
            default => $status ?? 'N/A',
        };
    }
}
