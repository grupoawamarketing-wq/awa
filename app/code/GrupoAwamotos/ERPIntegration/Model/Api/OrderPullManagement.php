<?php
declare(strict_types=1);

namespace GrupoAwamotos\ERPIntegration\Model\Api;

use GrupoAwamotos\ERPIntegration\Api\OrderPullInterface;
use GrupoAwamotos\ERPIntegration\Api\ConnectionInterface;
use GrupoAwamotos\ERPIntegration\Helper\Data as Helper;
use GrupoAwamotos\ERPIntegration\Model\CustomerSync;
use GrupoAwamotos\ERPIntegration\Model\B2BClientRegistration;
use GrupoAwamotos\ERPIntegration\Model\CnpjResolver;
use GrupoAwamotos\ERPIntegration\Model\ResourceModel\SyncLog as SyncLogResource;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SortOrderBuilder;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Directory\Model\RegionFactory;
use Psr\Log\LoggerInterface;

class OrderPullManagement implements OrderPullInterface
{
    private ConnectionInterface $connection;
    private Helper $helper;
    private CustomerSync $customerSync;
    private B2BClientRegistration $b2bRegistration;
    private OrderRepositoryInterface $orderRepository;
    private SearchCriteriaBuilder $searchCriteriaBuilder;
    private SortOrderBuilder $sortOrderBuilder;
    private SyncLogResource $syncLogResource;
    private CustomerRepositoryInterface $customerRepository;
    private RegionFactory $regionFactory;
    private CnpjResolver $cnpjResolver;
    private LoggerInterface $logger;

    public function __construct(
        ConnectionInterface $connection,
        Helper $helper,
        CustomerSync $customerSync,
        B2BClientRegistration $b2bRegistration,
        OrderRepositoryInterface $orderRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        SortOrderBuilder $sortOrderBuilder,
        SyncLogResource $syncLogResource,
        CustomerRepositoryInterface $customerRepository,
        RegionFactory $regionFactory,
        CnpjResolver $cnpjResolver,
        LoggerInterface $logger
    ) {
        $this->connection = $connection;
        $this->helper = $helper;
        $this->customerSync = $customerSync;
        $this->b2bRegistration = $b2bRegistration;
        $this->orderRepository = $orderRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->sortOrderBuilder = $sortOrderBuilder;
        $this->syncLogResource = $syncLogResource;
        $this->customerRepository = $customerRepository;
        $this->regionFactory = $regionFactory;
        $this->cnpjResolver = $cnpjResolver;
        $this->logger = $logger;
    }

