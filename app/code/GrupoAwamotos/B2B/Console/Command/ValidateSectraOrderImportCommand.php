<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Console\Command;

use GrupoAwamotos\ERPIntegration\Api\B2bOrderPullCustomerDataInterface;
use GrupoAwamotos\ERPIntegration\Api\OrderPullInterface;
use GrupoAwamotos\ERPIntegration\Cron\SyncOpenCartBridge;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product\Attribute\Source\Status as ProductStatus;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\CatalogInventory\Helper\Stock as StockHelper;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\App\Area;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\App\State;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Valida disponibilidade de pedidos B2B para Sectra → Integração AWA → Importar Pedidos.
 */
class ValidateSectraOrderImportCommand extends Command
{
    private const OPTION_CUSTOMER_ID = 'customer-id';
    private const OPTION_CREATE_TEST_ORDER = 'create-test-order';
    private const OC_OFFSET = 200000;

    public function __construct(
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly B2bOrderPullCustomerDataInterface $orderPullCustomerData,
        private readonly ResourceConnection $resourceConnection,
        private readonly SyncOpenCartBridge $openCartBridge,
        private readonly OrderPullInterface $orderPull,
        private readonly CartManagementInterface $cartManagement,
        private readonly CartRepositoryInterface $cartRepository,
        private readonly ProductCollectionFactory $productCollectionFactory,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly StockHelper $stockHelper,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly State $appState,
        ?string $name = null
    ) {
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this->setName('b2b:sectra:validate-order-import')
            ->setDescription('Valida pedido B2B para Sectra Importar Pedidos (Integração AWA)')
            ->addOption(
                self::OPTION_CUSTOMER_ID,
                null,
                InputOption::VALUE_REQUIRED,
                'ID Magento do cliente aprovado (ex.: 8905)'
            )
            ->addOption(
                self::OPTION_CREATE_TEST_ORDER,
                null,
                InputOption::VALUE_NONE,
                'Cria pedido de teste real para validar oc_order e payload pull'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->initializeAreaCode();

        $customerId = (int) $input->getOption(self::OPTION_CUSTOMER_ID);
        if ($customerId <= 0) {
            $output->writeln('<error>Informe --customer-id=8905</error>');
            return Command::FAILURE;
        }

        $createTestOrder = (bool) $input->getOption(self::OPTION_CREATE_TEST_ORDER);
        $ocCustomerId = $customerId + self::OC_OFFSET;

        $output->writeln('<info>Validação Sectra — Importar Pedidos</info>');
        $output->writeln(sprintf('Cliente Magento #%d (OC/Sectra customer_id %d)', $customerId, $ocCustomerId));

        $checks = $this->validateBridgeReadiness($customerId, $ocCustomerId);
        foreach ($checks as $label => $ok) {
            $output->writeln(sprintf('  [%s] %s', $ok ? 'OK' : 'FALHA', $label));
        }

        if (in_array(false, $checks, true)) {
            $output->writeln('<comment>Executando bridge cron para sincronizar tabelas oc_*...</comment>');
            $this->openCartBridge->execute();
            $checks = $this->validateBridgeReadiness($customerId, $ocCustomerId);
            foreach ($checks as $label => $ok) {
                $output->writeln(sprintf('  [%s] %s (pós-bridge)', $ok ? 'OK' : 'FALHA', $label));
            }
        }

        if ((bool) $input->getOption(self::OPTION_CREATE_TEST_ORDER)) {
            try {
                $incrementId = $this->createTestOrder($customerId, $output);
                if ($incrementId === null) {
                    return Command::FAILURE;
                }
                $output->writeln(sprintf('<info>Pedido de teste criado: #%s</info>', $incrementId));
                $this->openCartBridge->execute();
            } catch (\Exception $e) {
                $output->writeln('<error>Falha ao criar pedido de teste: ' . $e->getMessage() . '</error>');
                return Command::FAILURE;
            }
        }

        $ocOrders = $this->fetchOcOrders($ocCustomerId);
        $output->writeln('');
        $output->writeln(sprintf('Pedidos visíveis em oc_order (Importar Pedidos): %d', count($ocOrders)));

        if ($ocOrders === []) {
            $output->writeln('<comment>Nenhum pedido em oc_order. Use --create-test-order para gerar um pedido de validação.</comment>');
            return in_array(false, $checks, true) ? Command::FAILURE : Command::SUCCESS;
        }

        foreach ($ocOrders as $row) {
            $output->writeln(sprintf(
                '  order_id=%s | total=%s | payment=%s | CNPJ=%s',
                $row['order_id'],
                $row['total'],
                $row['payment_method'],
                $this->extractCnpjFromCustomField((string) ($row['custom_field'] ?? ''))
            ));
        }

        $pullResult = $this->orderPull->getPendingOrders(50);
        $payloadOk = $this->validatePullPayload($pullResult, $customerId, $output);

        return ($checks === [] || !in_array(false, $checks, true)) && $payloadOk
            ? Command::SUCCESS
            : Command::FAILURE;
    }

    /**
     * @return array<string, bool>
     */
    private function validateBridgeReadiness(int $customerId, int $ocCustomerId): array
    {
        $conn = $this->resourceConnection->getConnection();

        $approval = (string) $conn->fetchOne(
            $conn->select()
                ->from(['cev' => 'customer_entity_varchar'], 'value')
                ->join(['ea' => 'eav_attribute'], 'ea.attribute_id = cev.attribute_id', [])
                ->where('cev.entity_id = ?', $customerId)
                ->where('ea.attribute_code = ?', 'b2b_approval_status')
        );

        $syncStatus = (string) $conn->fetchOne(
            $conn->select()
                ->from(['cev' => 'customer_entity_varchar'], 'value')
                ->join(['ea' => 'eav_attribute'], 'ea.attribute_id = cev.attribute_id', [])
                ->where('cev.entity_id = ?', $customerId)
                ->where('ea.attribute_code = ?', 'erp_customer_sync_status')
        );

        $map = $conn->fetchRow(
            'SELECT old_oc_customer_id FROM oc_customer_id_map WHERE magento_customer_id = ?',
            [$customerId]
        );
        $confirmed = $conn->fetchOne(
            'SELECT customer_id FROM oc_customer_b2b_confirmed WHERE customer_id = ?',
            [$ocCustomerId]
        );
        $ocCustomer = $conn->fetchRow(
            'SELECT customer_id, customer_group_id, custom_field FROM oc_customer WHERE customer_id = ?',
            [$ocCustomerId]
        );
        $cnpjInField = $ocCustomer
            ? $this->extractCnpjFromCustomField((string) ($ocCustomer['custom_field'] ?? ''))
            : '';

        return [
            'Cliente B2B approved' => $approval === 'approved',
            'erp_customer_sync_status definido' => $syncStatus !== '',
            'oc_customer_id_map' => is_array($map) && (int) ($map['old_oc_customer_id'] ?? 0) === $ocCustomerId,
            'oc_customer_b2b_confirmed' => $confirmed !== false,
            'oc_customer com CNPJ em custom_field' => strlen($cnpjInField) === 14,
            'Pronto para comprar (dados fiscais)' => $this->orderPullCustomerData->isReadyForOrderPull($customerId),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchOcOrders(int $ocCustomerId): array
    {
        $conn = $this->resourceConnection->getConnection();

        try {
            return $conn->fetchAll(
                'SELECT order_id, customer_id, email, custom_field, total, payment_method
                 FROM oc_order WHERE customer_id = ? ORDER BY order_id DESC LIMIT 5',
                [$ocCustomerId]
            );
        } catch (\Exception) {
            return [];
        }
    }

    /**
     * @param mixed $pullResult
     */
    private function validatePullPayload($pullResult, int $customerId, OutputInterface $output): bool
    {
        $data = is_array($pullResult) ? ($pullResult[0] ?? []) : [];
        $orders = is_array($data['orders'] ?? null) ? $data['orders'] : [];

        $output->writeln('');
        $output->writeln(sprintf('Pedidos na API pull (getPendingOrders): %d', count($orders)));

        $order = null;
        foreach ($orders as $candidate) {
            if (!is_array($candidate)) {
                continue;
            }
            $customer = is_array($candidate['customer'] ?? null) ? $candidate['customer'] : [];
            if ((int) ($customer['magento_customer_id'] ?? 0) === $customerId) {
                $order = $candidate;
                break;
            }
        }

        if ($order === null) {
            $held = is_array($data['held_orders'] ?? null) ? $data['held_orders'] : [];
            foreach ($held as $h) {
                if (is_array($h)) {
                    $output->writeln(sprintf(
                        '  RETIDO #%s — %s',
                        $h['increment_id'] ?? '?',
                        $h['reason'] ?? '?'
                    ));
                }
            }
            if ($held === []) {
                $output->writeln('<comment>Nenhum pedido pull encontrado para este cliente.</comment>');
            }
            return false;
        }

        $customer = is_array($order['customer'] ?? null) ? $order['customer'] : [];
        $required = ['magento_customer_id', 'cnpj', 'razao_social', 'email', 'telephone', 'custom_field'];
        $missing = [];
        foreach ($required as $field) {
            if (empty($customer[$field])) {
                $missing[] = $field;
            }
        }

        if ($missing !== []) {
            $output->writeln('<error>Payload pull incompleto: ' . implode(', ', $missing) . '</error>');
            return false;
        }

        $output->writeln(sprintf(
            '  Payload OK — increment=%s | CNPJ=%s | mode=%s | itens=%d | total=R$ %.2f',
            $order['increment_id'] ?? '?',
            $customer['cnpj'] ?? '?',
            $customer['integration_mode'] ?? '?',
            (int) ($order['items_count'] ?? 0),
            (float) ($order['grand_total'] ?? 0)
        ));

        return true;
    }

    private function createTestOrder(int $customerId, OutputInterface $output): ?string
    {
        $customer = $this->customerRepository->getById($customerId);
        if (!$this->orderPullCustomerData->isApprovedB2bCustomer($customerId)) {
            throw new LocalizedException(__('Cliente #%1 não está aprovado B2B.', $customerId));
        }

        $product = $this->findSalableProduct();
        if ($product === null) {
            throw new LocalizedException(__('Nenhum produto simples salável encontrado para pedido de teste.'));
        }

        $this->deactivateActiveQuotes($customerId);

        $cartId = $this->cartManagement->createEmptyCartForCustomer($customerId);
        $quote = $this->cartRepository->getActive($cartId);
        $quote->addProduct($this->productRepository->get($product->getSku()), 1);

        $address = $this->buildQuoteAddress($customerId, $customer->getEmail());
        $quote->getBillingAddress()->addData($address);
        $quote->getShippingAddress()->addData($address);

        $shippingAddress = $quote->getShippingAddress();
        $shippingAddress->setCollectShippingRates(true);
        $shippingAddress->collectShippingRates();

        $shippingMethod = null;
        foreach ($shippingAddress->getAllShippingRates() as $rate) {
            $shippingMethod = $rate->getCode();
            if ($rate->getCode() === 'freeshipping_freeshipping') {
                break;
            }
        }
        $shippingAddress->setShippingMethod($shippingMethod ?: 'tablerate_bestway');

        $quote->setPaymentMethod('acombinar');
        $quote->getPayment()->importData(['method' => 'acombinar']);
        $quote->collectTotals();
        $this->cartRepository->save($quote);

        $orderId = $this->cartManagement->placeOrder($cartId);
        $order = $this->orderRepository->get($orderId);

        $output->writeln(sprintf(
            '  Produto: %s | Total: R$ %.2f | Payment: %s',
            $product->getSku(),
            (float) $order->getGrandTotal(),
            $order->getPayment()?->getMethod() ?? '—'
        ));

        return $order->getIncrementId();
    }

    /**
     * @return array<string, mixed>
     */
    private function buildQuoteAddress(int $customerId, string $email): array
    {
        $addresses = $this->customerRepository->getById($customerId)->getAddresses();
        if ($addresses !== []) {
            foreach ($addresses as $addr) {
                if ($addr->isDefaultShipping() || $addr->isDefaultBilling()) {
                    return [
                        'customer_address_id' => $addr->getId(),
                        'firstname' => $addr->getFirstname(),
                        'lastname' => $addr->getLastname(),
                        'street' => $addr->getStreet(),
                        'city' => $addr->getCity(),
                        'region_id' => $addr->getRegionId(),
                        'region' => $addr->getRegion()?->getRegion(),
                        'postcode' => $addr->getPostcode(),
                        'country_id' => $addr->getCountryId(),
                        'telephone' => $addr->getTelephone(),
                        'company' => $addr->getCompany(),
                        'email' => $email,
                    ];
                }
            }
        }

        return [
            'firstname' => 'Teste',
            'lastname' => 'Sectra',
            'street' => ['Rua Teste Sectra, 100'],
            'city' => 'Araraquara',
            'region_id' => 485,
            'region' => 'São Paulo',
            'postcode' => '14801-000',
            'country_id' => 'BR',
            'telephone' => '16999999999',
            'email' => $email,
        ];
    }

    private function findSalableProduct(): ?\Magento\Catalog\Model\Product
    {
        $collection = $this->productCollectionFactory->create();
        $collection->addAttributeToSelect(['sku', 'name', 'price'])
            ->addAttributeToFilter('status', ProductStatus::STATUS_ENABLED)
            ->addAttributeToFilter('type_id', 'simple')
            ->addAttributeToFilter('price', ['gt' => 0])
            ->setPageSize(20);
        $this->stockHelper->addInStockFilterToCollection($collection);

        foreach ($collection as $product) {
            if ($product->isSalable()) {
                return $product;
            }
        }

        return null;
    }

    private function deactivateActiveQuotes(int $customerId): void
    {
        $conn = $this->resourceConnection->getConnection();
        $conn->update(
            'quote',
            ['is_active' => 0],
            ['customer_id = ?' => $customerId, 'is_active = ?' => 1]
        );
    }

    private function extractCnpjFromCustomField(string $json): string
    {
        $data = json_decode($json, true);

        return is_array($data) ? preg_replace('/\D/', '', (string) ($data['6'] ?? '')) : '';
    }

    private function initializeAreaCode(): void
    {
        try {
            $this->appState->setAreaCode(Area::AREA_ADMINHTML);
        } catch (\Exception) {
            // already set
        }
    }
}
