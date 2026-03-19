<?php
/**
 * Price Visibility Service
 */
declare(strict_types=1);

namespace GrupoAwamotos\B2B\Model;

use GrupoAwamotos\B2B\Api\PriceVisibilityInterface;
use GrupoAwamotos\B2B\Helper\Config;
use GrupoAwamotos\B2B\Model\Customer\Attribute\Source\ApprovalStatus;
use GrupoAwamotos\ERPIntegration\Model\ResourceModel\SyncLog as SyncLogResource;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\UrlInterface;
use Psr\Log\LoggerInterface;

class PriceVisibility implements PriceVisibilityInterface
{
    /**
     * @var Config
     */
    private $config;

    /**
     * @var CustomerSession
     */
    private $customerSession;

    /**
     * @var CustomerRepositoryInterface
     */
    private $customerRepository;

    /**
     * @var UrlInterface
     */
    private $urlBuilder;

    /**
     * @var SyncLogResource
     */
    private $syncLogResource;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var bool|null
     */
    private $canViewPricesCache = null;

    /**
     * @var bool|null
     */
    private $canAddToCartCache = null;

    /** @var int|null|false */
    private $erpCodeCache = false;

    public function __construct(
        Config $config,
        CustomerSession $customerSession,
        CustomerRepositoryInterface $customerRepository,
        UrlInterface $urlBuilder,
        ?SyncLogResource $syncLogResource = null,
        ?LoggerInterface $logger = null
    ) {
        $this->config = $config;
        $this->customerSession = $customerSession;
        $this->customerRepository = $customerRepository;
        $this->urlBuilder = $urlBuilder;
        $this->syncLogResource = $syncLogResource;
        $this->logger = $logger ?? new \Psr\Log\NullLogger();
    }

    /**
     * @inheritDoc
     */
    public function canViewPrices(): bool
    {
        if ($this->canViewPricesCache !== null) {
            return $this->canViewPricesCache;
        }

        // Se módulo desabilitado, mostrar preços
        if (!$this->config->isEnabled()) {
            $this->canViewPricesCache = true;
            return true;
        }

        // Se usuário está logado
        if ($this->customerSession->isLoggedIn()) {
            // Usar repository para garantir que custom attributes EAV sejam carregados
            $approvalStatus = $this->getCustomerApprovalStatus();

            // Se não há status definido, considerar como aprovado (compatibilidade)
            if (empty($approvalStatus)) {
                $this->canViewPricesCache = true;
                return true;
            }

            // Cliente aprovado — verificar se tem código ERP
            if ($approvalStatus === ApprovalStatus::STATUS_APPROVED) {
                if ($this->config->hidePriceForNoErp() && $this->getCustomerErpCode() === null) {
                    $this->canViewPricesCache = false;
                    return false;
                }
                $this->canViewPricesCache = true;
                return true;
            }

            // Cliente pendente - depende da configuração
            if ($approvalStatus === ApprovalStatus::STATUS_PENDING) {
                $this->canViewPricesCache = $this->config->showPriceForPending();
                return $this->canViewPricesCache;
            }

            // Cliente rejeitado ou suspenso não vê preços
            $this->canViewPricesCache = false;
            return false;
        }

        // Modo strict: visitantes NUNCA veem preços
        if ($this->config->isStrictB2B()) {
            $this->canViewPricesCache = false;
            return false;
        }

        // Modo mixed: visitante - depende da configuração
        $this->canViewPricesCache = !$this->config->hidePriceForGuests();
        return $this->canViewPricesCache;
    }

    /**
     * @inheritDoc
     */
    public function canAddToCart(): bool
    {
        if ($this->canAddToCartCache !== null) {
            return $this->canAddToCartCache;
        }

        // Se módulo desabilitado, permitir
        if (!$this->config->isEnabled()) {
            $this->canAddToCartCache = true;
            return true;
        }

        // Se usuário está logado
        if ($this->customerSession->isLoggedIn()) {
            if (!$this->isCustomerApproved()) {
                $this->canAddToCartCache = false;
                return false;
            }
            // Aprovado mas sem ERP code — bloquear compra
            if ($this->config->hidePriceForNoErp() && $this->getCustomerErpCode() === null) {
                $this->canAddToCartCache = false;
                return false;
            }
            $this->canAddToCartCache = true;
            return true;
        }

        // Modo strict: visitantes NUNCA podem adicionar ao carrinho
        if ($this->config->isStrictB2B()) {
            $this->canAddToCartCache = false;
            return false;
        }

        // Modo mixed: visitante - depende da configuração
        $this->canAddToCartCache = !$this->config->hideAddToCartForGuests();
        return $this->canAddToCartCache;
    }

