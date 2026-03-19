<?php
declare(strict_types=1);

namespace GrupoAwamotos\ERPIntegration\Cron;

use GrupoAwamotos\ERPIntegration\Api\CustomerSyncInterface;
use GrupoAwamotos\ERPIntegration\Helper\Data as Helper;
use GrupoAwamotos\ERPIntegration\Model\CnpjResolver;
use GrupoAwamotos\ERPIntegration\Model\ResourceModel\SyncLog as SyncLogResource;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Framework\App\Area;
use Magento\Framework\App\State as AppState;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;
use Psr\Log\LoggerInterface;

/**
 * Cron: tenta resolver pedidos retidos por ausência de vínculo ERP.
 */
class RetryHeldOrders
{
    private const BATCH_SIZE = 50;

    private CustomerSyncInterface $customerSync;
    private OrderCollectionFactory $orderCollectionFactory;
    private CustomerRepositoryInterface $customerRepository;
    private SyncLogResource $syncLogResource;
    private CnpjResolver $cnpjResolver;
    private Helper $helper;
    private LoggerInterface $logger;
    private AppState $appState;

    public function __construct(
        CustomerSyncInterface $customerSync,
        OrderCollectionFactory $orderCollectionFactory,
        CustomerRepositoryInterface $customerRepository,
        SyncLogResource $syncLogResource,
        CnpjResolver $cnpjResolver,
        Helper $helper,
        LoggerInterface $logger,
        AppState $appState
    ) {
        $this->customerSync = $customerSync;
        $this->orderCollectionFactory = $orderCollectionFactory;
        $this->customerRepository = $customerRepository;
        $this->syncLogResource = $syncLogResource;
        $this->cnpjResolver = $cnpjResolver;
        $this->helper = $helper;
        $this->logger = $logger;
        $this->appState = $appState;
    }

    public function execute(): void
    {
        if (!$this->helper->isEnabled() || !$this->helper->isOrderSyncEnabled()) {
            return;
        }

        try {
            $this->appState->setAreaCode(Area::AREA_ADMINHTML);
        } catch (\Exception $exception) {
            unset($exception);
        }

        $orders = $this->getOrdersWithoutErpCode();
        $total = count($orders);

        if ($total === 0) {
            $this->logger->info('[ERP Cron] No held orders to retry');
            return;
        }

        $resolved = 0;
        $unresolvable = 0;
        $errors = 0;

        foreach ($orders as $order) {
            try {
                if ($this->resolveOrder($order)) {
                    $resolved++;
                    continue;
                }

                $unresolvable++;
            } catch (\Exception $exception) {
                $errors++;
                $this->logger->warning('[ERP Cron] Failed to resolve held order', [
                    'increment_id' => $order->getIncrementId(),
                    'order_id' => (int) $order->getEntityId(),
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        $this->logger->info('[ERP Cron] Held orders retry completed', [
            'total' => $total,
            'resolved' => $resolved,
            'unresolvable' => $unresolvable,
            'errors' => $errors,
        ]);

        if ($resolved > 0 || $errors > 0) {
            $this->syncLogResource->addLog(
                'order_held_retry',
                'sync',
                $errors > 0 ? 'partial' : 'success',
                sprintf(
                    'Retry pedidos retidos: %d resolvidos de %d total (%d sem resolução, %d erros)',
                    $resolved,
                    $total,
                    $unresolvable,
                    $errors
                )
            );
        }
    }

    /**
     * @return Order[]
     */
    private function getOrdersWithoutErpCode(): array
    {
        $collection = $this->orderCollectionFactory->create();
        $collection->addFieldToFilter('status', ['in' => ['pending', 'processing', 'new']]);
        $collection->addFieldToFilter('customer_id', ['notnull' => true]);
        $collection->getSelect()->where(
            'main_table.customer_erp_code IS NULL'
            . ' OR main_table.customer_erp_code = \'\''
            . ' OR main_table.customer_erp_code = \'0\''
        );
        $collection->setPageSize(self::BATCH_SIZE);
        $collection->setOrder('created_at', 'ASC');

        return $collection->getItems();
    }

    private function resolveOrder(Order $order): bool
    {
        $customerId = (int) $order->getCustomerId();
        if ($customerId <= 0) {
            return false;
        }

        $erpCode = $this->getResolvedErpCode($customerId);
        if (!$erpCode) {
            $customer = $this->getCustomer($customerId);
            $cnpj = $this->extractCnpj($order, $customer);

            if ($cnpj === '') {
                return false;
            }

            $erpCustomer = $this->customerSync->getErpCustomerByCnpj($cnpj);
            if (!$erpCustomer || empty($erpCustomer['CODIGO'])) {
                return false;
            }

            $erpCode = (int) $erpCustomer['CODIGO'];
            if ($erpCode <= 0) {
                return false;
            }

            $this->customerSync->linkMagentoToErp($customerId, $erpCode);
        }

        $order->setData('customer_erp_code', (string) $erpCode);
        $order->addCommentToStatusHistory(
            __('[ERP Auto] Código ERP %1 vinculado automaticamente por CNPJ', $erpCode)
        );
        $order->save();

        $this->logger->info('[ERP Cron] Resolved held order', [
            'increment_id' => $order->getIncrementId(),
            'customer_id' => $customerId,
            'erp_code' => $erpCode,
        ]);

        return true;
    }

    private function getResolvedErpCode(int $customerId): ?int
    {
        $erpCode = $this->syncLogResource->getErpCodeByMagentoId('customer', $customerId);
        if ($erpCode !== null && is_numeric($erpCode) && (int) $erpCode > 0) {
            return (int) $erpCode;
        }

        $customer = $this->getCustomer($customerId);
        if ($customer === null) {
            return null;
        }

        $attribute = $customer->getCustomAttribute('erp_code');
        if ($attribute && is_numeric((string) $attribute->getValue()) && (int) $attribute->getValue() > 0) {
            return (int) $attribute->getValue();
        }

        return null;
    }

    private function getCustomer(int $customerId): ?CustomerInterface
    {
        try {
            return $this->customerRepository->getById($customerId);
        } catch (\Exception $exception) {
            return null;
        }
    }

    private function extractCnpj(Order $order, ?CustomerInterface $customer): string
    {
        $cnpj = $this->cnpjResolver->normalize((string) ($order->getData('b2b_cnpj') ?? ''));
        if ($cnpj !== '') {
            return $cnpj;
        }

        $cnpj = $this->cnpjResolver->normalize((string) ($order->getCustomerTaxvat() ?? ''));
        if ($cnpj !== '') {
            return $cnpj;
        }

        if ($customer === null) {
            return '';
        }

        $attribute = $customer->getCustomAttribute('b2b_cnpj');
        $cnpj = $this->cnpjResolver->normalize((string) ($attribute ? $attribute->getValue() : ''));
        if ($cnpj !== '') {
            return $cnpj;
        }

        return $this->cnpjResolver->normalize((string) ($customer->getTaxvat() ?? ''));
    }
}
