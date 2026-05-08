<?php

declare(strict_types=1);

namespace GrupoAwamotos\TawkIntegration\Block;

use GrupoAwamotos\B2B\Helper\Config as B2BConfig;
use GrupoAwamotos\B2B\Model\CreditService;
use GrupoAwamotos\B2B\Model\CustomerApproval;
use GrupoAwamotos\ERPIntegration\Model\OrderHistory;
use GrupoAwamotos\ERPIntegration\Model\Rfm\Calculator as RfmCalculator;
use GrupoAwamotos\TawkIntegration\Helper\Config;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;
use Psr\Log\LoggerInterface;

class Widget extends Template
{
    private Config $config;
    private B2BConfig $b2bConfig;
    private CustomerSession $customerSession;
    private CheckoutSession $checkoutSession;
    private CustomerRepositoryInterface $customerRepository;
    private CustomerApproval $customerApproval;
    private CreditService $creditService;
    private RfmCalculator $rfmCalculator;
    private OrderHistory $orderHistory;
    private OrderCollectionFactory $orderCollectionFactory;
    private LoggerInterface $logger;

    public function __construct(
        Context $context,
        Config $config,
        B2BConfig $b2bConfig,
        CustomerSession $customerSession,
        CheckoutSession $checkoutSession,
        CustomerRepositoryInterface $customerRepository,
        CustomerApproval $customerApproval,
        CreditService $creditService,
        RfmCalculator $rfmCalculator,
        OrderHistory $orderHistory,
        OrderCollectionFactory $orderCollectionFactory,
        LoggerInterface $logger,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->config = $config;
        $this->b2bConfig = $b2bConfig;
        $this->customerSession = $customerSession;
        $this->checkoutSession = $checkoutSession;
        $this->customerRepository = $customerRepository;
        $this->customerApproval = $customerApproval;
        $this->creditService = $creditService;
        $this->rfmCalculator = $rfmCalculator;
        $this->orderHistory = $orderHistory;
        $this->orderCollectionFactory = $orderCollectionFactory;
        $this->logger = $logger;
    }

    /**
     * @return string
     */
    protected function _toHtml(): string
    {
        if (!$this->config->isEnabled()) {
            return '';
        }
        $propertyId = $this->config->getPropertyId();
        $widgetId = $this->config->getWidgetId();
        if ($propertyId === '' || $widgetId === '') {
            return '';
        }
        return parent::_toHtml();
    }

    public function getPropertyId(): string
    {
        return $this->config->getPropertyId();
    }

    public function getWidgetId(): string
    {
        return $this->config->getWidgetId();
    }

    public function isCustomerLoggedIn(): bool
    {
        return $this->customerSession->isLoggedIn();
    }