    /**
     * @inheritDoc
     */
    public function getPriceReplacementMessage(): string
    {
        if ($this->customerSession->isLoggedIn()) {
            $approvalStatus = $this->getCustomerApprovalStatus();

            // Aprovado mas sem código ERP — tabela de preços em definição
            if ($approvalStatus === ApprovalStatus::STATUS_APPROVED
                && $this->config->hidePriceForNoErp()
                && $this->getCustomerErpCode() === null
            ) {
                $msg = $this->config->getPendingErpMessage();
                return !empty($msg) ? $msg : 'Sua tabela de preços está sendo definida. Consulte o departamento de vendas.';
            }

            // Pendente, rejeitado ou suspenso
            if ($approvalStatus && $approvalStatus !== ApprovalStatus::STATUS_APPROVED) {
                $pendingMsg = $this->config->getPendingMessage();
                if (!empty($pendingMsg)) {
                    return $pendingMsg;
                }
                return 'Sua conta está pendente de aprovação.';
            }
        }

        $message = $this->config->getLoginMessage();

        if (empty($message)) {
            $message = '<a href="{{login_url}}">Faça login</a> para ver os preços';
        }

        // Substituir placeholders
        $loginUrl = $this->config->isStrictB2B()
            ? $this->urlBuilder->getUrl('b2b/account/login')
            : $this->urlBuilder->getUrl('customer/account/login');
        $registerUrl = $this->config->isEnabled()
            ? $this->urlBuilder->getUrl('b2b/register')
            : $this->urlBuilder->getUrl('customer/account/create');

        $message = str_replace(
            ['{{login_url}}', '{{register_url}}'],
            [$loginUrl, $registerUrl],
            $message
        );

        return $message;
    }

    /**
     * @inheritDoc
     */
    public function isCustomerApproved(): bool
    {
        if (!$this->customerSession->isLoggedIn()) {
            return false;
        }

        try {
            // Buscar dados atualizados diretamente do banco, não da sessão
            $customerId = $this->customerSession->getCustomerId();
            $customer = $this->customerRepository->getById($customerId);
            $approvalStatusAttr = $customer->getCustomAttribute('b2b_approval_status');
            $approvalStatus = $approvalStatusAttr ? $approvalStatusAttr->getValue() : null;

            // Se não há status definido, considerar como aprovado (compatibilidade)
            if (empty($approvalStatus)) {
                return true;
            }

            return $approvalStatus === ApprovalStatus::STATUS_APPROVED;
        } catch (\Exception $e) {
            // Se houver erro ao buscar cliente, permitir por segurança
            return true;
        }
    }

    /**
     * Get customer approval status
     *
     * @return string|null
     */
    public function getCustomerApprovalStatus(): ?string
    {
        if (!$this->customerSession->isLoggedIn()) {
            return null;
        }

        try {
            $customerId = $this->customerSession->getCustomerId();
            $customer = $this->customerRepository->getById($customerId);
            $approvalStatusAttr = $customer->getCustomAttribute('b2b_approval_status');
            return $approvalStatusAttr ? $approvalStatusAttr->getValue() : null;
        } catch (\Exception $e) {
            $this->logger->error('[B2B PriceVisibility] getCustomerApprovalStatus error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * @inheritDoc
     */
    public function isApprovedPendingErp(): bool
    {
        if (!$this->customerSession->isLoggedIn()) {
            return false;
        }
        $status = $this->getCustomerApprovalStatus();
        return $status === ApprovalStatus::STATUS_APPROVED
            && $this->config->hidePriceForNoErp()
            && $this->getCustomerErpCode() === null;
    }

    /**
     * Get customer ERP code from attribute or entity_map fallback
     */
    private function getCustomerErpCode(): ?int
    {
        if ($this->erpCodeCache !== false) {
            return $this->erpCodeCache;
        }

        try {
            $customerId = (int) $this->customerSession->getCustomerId();
            $customer = $this->customerRepository->getById($customerId);

            // Primary: erp_code attribute
            $attr = $customer->getCustomAttribute('erp_code');
            $erpCode = ($attr && $attr->getValue()) ? $attr->getValue() : null;

            // Fallback: entity_map table
            if ($erpCode === null && $this->syncLogResource !== null) {
                $erpCode = $this->syncLogResource->getErpCodeByMagentoId('customer', $customerId);
            }

            $this->erpCodeCache = ($erpCode !== null && is_numeric($erpCode)) ? (int) $erpCode : null;
        } catch (\Exception $e) {
            $this->logger->error('[B2B PriceVisibility] getCustomerErpCode error: ' . $e->getMessage());
            $this->erpCodeCache = null;
        }

        return $this->erpCodeCache;
    }

    /**
     * Clear cached values (useful after login/logout)
     */
    public function clearCache(): void
    {
        $this->canViewPricesCache = null;
        $this->canAddToCartCache = null;
        $this->erpCodeCache = false;
    }
}
