<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Controller\Order;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Response\Http\FileFactory;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;
use Psr\Log\LoggerInterface;

/**
 * Export customer orders to CSV
 * P1-2: CSV Export for B2B customers
 */
class ExportCsv implements HttpGetActionInterface
{
    private const CSV_FILENAME_PREFIX = 'meus_pedidos_';

    private CustomerSession $customerSession;
    private OrderCollectionFactory $orderCollectionFactory;
    private FileFactory $fileFactory;
    private ResultFactory $resultFactory;
    private LoggerInterface $logger;

    public function __construct(
        CustomerSession $customerSession,
        OrderCollectionFactory $orderCollectionFactory,
        FileFactory $fileFactory,
        ResultFactory $resultFactory,
        LoggerInterface $logger
    ) {
        $this->customerSession = $customerSession;
        $this->orderCollectionFactory = $orderCollectionFactory;
        $this->fileFactory = $fileFactory;
        $this->resultFactory = $resultFactory;
        $this->logger = $logger;
    }

    /**
     * Execute CSV export
     *
     * @return ResultInterface|ResponseInterface
     */
    public function execute()
    {
        if (!$this->customerSession->isLoggedIn()) {
            /** @var Redirect $redirect */
            $redirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
            $redirect->setPath('b2b/account/login');
            return $redirect;
        }

        try {
            $customerId = (int) $this->customerSession->getCustomerId();
            $csvContent = $this->generateCsv($customerId);
            $filename = self::CSV_FILENAME_PREFIX . date('Y-m-d_His') . '.csv';

            return $this->fileFactory->create(
                $filename,
                [
                    'type' => 'string',
                    'value' => $csvContent,
                    'rm' => true,
                ],
                \Magento\Framework\App\Filesystem\DirectoryList::VAR_DIR,
                'text/csv; charset=UTF-8'
            );
        } catch (LocalizedException $e) {
            $this->logger->error('B2B CSV Export error: ' . $e->getMessage());
            /** @var Redirect $redirect */
            $redirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
            $redirect->setPath('sales/order/history');
            return $redirect;
        }
    }

    /**
     * Generate CSV content from customer orders
     *
     * @param int $customerId
     * @return string
     */
    private function generateCsv(int $customerId): string
    {
        $collection = $this->orderCollectionFactory->create();
        $collection->addFieldToFilter('customer_id', ['eq' => $customerId])
            ->addAttributeToSort('created_at', 'DESC')
            ->setPageSize(500); // Limit to last 500 orders

        $output = fopen('php://temp', 'r+');

        // BOM for Excel UTF-8 compatibility
        fwrite($output, "\xEF\xBB\xBF");

        // Header row
        fputcsv($output, [
            'Pedido',
            'Data',
            'Status',
            'Subtotal',
            'Frete',
            'Desconto',
            'Total',
            'Forma Pagamento',
            'Transportadora',
            'PO Number',
            'Observações do Pedido',
            'Itens',
        ], ';');

        foreach ($collection as $order) {
            $items = [];
            foreach ($order->getAllVisibleItems() as $item) {
                $items[] = sprintf(
                    '%s x%d',
                    $item->getSku(),
                    (int) $item->getQtyOrdered()
                );
            }

            fputcsv($output, [
                $order->getIncrementId(),
                date('d/m/Y H:i', strtotime($order->getCreatedAt())),
                $order->getStatusLabel(),
                $this->formatPrice((float) $order->getSubtotal()),
                $this->formatPrice((float) $order->getShippingAmount()),
                $this->formatPrice((float) $order->getDiscountAmount()),
                $this->formatPrice((float) $order->getGrandTotal()),
                $order->getPayment()?->getMethodInstance()?->getTitle() ?? '',
                $order->getShippingDescription() ?? '',
                $order->getData('b2b_po_number') ?? '',
                $this->normalizeCsvText((string) ($order->getData('b2b_order_notes') ?? '')),
                implode(' | ', $items),
            ], ';');
        }

        rewind($output);
        $content = stream_get_contents($output);
        fclose($output);

        return $content !== false ? $content : '';
    }

    /**
     * Format price for CSV (Brazilian format)
     *
     * @param float $price
     * @return string
     */
    private function formatPrice(float $price): string
    {
        return number_format($price, 2, ',', '.');
    }

    /**
     * Normalize multiline/untrusted text to keep CSV row stable.
     *
     * @param string $value
     * @return string
     */
    private function normalizeCsvText(string $value): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $value));
    }
}
