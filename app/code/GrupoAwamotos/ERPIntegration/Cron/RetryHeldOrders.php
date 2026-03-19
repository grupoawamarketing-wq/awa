<?php
declare(strict_types=1);

namespace GrupoAwamotos\ERPIntegration\Cron;

use GrupoAwamotos\ERPIntegration\Api\CustomerSyncInterface;
use GrupoAwamotos\ERPIntegration\Helper\Data as Helper;
use GrupoAwamotos\ERPIntegration\Model\ResourceModel\SyncLog as SyncLogResource;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;
use Magento\Framework\App\State as AppState;
use Psr\Log\LoggerInterface;

/**
 * Cron: Retry resolution of ERP codes for orders with unlinked customers.
 *
 * Finds orders in 'pending' or 'processing' status where customer_erp_code is
 * null/empty/zero, then attempts to resolve erp_code via CPF/CNPJ lookup in
 * Sectra (read-only) and stamps it on the order + customer attribute.
 *
 * This ensures that when Sectra calls getPendingOrders() next time, these orders
 * will pass the erp_code check and appear in the "orders" list instead of "held".
 *
 * Schedule: every 30 minutes
 */
class RetryHeldOrders
{
    private const BATCH_SIZE = 50;

    private CustomerSyncInterface $customerSync;
    private OrderCollectionFactory $orderCollectionFactory;
    private CustomerRepositoryInterface $customerRepository;
    private SyncLogResource $syncLogResource;
    private Helper $helper;
    private LoggerInterface $logger;
    private AppState $appState;

    public function __construct(
        CustomerSyncInterface $customerSync,
        OrderCollectionFactory $orderCollectionFactory,
        CustomerRepositoryInterface $customerRepository,
        SyncLogResource $syncLogResource,
        Helper $helper,
        LoggerInterface $logger,
        AppState $appState
    ) {
        $this->customerSync = $customerSync;
        $this->orderCollectionFactory = $orderCollectionFactory;
        $this->customerRepository = $customerRepository;
        $this->syncLogResource = $syncLogResource;
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
            $this->appState->setAreaCode(\Magento\Framework\App\Area::AREA_ADMINHTML);
        } catch (\Exception $e) {
            // Area already set
        }

        $this->logger->info('[ERP Cron] Starting held orders retry (ERP code resolution)');

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
            $incrementId = $order->getIncrementId();
            $customerId = (int) $order->getCustomerId();

            if (!$customerId) {
                $unresolvable++;
                continue;
            }

            try {
                // Step 1: Check if customer now has erp_code (may have been linked by other cron)
                $erpCode = $this->getResolvedErpCode($customerId);

                // Step 2: If not, try lookup by CPF/CNPJ
                if (!$erpCode) {
                    $taxvat = $order->getCustomerTaxvat();
                    if (empty($taxvat)) {
                        try {
                            $customer = $this->customerRepository->getById($customerId);
                            $taxvat = $customer->getTaxvat();
                        } catch (\Exception $e) {
                            // Customer may have been deleted
                        }
                    }

                    if (!empty($taxvat)) {
                        $erpCustomer = $this->customerSync->getErpCustomerByTaxvat($taxvat);
                        if ($erpCustomer && !empty($erpCustomer['CODIGO'])) {
                            $erpCode = (int) $erpCustomer['CODIGO'];
                            $this->customerSync->linkMagentoToErp($customerId, $erpCode);
                        }
                    }
                }

                // Step 3: Stamp erp_code on order if resolved
                if ($erpCode) {
                    $order->setData('customer_erp_code', (string) $erpCode);
                    $order->addCommentToStatusHistory(
                        __('[ERP Auto] Código ERP %1 vinculado automaticamente por CPF/CNPJ', $erpCode)
                    );
                    $order->save();

                    $resolved++;
                    $this->logger->info('[ERP Cron] Resolved held order', [
                        'increment_id' => $incrementId,
                        'customer_id' => $customerId,
                        'erp_code' => $erpCode,
                    ]);
                } else {
                    $unresolvable++;
                }
            } catch (\Exception $e) {
                $errors++;
                $this->logger->warning('[ERP Cron] Failed to resolve held order ' . $incrementId, [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->logger->info('[ERP Cron] Held orders retry completed', [
            'total' => $total,
            'resolved' => $resolved,
            'unresolvable' => $unresolvable,
            'errors' => $errors,
        ]);

        if ($resolved > 0) {
            $this->syncLogResource->addLog(
                'order_held_retry',
                'sync',
                'success',
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
     * Get orders in active statuses that don't have an ERP code stamped.
     *
     * Usa getSelect()->where() em vez do addFieldToFilter() com arrays aninhados para
     * evitar o TypeError "Cannot access offset of type array in isset or empty" no PHP 8.4.
     *
     * @return \Magento\Sales\Model\Order[]
     */
    private function getOrdersWithoutErpCode(): array
    {
        $collection = $this->orderCollectionFactory->create();
        $collection->addFieldToFilter('status', ['in' => ['pending', 'processing', 'new']]);
        $collection->addFieldToFilter('customer_id', ['notnull' => true]);

        // OR: customer_erp_code IS NULL, empty string, or '0'
        // Usando where() direto para evitar TypeError do PHP 8.4 com addFieldToFilter nested arrays
        $collection->getSelect()->where(
            'main_table.customer_erp_code IS NULL'
            . ' OR main_table.customer_erp_code = \'\''
            . ' OR main_table.customer_erp_code = \'0\''
        );
        $collection->setPageSize(self::BATCH_SIZE);
        $collection->setOrder('created_at', 'ASC'); // Oldest first

        return $collection->getItems();
    }

    /**
     * Check if customer already has erp_code (attribute or entity_map).
     */
    private function getResolvedErpCode(int $customerId): ?int
    {
        // Check entity_map
        $erpCode = $this->syncLogResource->getErpCodeByMagentoId('customer', $customerId);
        if ($erpCode && is_numeric($erpCode) && (int) $erpCode > 0) {
            return (int) $erpCode;
        }

        // Check customer attribute
        try {
            $customer = $this->customerRepository->getById($customerId);
            $attr = $customer->getCustomAttribute('erp_code');
            if ($attr && $attr->getValue() && is_numeric($attr->getValue()) && (int) $attr->getValue() > 0) {
                return (int) $attr->getValue();
            }
        } catch (\Exception $e) {
            // Customer may not exist
        }

        return null;
    }
}