    /**
     * @return array{name: string, email: string, hash: string}|null
     */
    public function getVisitorData(): ?array
    {
        if (!$this->isCustomerLoggedIn()) {
            return null;
        }
        try {
            $customer = $this->customerSession->getCustomer();
            $email = (string) $customer->getEmail();
            $name = trim($customer->getFirstname() . ' ' . $customer->getLastname());
            $hash = $this->config->generateVisitorHash($email);
            return ['name' => $name, 'email' => $email, 'hash' => $hash];
        } catch (\Exception $e) {
            $this->logger->error('[TawkIntegration] Visitor data error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * @return array<string, string>
     */
    public function getEnrichmentAttributes(): array
    {
        if (!$this->isCustomerLoggedIn()) {
            return $this->getAnonymousAttributes();
        }
        $attributes = [];
        try {
            $customerId = (int) $this->customerSession->getCustomerId();
            $customer = $this->customerRepository->getById($customerId);
            if ($this->config->shouldSendB2bData()) {
                $attributes = array_merge($attributes, $this->getB2bAttributes($customerId, $customer));
            }
            if ($this->config->shouldSendRfmData()) {
                $attributes = array_merge($attributes, $this->getRfmAttributes($customerId));
            }
            if ($this->config->shouldSendOrderData()) {
                $attributes = array_merge($attributes, $this->getOrderAttributes($customerId));
            }
            if ($this->config->shouldSendCartData()) {
                $attributes = array_merge($attributes, $this->getCartAttributes());
            }
        } catch (\Exception $e) {
            $this->logger->error('[TawkIntegration] Enrichment error: ' . $e->getMessage());
        }
        return $attributes;
    }

    /**
     * @return string[]
     */
    public function getAutoTags(): array
    {
        if (!$this->isCustomerLoggedIn()) {
            return ['visitante'];
        }
        $tags = ['cliente'];
        try {
            $customerId = (int) $this->customerSession->getCustomerId();
            $customer = $this->customerRepository->getById($customerId);
            $groupId = (int) $customer->getGroupId();
            if ($groupId === $this->b2bConfig->getWholesaleGroupId()) {
                $tags[] = 'atacado';
            } elseif ($groupId === $this->b2bConfig->getVipGroupId()) {
                $tags[] = 'vip';
            }
            $revendedorGroupId = (int) $this->_scopeConfig->getValue(
                'grupoawamotos_b2b/customer_groups/revendedor_group'
            );
            if ($revendedorGroupId > 0 && $groupId === $revendedorGroupId) {
                $tags[] = 'revendedor';
            }
            $approvalStatus = $this->customerApproval->getApprovalStatus($customerId);
            if ($approvalStatus === 'pending') {
                $tags[] = 'pendente-aprovacao';
            }
            if ($this->config->shouldSendRfmData()) {
                $rfm = $this->getRfmSegment($customerId);
                if ($rfm !== null) {
                    $tags[] = 'rfm-' . $rfm;
                }
            }
        } catch (\Exception $e) {
            $this->logger->error('[TawkIntegration] Tags error: ' . $e->getMessage());
        }
        return array_slice($tags, 0, 10);
    }

    /**
     * @return array<string, string>
     */
    private function getAnonymousAttributes(): array
    {
        $attributes = [];
        if ($this->config->shouldSendCartData()) {
            try {
                $quote = $this->checkoutSession->getQuote();
                if ($quote->getItemsCount() > 0) {
                    $attributes['carrinho-total'] = number_format((float) $quote->getGrandTotal(), 2, ',', '.');
                    $attributes['carrinho-itens'] = (string) $quote->getItemsCount();
                }
            } catch (\Exception $e) {
                // Non-critical
            }
        }
        return $attributes;
    }

    /**
     * @param int $customerId
     * @param \Magento\Customer\Api\Data\CustomerInterface $customer
     * @return array<string, string>
     */
    private function getB2bAttributes(int $customerId, \Magento\Customer\Api\Data\CustomerInterface $customer): array
    {
        $attrs = [];
        $cnpjAttr = $customer->getCustomAttribute('cnpj');
        if ($cnpjAttr !== null && $cnpjAttr->getValue() !== '') {
            $attrs['cnpj'] = (string) $cnpjAttr->getValue();
        }
        $personTypeAttr = $customer->getCustomAttribute('person_type');
        if ($personTypeAttr !== null) {
            $attrs['tipo-pessoa'] = $personTypeAttr->getValue() === 'pj' ? 'Pessoa Jurídica' : 'Pessoa Física';
        }
        $groupId = (int) $customer->getGroupId();
        $attrs['grupo'] = $this->resolveGroupLabel($groupId);
        $approvalStatus = $this->customerApproval->getApprovalStatus($customerId);
        if ($approvalStatus !== null) {
            $statusLabels = [
                'pending' => 'Pendente',
                'approved' => 'Aprovado',
                'rejected' => 'Rejeitado',
                'suspended' => 'Suspenso',
            ];
            $attrs['situacao'] = $statusLabels[$approvalStatus] ?? $approvalStatus;
        }
        if ($this->creditService->isEnabled()) {
            try {
                $credit = $this->creditService->getCreditLimit($customerId);
                $attrs['limite-credito'] = 'R$ ' . number_format($credit->getCreditLimit(), 2, ',', '.');
                $attrs['credito-disponivel'] = 'R$ ' . number_format($credit->getAvailableCredit(), 2, ',', '.');
            } catch (\Exception $e) {
                // No credit record
            }
        }
        return $attrs;
    }

    /**
     * @param int $customerId
     * @return array<string, string>
     */
    private function getRfmAttributes(int $customerId): array
    {
        $segment = $this->getRfmSegment($customerId);
        if ($segment === null) {
            return [];
        }
        $segmentLabels = [
            'champions' => 'Campeão',
            'loyal' => 'Leal',
            'potential' => 'Potencial',
            'new_customers' => 'Novo',
            'promising' => 'Promissor',
            'needs_attention' => 'Atenção',
            'about_to_sleep' => 'Adormecendo',
            'at_risk' => 'Em Risco',
            'cant_lose' => 'Não Perder',
            'hibernating' => 'Hibernando',
            'lost' => 'Perdido',
        ];
        return ['perfil-rfm' => $segmentLabels[$segment] ?? $segment];
    }

    /**
     * @param int $customerId
     * @return array<string, string>
     */
    private function getOrderAttributes(int $customerId): array
    {
        try {
            $collection = $this->orderCollectionFactory->create();
            $collection->addFieldToFilter('customer_id', $customerId)
                ->setOrder('created_at', 'DESC')
                ->setPageSize(1);
            $lastOrder = $collection->getFirstItem();
            if (!$lastOrder->getId()) {
                return ['total-pedidos' => '0'];
            }
            return [
                'ultimo-pedido' => '#' . $lastOrder->getIncrementId(),
                'ultimo-pedido-valor' => 'R$ ' . number_format((float) $lastOrder->getGrandTotal(), 2, ',', '.'),
                'ultimo-pedido-data' => date('d/m/Y', strtotime((string) $lastOrder->getCreatedAt())),
                'total-pedidos' => (string) $this->getOrderCount($customerId),
            ];
        } catch (\Exception $e) {
            $this->logger->error('[TawkIntegration] Order attributes error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * @return array<string, string>
     */
    private function getCartAttributes(): array
    {
        try {
            $quote = $this->checkoutSession->getQuote();
            if ($quote->getItemsCount() > 0) {
                return [
                    'carrinho-total' => 'R$ ' . number_format((float) $quote->getGrandTotal(), 2, ',', '.'),
                    'carrinho-itens' => (string) $quote->getItemsCount(),
                ];
            }
        } catch (\Exception $e) {
            // Non-critical
        }
        return [];
    }

    private function resolveGroupLabel(int $groupId): string
    {
        if ($groupId === $this->b2bConfig->getWholesaleGroupId()) {
            return 'Atacado';
        }
        if ($groupId === $this->b2bConfig->getVipGroupId()) {
            return 'VIP';
        }
        $revendedorGroupId = (int) $this->_scopeConfig->getValue(
            'grupoawamotos_b2b/customer_groups/revendedor_group'
        );
        if ($revendedorGroupId > 0 && $groupId === $revendedorGroupId) {
            return 'Revendedor';
        }
        return 'Varejo';
    }

    private function getRfmSegment(int $customerId): ?string
    {
        try {
            $erpCode = $this->orderHistory->getErpClientCodeByCustomerId($customerId);
            if ($erpCode === null) {
                return null;
            }
            $rfm = $this->rfmCalculator->getCustomerRfm($erpCode);
            return $rfm['segment'] ?? null;
        } catch (\Exception $e) {
            $this->logger->error('[TawkIntegration] RFM error: ' . $e->getMessage());
            return null;
        }
    }

    private function getOrderCount(int $customerId): int
    {
        $collection = $this->orderCollectionFactory->create();
        $collection->addFieldToFilter('customer_id', $customerId);
        return (int) $collection->getSize();
    }
}
