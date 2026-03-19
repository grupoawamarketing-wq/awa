<?php
/**
 * Observer para copiar metadados B2B do Quote para Order
 * P0-1: Purchase Order Number
 * P2-4.2: Order Notes
 */
declare(strict_types=1);

namespace GrupoAwamotos\B2B\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Psr\Log\LoggerInterface;

class CopyPoNumberToOrder implements ObserverInterface
{
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Event: sales_model_service_quote_submit_before
     * Copia b2b_po_number e b2b_order_notes do quote para o order antes de salvar
     */
    public function execute(Observer $observer): void
    {
        try {
            /** @var \Magento\Quote\Model\Quote $quote */
            $quote = $observer->getEvent()->getQuote();

            /** @var \Magento\Sales\Model\Order $order */
            $order = $observer->getEvent()->getOrder();

            $poNumber = $quote->getData('b2b_po_number');
            $orderNotes = $quote->getData('b2b_order_notes');
            $copiedFields = [];

            if (!empty($poNumber)) {
                $order->setData('b2b_po_number', $poNumber);
                $copiedFields[] = 'b2b_po_number';
            }

            if (!empty($orderNotes)) {
                $order->setData('b2b_order_notes', (string) $orderNotes);
                $copiedFields[] = 'b2b_order_notes';
            }

            if (!empty($copiedFields)) {
                $this->logger->info('[B2B] Metadados copiados do quote para order', [
                    'order_increment_id' => $order->getIncrementId(),
                    'copied_fields' => $copiedFields,
                ]);
            }
        } catch (\Exception $e) {
            $this->logger->error('[B2B] Erro ao copiar metadados B2B para order: ' . $e->getMessage());
        }
    }
}
