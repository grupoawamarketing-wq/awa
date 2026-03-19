<?php
declare(strict_types=1);

namespace GrupoAwamotos\ERPIntegration\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use GrupoAwamotos\ERPIntegration\Helper\Data as Helper;
use GrupoAwamotos\ERPIntegration\Model\ResourceModel\SyncLog as SyncLogResource;
use GrupoAwamotos\ERPIntegration\Api\CustomerSyncInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Psr\Log\LoggerInterface;

/**
 * Observer for order placement - PULL mode
 *
 * In PULL mode, the ERP fetches orders via REST API (GET /V1/erp/orders/pending).
 * This observer logs the order and stamps the customer's ERP code directly on
 * the sales_order record, so ERP SECTRA can read it regardless of import method.
 *
 * Resolution order for erp_code:
 *  1. Customer attribute 'erp_code' (primary)
 *  2. entity_map table (fallback)
 *  3. ERP lookup by CPF/CNPJ in FN_FORNECEDORES (auto-link)
 */
class OrderPlaceAfter implements ObserverInterface
{
    private Helper $helper;
    private SyncLogResource $syncLogResource;
    private CustomerRepositoryInterface $customerRepository;
    private CustomerSyncInterface $customerSync;
    private LoggerInterface $logger;

    public function __construct(
        Helper $helper,
        SyncLogResource $syncLogResource,
        CustomerRepositoryInterface $customerRepository,
        CustomerSyncInterface $customerSync,
        LoggerInterface $logger
    ) {
        $this->helper = $helper;
        $this->syncLogResource = $syncLogResource;
        $this->customerRepository = $customerRepository;
        $this->customerSync = $customerSync;
        $this->logger = $logger;
    }

    public function execute(Observer $observer): void
    {
        if (!$this->helper->isOrderSyncEnabled()) {
            return;
        }

        try {
            $order = $observer->getEvent()->getOrder();
            if (!$order) {
                return;
            }

            // Stamp customer ERP code directly on the order
            $erpCode = $this->resolveCustomerErpCode($order);
            if ($erpCode) {
                $order->setData('customer_erp_code', (string) $erpCode);
            }

            // Log that order is available for ERP pull
            $this->syncLogResource->addLog(
                'order_pull',
                'export',
                'pending',
                sprintf(
                    'Pedido %s disponível para ERP via API Pull. Cliente: %s (ERP: %s)',
                    $order->getIncrementId(),
                    $order->getCustomerTaxvat() ?: $order->getCustomerEmail(),
                    $erpCode ?: 'N/A'
                ),
                null,
                (int) $order->getEntityId()
            );

            $order->addCommentToStatusHistory(
                __('Pedido disponível para sincronização com ERP via API. Código ERP cliente: %1', $erpCode ?: 'N/A')
            );

            $this->logger->info('[ERP] Order available for ERP pull', [
                'order_id' => $order->getEntityId(),
                'increment_id' => $order->getIncrementId(),
                'customer_erp_code' => $erpCode,
            ]);
        } catch (\Exception $e) {
            // Never fail the order placement due to ERP logging issues
            $this->logger->error('[ERP] Observer OrderPlaceAfter error: ' . $e->getMessage(), [
                'order_id' => isset($order) ? $order->getEntityId() : null,
            ]);
        }
    }

    /**
     * Resolve ERP code with 3-tier resolution:
     *  1. Customer attribute 'erp_code' (primary - instant)
     *  2. entity_map table (fallback - local DB)
     *  3. ERP lookup by CPF/CNPJ in FN_FORNECEDORES (auto-link, persists for future)
     */
    private function resolveCustomerErpCode($order): ?int
    {
        $customerId = $order->getCustomerId();
        if (!$customerId) {
            return null;
        }

        $customer = null;
        try {
            // Primary: erp_code customer attribute
            $customer = $this->customerRepository->getById((int) $customerId);
            $attr = $customer->getCustomAttribute('erp_code');
            if ($attr && $attr->getValue() && is_numeric($attr->getValue())) {
                return (int) $attr->getValue();
            }
        } catch (\Exception $e) {
            $this->logger->debug('[ERP] Could not load customer attribute: ' . $e->getMessage());
        }

        // Fallback: entity_map
        $erpCode = $this->syncLogResource->getErpCodeByMagentoId('customer', (int) $customerId);
        if ($erpCode && is_numeric($erpCode)) {
            return (int) $erpCode;
        }

        // Auto-link: lookup by CPF/CNPJ in ERP (read-only)
        return $this->autoLinkByTaxvat($order, (int) $customerId, $customer);
    }

    /**
     * Attempt to resolve and persist erp_code by looking up CPF/CNPJ in Sectra.
     * This is a read-only operation on the ERP side — only Magento data is updated.
     */
    private function autoLinkByTaxvat($order, int $customerId, ?\Magento\Customer\Api\Data\CustomerInterface $customer): ?int
    {
        $taxvat = $order->getCustomerTaxvat();
        if (empty($taxvat)) {
            // Try from customer entity if not on order
            if ($customer) {
                $taxvat = $customer->getTaxvat();
            }
        }

        if (empty($taxvat)) {
            return null;
        }

        try {
            $erpCustomer = $this->customerSync->getErpCustomerByTaxvat($taxvat);
            if (!$erpCustomer || empty($erpCustomer['CODIGO'])) {
                $this->logger->info('[ERP] Auto-link: CPF/CNPJ not found in ERP', [
                    'customer_id' => $customerId,
                    'taxvat' => substr($taxvat, 0, 6) . '***',
                ]);
                return null;
            }

            $erpCode = (int) $erpCustomer['CODIGO'];

            // Persist the link for future orders (saves both entity_map + customer attribute)
            $linked = $this->customerSync->linkMagentoToErp($customerId, $erpCode);
            if ($linked) {
                $this->logger->info('[ERP] Auto-linked customer by CPF/CNPJ', [
                    'customer_id' => $customerId,
                    'erp_code' => $erpCode,
                    'razao' => $erpCustomer['RAZAO'] ?? '',
                ]);
            }

            return $erpCode;
        } catch (\Exception $e) {
            $this->logger->warning('[ERP] Auto-link by taxvat failed: ' . $e->getMessage(), [
                'customer_id' => $customerId,
            ]);
            return null;
        }
    }
}