    public function getPendingOrders(int $limit = 50, ?string $fromDate = null): array
    {
        $this->logger->info('[ERP API] getPendingOrders called', ['limit' => $limit, 'fromDate' => $fromDate]);

        $allOrders = $this->fetchUnsyncedOrders($limit * 2, $fromDate);

        $orders = [];
        $held = [];
        $hasWriteAccess = $this->b2bRegistration->hasWriteAccess();
        $requiresSectraRegistration = $this->helper->requiresSectraClientRegistration();

        foreach ($allOrders as $order) {
            try {
                $erpClientCode = $this->resolveErpClientCode($order);
                $customerName = trim(($order->getCustomerFirstname() ?? '') . ' ' . ($order->getCustomerLastname() ?? ''));

                // Orders without any ERP client code are truly unresolvable — hold them
                if ($erpClientCode <= 0) {
                    $held[] = [
                        'increment_id' => $order->getIncrementId(),
                        'erp_code' => 0,
                        'customer' => $customerName,
                        'reason' => 'Cliente sem erp_code no Magento',
                    ];
                    continue;
                }

                $registrationState = $this->resolveClientRegistrationState(
                    $erpClientCode,
                    $order->getIncrementId(),
                    $hasWriteAccess,
                    $requiresSectraRegistration
                );

                if (!$registrationState['registered']) {
                    $held[] = [
                        'increment_id' => $order->getIncrementId(),
                        'erp_code' => $erpClientCode,
                        'customer' => $customerName,
                        'reason' => $registrationState['reason'],
                    ];
                    continue;
                }

                $orders[] = $this->buildOrderPayload($order, true);
                if (count($orders) >= $limit) {
                    break;
                }
            } catch (\Exception $e) {
                $this->logger->warning('[ERP API] Failed to build payload for order ' . $order->getIncrementId(), [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->syncLogResource->addLog(
            'order_pull',
            'export',
            'success',
            sprintf('Listed %d pending orders via API (%d held)', count($orders), count($held))
        );

        // Wrap in indexed array so Magento's ServiceOutputProcessor preserves keys
        return [[
            'orders' => $orders,
            'total_count' => count($orders),
            'held_count' => count($held),
            'held_orders' => $held,
            'import_mode' => $this->helper->getOrderImportMode(),
            'requires_sectra_registration' => $requiresSectraRegistration,
            'timestamp' => date('c'),
        ]];
    }

    public function getOrderDetails(string $incrementId): array
    {
        $this->logger->info('[ERP API] getOrderDetails called', ['increment_id' => $incrementId]);

        $order = $this->findOrderByIncrementId($incrementId);

        // Wrap in indexed array so Magento's ServiceOutputProcessor preserves keys
        return [$this->buildOrderPayload($order, $this->isClientRegisteredForPayload($order))];
    }

    public function acknowledgeOrder(
        string $incrementId,
        string $erpOrderId,
        ?string $message = null
    ): array {
        $this->logger->info('[ERP API] acknowledgeOrder called', [
            'increment_id' => $incrementId,
            'erp_order_id' => $erpOrderId,
        ]);

        $order = $this->findOrderByIncrementId($incrementId);

        // Store ERP mapping
        $this->syncLogResource->setEntityMap(
            'order',
            $erpOrderId,
            (int) $order->getEntityId()
        );

        // Update order status to 'processing' if still pending/new
        $currentState = $order->getState();
        if (in_array($currentState, [
            \Magento\Sales\Model\Order::STATE_NEW,
            \Magento\Sales\Model\Order::STATE_PENDING_PAYMENT
        ], true)) {
            $order->setState(\Magento\Sales\Model\Order::STATE_PROCESSING);
            $order->setStatus('processing');
        }

        // Add order comment
        $comment = sprintf(
            '[ERP] Pedido recebido pelo ERP via API. ID ERP: %s%s',
            $erpOrderId,
            $message ? ' - ' . $message : ''
        );
        $order->addCommentToStatusHistory($comment);
        $this->orderRepository->save($order);

        // Log
        $this->syncLogResource->addLog(
            'order_pull',
            'export',
            'success',
            sprintf('Order %s acknowledged by ERP. ERP ID: %s', $incrementId, $erpOrderId),
            $erpOrderId,
            (int) $order->getEntityId()
        );

        $this->logger->info('[ERP API] Order acknowledged', [
            'increment_id' => $incrementId,
            'erp_order_id' => $erpOrderId,
        ]);

        // Wrap in indexed array so Magento's ServiceOutputProcessor preserves keys
        return [[
            'success' => true,
            'message' => sprintf('Order %s acknowledged. ERP ID: %s', $incrementId, $erpOrderId),
            'increment_id' => $incrementId,
            'erp_order_id' => $erpOrderId,
            'acknowledged_at' => date('c'),
        ]];
    }

    public function getCanceledOrders(?string $fromDate = null): array
    {
        $this->logger->info('[ERP API] getCanceledOrders called', ['fromDate' => $fromDate]);

        $syncedOrderIds = $this->getSyncedOrderIds();

        $this->searchCriteriaBuilder->addFilter('state', 'canceled');

        if ($fromDate) {
            $this->searchCriteriaBuilder->addFilter('created_at', $fromDate, 'gteq');
        }

        $sortOrder = $this->sortOrderBuilder
            ->setField('created_at')
            ->setAscendingDirection()
            ->create();
        $this->searchCriteriaBuilder->setSortOrders([$sortOrder]);
        $this->searchCriteriaBuilder->setPageSize(100);

        $searchCriteria = $this->searchCriteriaBuilder->create();
        $orderList = $this->orderRepository->getList($searchCriteria);

        $orders = [];
        foreach ($orderList->getItems() as $order) {
            if (in_array((int) $order->getEntityId(), $syncedOrderIds, true)) {
                continue;
            }

            $orders[] = [
                'increment_id' => $order->getIncrementId(),
                'entity_id' => (int) $order->getEntityId(),
                'state' => $order->getState(),
                'status' => $order->getStatus(),
                'grand_total' => (float) $order->getGrandTotal(),
                'customer_email' => $order->getCustomerEmail(),
                'created_at' => $order->getCreatedAt(),
                'canceled_at' => $order->getUpdatedAt(),
            ];
        }

        return [[
            'orders' => $orders,
            'total_count' => count($orders),
            'timestamp' => date('c'),
        ]];
    }

    public function getHeldOrders(int $limit = 50): array
    {
        $this->logger->info('[ERP API] getHeldOrders called', ['limit' => $limit]);

        $allOrders = $this->fetchUnsyncedOrders($limit * 3, null);
        $requiresSectraRegistration = $this->helper->requiresSectraClientRegistration();

        $held = [];
        foreach ($allOrders as $order) {
            if (count($held) >= $limit) {
                break;
            }

            try {
                $erpClientCode = $this->resolveErpClientCode($order);

                if ($erpClientCode <= 0) {
                    $held[] = $this->buildHeldOrderPayload($order, 0, 'Cliente sem erp_code no Magento');
                    continue;
                }

                if ($requiresSectraRegistration && !$this->b2bRegistration->isClientRegistered($erpClientCode)) {
                    $held[] = $this->buildHeldOrderPayload(
                        $order,
                        $erpClientCode,
                        'Cliente nao registrado no GR_INTEGRACAOVALIDADOR do Sectra'
                    );
                }
            } catch (\Exception $e) {
                // skip
            }
        }

        $hasWriteAccess = $this->b2bRegistration->hasWriteAccess();

        return [[
            'orders' => $held,
            'total_held' => count($held),
            'write_connection_available' => $hasWriteAccess,
            'import_mode' => $this->helper->getOrderImportMode(),
            'resolution' => $requiresSectraRegistration
                ? ($hasWriteAccess
                    ? 'Auto-registro habilitado. Clientes serao registrados automaticamente na proxima chamada de getPendingOrders.'
                    : 'Configurar credenciais de escrita em Stores > Config > GrupoAwamotos > ERP Integration > Conexao de Escrita, ou executar SQL no Sectra.')
                : 'Modo OpenCart bridge ativo. Pedidos so ficam retidos aqui se faltarem vinculos ERP basicos, como customer_erp_code.',
            'timestamp' => date('c'),
        ]];
    }

    // ==================== Private Methods ====================

    /**
     * Fetch unsynced orders from Magento
     *
     * @return OrderInterface[]
     */
    private function fetchUnsyncedOrders(int $limit, ?string $fromDate): array
    {
        $syncedOrderIds = $this->getSyncedOrderIds();

        $this->searchCriteriaBuilder->addFilter(
            'state',
            ['new', 'pending_payment', 'processing'],
            'in'
        );

        if ($fromDate) {
            $this->searchCriteriaBuilder->addFilter('created_at', $fromDate, 'gteq');
        }

        $sortOrder = $this->sortOrderBuilder
            ->setField('created_at')
            ->setAscendingDirection()
            ->create();

        $this->searchCriteriaBuilder->setSortOrders([$sortOrder]);
        $this->searchCriteriaBuilder->setPageSize($limit);

        $searchCriteria = $this->searchCriteriaBuilder->create();
        $orderList = $this->orderRepository->getList($searchCriteria);

        $orders = [];
        foreach ($orderList->getItems() as $order) {
            if (!in_array((int) $order->getEntityId(), $syncedOrderIds, true)) {
                $orders[] = $order;
            }
        }

        return $orders;
    }

    private function buildHeldOrderPayload(OrderInterface $order, int $erpClientCode, string $reason): array
    {
        return [
            'increment_id' => $order->getIncrementId(),
            'magento_id' => (int) $order->getEntityId(),
            'erp_code' => $erpClientCode,
            'customer' => trim(($order->getCustomerFirstname() ?? '') . ' ' . ($order->getCustomerLastname() ?? '')),
            'customer_email' => $order->getCustomerEmail() ?? '',
            'grand_total' => (float) $order->getGrandTotal(),
            'created_at' => $order->getCreatedAt(),
            'status' => $order->getStatus(),
            'reason' => $reason,
        ];
    }

    private function findOrderByIncrementId(string $incrementId): OrderInterface
    {
        $this->searchCriteriaBuilder->addFilter('increment_id', $incrementId);
        $searchCriteria = $this->searchCriteriaBuilder->create();
        $orders = $this->orderRepository->getList($searchCriteria)->getItems();

        if (empty($orders)) {
            throw new NoSuchEntityException(
                __('Order with increment_id "%1" not found.', $incrementId)
            );
        }

        return reset($orders);
    }

    private function getSyncedOrderIds(): array
    {
        try {
            $connection = $this->syncLogResource->getConnection();
            $select = $connection->select()
                ->from('grupoawamotos_erp_entity_map', ['magento_entity_id'])
                ->where('entity_type = ?', 'order');

            return array_map('intval', $connection->fetchCol($select));
        } catch (\Exception $e) {
            $this->logger->warning('[ERP API] Failed to get synced order IDs: ' . $e->getMessage());
            return [];
        }
    }

    private function buildOrderPayload(OrderInterface $order, bool $clientRegistered): array
    {
        // Resolve ERP client code
        $erpClientCode = $this->resolveErpClientCode($order);
        $cnpj = $this->extractOrderCnpj($order);

        // Fetch customer commercial data from ERP
        $erpCustomerData = $this->getErpCustomerOrderData($erpClientCode);

        // Build items
        $items = $this->buildItemsPayload($order);

        // Shipping address
        $shipping = $order->getShippingAddress();

        return [
            'increment_id' => $order->getIncrementId(),
            'magento_id' => (int) $order->getEntityId(),
            'created_at' => $order->getCreatedAt(),
            'status' => $order->getStatus(),
            'state' => $order->getState(),
            'store_id' => (int) $order->getStoreId(),

            // Financial (VE_PEDIDO.VLR*)
            'subtotal' => (float) $order->getSubtotal(),
            'discount' => abs((float) $order->getDiscountAmount()),
            'shipping_amount' => (float) $order->getShippingAmount(),
            'grand_total' => (float) $order->getGrandTotal(),

            // Customer
            'customer' => [
                'erp_code' => $erpClientCode,
                'cnpj' => $cnpj,
                'taxvat' => $cnpj,
                'name' => trim(($order->getCustomerFirstname() ?? '') . ' ' . ($order->getCustomerLastname() ?? '')),
                'email' => $order->getCustomerEmail() ?? '',
                'registered_in_b2b' => $clientRegistered,
            ],

            // ERP Commercial Conditions (from FN_FORNECEDORES)
            'erp_conditions' => [
                'vendedor' => (int) ($erpCustomerData['VENDPREF'] ?? 0),
                'cond_pagto' => (int) ($erpCustomerData['CONDPAGTO'] ?? 0),
                'fator_preco' => (int) ($erpCustomerData['FATORPRECO'] ?? 0),
                'contato' => (string) ($erpCustomerData['CONTATO_NOME'] ?? ''),
                'transportadora' => (int) ($erpCustomerData['TRANSPPREF'] ?? 0),
                'tp_fator' => (string) ($erpCustomerData['TPFATOR'] ?? ''),
                'perc_fator' => (float) ($erpCustomerData['PERCFATOR'] ?? 0),
            ],

            // ERP order fields (VE_PEDIDO direct mapping)
            'erp_status' => 'W',              // STATUS = 'W' (Ped. Web)
            'filial' => $this->helper->getStockFilial(),
            'pedidoweb' => $order->getIncrementId(),
            'pedidocli' => $order->getIncrementId(),
            'username' => 'MAGENTO',

            // Shipping Address (VE_PEDIDO.ENT*)
            'shipping_address' => [
                'street' => $shipping ? implode(', ', $shipping->getStreet()) : '',
                'neighborhood' => $this->extractBairro($shipping),
                'city' => $shipping ? ($shipping->getCity() ?? '') : '',
                'region' => $shipping ? $this->resolveRegionCode($shipping) : '',
                'postcode' => $shipping ? ($shipping->getPostcode() ?? '') : '',
            ],

            // Payment
            'payment_method' => $order->getPayment() ? $order->getPayment()->getMethod() : '',

            // Items (VE_PEDIDOITENS)
            'items' => $items,
            'items_count' => count($items),
        ];
    }

    private function buildItemsPayload(OrderInterface $order): array
    {
        $items = [];

        foreach ($order->getItems() as $item) {
            if ($item->getParentItemId()) {
                continue;
            }
            if ((float) $item->getQtyOrdered() <= 0) {
                continue;
            }

            $unidade = $this->getErpProductUnit($item->getSku());

            $items[] = [
                'sku' => $item->getSku(),
                'name' => $item->getName(),
                'qty' => (float) $item->getQtyOrdered(),
                'unit' => $unidade,
                'unit_price' => (float) $item->getPrice(),
                'row_total' => (float) $item->getRowTotal(),
                'discount' => abs((float) $item->getDiscountAmount()),
                'row_total_incl_discount' => (float) $item->getRowTotal() - abs((float) $item->getDiscountAmount()),
            ];
        }

        return $items;
    }

    /**
     * @return array{registered: bool, reason: string}
     */
    private function resolveClientRegistrationState(
        int $erpClientCode,
        string $incrementId,
        bool $hasWriteAccess,
        bool $requiresSectraRegistration
    ): array
    {
        if (!$requiresSectraRegistration) {
            return [
                'registered' => true,
                'reason' => '',
            ];
        }

        if ($this->b2bRegistration->isClientRegistered($erpClientCode)) {
            return [
                'registered' => true,
                'reason' => '',
            ];
        }

        if (!$hasWriteAccess) {
            $this->logger->info('[ERP API] Order held - client not registered and write access unavailable', [
                'increment_id' => $incrementId,
                'erp_code' => $erpClientCode,
            ]);

            return [
                'registered' => false,
                'reason' => 'Cliente nao registrado no GR_INTEGRACAOVALIDADOR do Sectra e sem conexao de escrita para auto-registro.',
            ];
        }

        if ($this->b2bRegistration->registerClient($erpClientCode)) {
            return [
                'registered' => true,
                'reason' => '',
            ];
        }

        $this->logger->warning('[ERP API] Order held - auto-registration failed', [
            'increment_id' => $incrementId,
            'erp_code' => $erpClientCode,
        ]);

        return [
            'registered' => false,
            'reason' => 'Falha ao registrar cliente no GR_INTEGRACAOVALIDADOR. Verificar logs.',
        ];
    }

    private function isClientRegisteredForPayload(OrderInterface $order): bool
    {
        $erpClientCode = $this->resolveErpClientCode($order);
        if ($erpClientCode <= 0) {
            return false;
        }

        if (!$this->helper->requiresSectraClientRegistration()) {
            return true;
        }

        try {
            return $this->b2bRegistration->isClientRegistered($erpClientCode);
        } catch (\Exception $e) {
            $this->logger->warning('[ERP API] Failed to verify Sectra registration for payload: ' . $e->getMessage(), [
                'increment_id' => $order->getIncrementId(),
                'erp_code' => $erpClientCode,
            ]);

            return false;
        }
    }

    private function resolveErpClientCode(OrderInterface $order): int
    {
        // 0. Check if already stamped on the order (set by OrderPlaceAfter observer)
        $stamped = $order->getData('customer_erp_code');
        if ($stamped && is_numeric($stamped)) {
            return (int) $stamped;
        }

        // 1. Lookup by CNPJ in ERP directly
        $cnpj = $this->extractOrderCnpj($order);
        if ($cnpj) {
            $erpCustomer = $this->customerSync->getErpCustomerByCnpj($cnpj);
            if ($erpCustomer) {
                return (int) $erpCustomer['CODIGO'];
            }
        }

        $customerId = $order->getCustomerId();
        if (!$customerId) {
            $this->logger->warning('[ERP API] Order has no customer_id (guest order)', [
                'increment_id' => $order->getIncrementId(),
            ]);
            return 0;
        }

        // 2. Entity map lookup
        $erpCode = $this->syncLogResource->getErpCodeByMagentoId('customer', (int) $customerId);
        if ($erpCode && is_numeric($erpCode)) {
            return (int) $erpCode;
        }

        // 3. Customer erp_code attribute (definitive fallback)
        try {
            $customer = $this->customerRepository->getById((int) $customerId);
            $attr = $customer->getCustomAttribute('erp_code');
            if ($attr && $attr->getValue() && is_numeric($attr->getValue())) {
                $this->logger->info('[ERP API] Resolved ERP code from customer attribute', [
                    'customer_id' => $customerId,
                    'erp_code' => $attr->getValue(),
                ]);
                return (int) $attr->getValue();
            }
        } catch (\Exception $e) {
            $this->logger->warning('[ERP API] Failed to load customer for ERP code: ' . $e->getMessage());
        }

        $this->logger->error('[ERP API] Could not resolve ERP client code for order', [
            'increment_id' => $order->getIncrementId(),
            'customer_id' => $customerId,
            'cnpj' => $cnpj,
        ]);

        return 0;
    }

    private function extractOrderCnpj(OrderInterface $order): string
    {
        $customerId = (int) $order->getCustomerId();

        $cnpj = $this->cnpjResolver->normalize((string) ($order->getData('b2b_cnpj') ?? ''));
        if ($cnpj !== '') {
            return $cnpj;
        }

        $cnpj = $this->cnpjResolver->normalize((string) ($order->getCustomerTaxvat() ?? ''));
        if ($cnpj !== '') {
            return $cnpj;
        }

        if ($customerId <= 0) {
            return '';
        }

        try {
            $customer = $this->customerRepository->getById($customerId);
            $attr = $customer->getCustomAttribute('b2b_cnpj');
            $cnpj = $this->cnpjResolver->normalize((string) ($attr ? $attr->getValue() : ''));
            if ($cnpj !== '') {
                return $cnpj;
            }

            return $this->cnpjResolver->normalize((string) ($customer->getTaxvat() ?? ''));
        } catch (\Exception $e) {
            $this->logger->warning('[ERP API] Failed to load customer CNPJ: ' . $e->getMessage(), [
                'increment_id' => $order->getIncrementId(),
                'customer_id' => $customerId,
            ]);

            return '';
        }
    }

    private function getErpCustomerOrderData(int $erpClientCode): array
    {
        if ($erpClientCode <= 0) {
            return [];
        }

        try {
            $sql = "SELECT f.CONDPAGTO, f.FATORPRECO, f.TRANSPPREF, f.VENDPREF,
                           f.TPFATOR, f.PERCFATOR,
                           c.NOME AS CONTATO_NOME
                    FROM FN_FORNECEDORES f
                    LEFT JOIN FN_CONTATO c ON c.FORNECEDOR = f.CODIGO AND c.PRINCIPAL = 'S'
                    WHERE f.CODIGO = :code AND f.CKCLIENTE = 'S'";

            $result = $this->connection->fetchOne($sql, [':code' => $erpClientCode]);
            return $result ?: [];
        } catch (\Exception $e) {
            $this->logger->warning('[ERP API] Failed to fetch customer data: ' . $e->getMessage());
            return [];
        }
    }

    private function getErpProductUnit(string $sku): string
    {
        try {
            $sql = "SELECT UNDVENDA FROM MT_MATERIAL WHERE CODIGO = :sku";
            $result = $this->connection->fetchOne($sql, [':sku' => $sku]);
            return $result ? (string) ($result['UNDVENDA'] ?? 'PC') : 'PC';
        } catch (\Exception $e) {
            return 'PC';
        }
    }

    private function resolveRegionCode($shipping): string
    {
        $countryId = $shipping->getCountryId() ?? 'BR';

        // Prefer region name lookup (more reliable than region_id for BR addresses)
        $regionName = $shipping->getRegion();
        if ($regionName) {
            $region = $this->regionFactory->create()->loadByName($regionName, $countryId);
            if ($region->getId()) {
                return $region->getCode();
            }
        }

        // Fallback: use region_id
        $regionId = $shipping->getRegionId();
        if ($regionId) {
            $region = $this->regionFactory->create()->load($regionId);
            if ($region->getId()) {
                return $region->getCode();
            }
        }

        return $shipping->getRegionCode() ?? '';
    }

    private function extractBairro($shipping): string
    {
        if (!$shipping) {
            return '';
        }

        $street = $shipping->getStreet();
        if (!is_array($street)) {
            return '';
        }
        return $street[2] ?? $street[1] ?? '';
    }

}
