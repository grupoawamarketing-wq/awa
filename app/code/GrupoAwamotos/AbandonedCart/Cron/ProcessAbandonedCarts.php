<?php

declare(strict_types=1);

namespace GrupoAwamotos\AbandonedCart\Cron;

use GrupoAwamotos\AbandonedCart\Api\AbandonedCartRepositoryInterface;
use GrupoAwamotos\AbandonedCart\Api\Data\AbandonedCartInterface;
use GrupoAwamotos\AbandonedCart\Api\Data\AbandonedCartInterfaceFactory;
use GrupoAwamotos\AbandonedCart\Helper\Data as Helper;
use Magento\Framework\App\ResourceConnection;
use Magento\Quote\Model\ResourceModel\Quote\CollectionFactory as QuoteCollectionFactory;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;
use Psr\Log\LoggerInterface;

class ProcessAbandonedCarts
{
    private Helper $helper;
    private QuoteCollectionFactory $quoteCollectionFactory;
    private OrderCollectionFactory $orderCollectionFactory;
    private AbandonedCartRepositoryInterface $abandonedCartRepository;
    private AbandonedCartInterfaceFactory $abandonedCartFactory;
    private ResourceConnection $resource;
    private LoggerInterface $logger;

    public function __construct(
        Helper $helper,
        QuoteCollectionFactory $quoteCollectionFactory,
        OrderCollectionFactory $orderCollectionFactory,
        AbandonedCartRepositoryInterface $abandonedCartRepository,
        AbandonedCartInterfaceFactory $abandonedCartFactory,
        ResourceConnection $resource,
        LoggerInterface $logger
    ) {
        $this->helper = $helper;
        $this->quoteCollectionFactory = $quoteCollectionFactory;
        $this->orderCollectionFactory = $orderCollectionFactory;
        $this->abandonedCartRepository = $abandonedCartRepository;
        $this->abandonedCartFactory = $abandonedCartFactory;
        $this->resource = $resource;
        $this->logger = $logger;
    }

    public function execute(): void
    {
        $this->logger->info('[AbandonedCart] Starting cart processing cron');

        // Processar novos carrinhos abandonados
        $this->processNewAbandonedCarts();

        // Verificar carrinhos convertidos em pedidos
        $this->checkRecoveredCarts();

        $this->logger->info('[AbandonedCart] Cart processing cron completed');
    }

    private function processNewAbandonedCarts(): void
    {
        // Buscar carrinhos abandonados (não convertidos em pedido, com items, com email)
        $minDelay = $this->helper->getEmailDelay(1);
        $cutoffTime = date('Y-m-d H:i:s', strtotime("-{$minDelay} hours"));
        $maxAge = date('Y-m-d H:i:s', strtotime('-30 days'));

        $collection = $this->quoteCollectionFactory->create();
        $collection->addFieldToFilter('is_active', 1)
            ->addFieldToFilter('items_count', ['gt' => 0])
            ->addFieldToFilter('customer_email', ['notnull' => true])
            ->addFieldToFilter('customer_email', ['neq' => ''])
            ->addFieldToFilter('updated_at', ['lteq' => $cutoffTime])
            ->addFieldToFilter('updated_at', ['gteq' => $maxAge])
            ->setPageSize(500);

        // Load collection once so we can batch-check existing records
        $collection->load();

        if (!$collection->count()) {
            return;
        }

        // Pre-load all existing abandoned cart quote_ids in a SINGLE query
        // This eliminates the N+1 pattern (one getByQuoteId() per quote)
        $quoteIds = array_map('intval', $collection->getColumnValues('entity_id'));
        $existingIds = $this->loadExistingQuoteIds($quoteIds);

        $processed = 0;
        $skipped = 0;

        foreach ($collection as $quote) {
            try {
                $quoteId = (int) $quote->getId();

                // O(1) set lookup — replaces individual getByQuoteId() DB call
                if (isset($existingIds[$quoteId])) {
                    $skipped++;
                    continue;
                }

                // Verificar valor mínimo
                $storeId = (int) $quote->getStoreId();
                $minValue = $this->helper->getMinCartValue($storeId);
                if ($quote->getGrandTotal() < $minValue) {
                    $skipped++;
                    continue;
                }

                // Verificar se exclui visitantes
                if ($this->helper->excludeGuest($storeId) && !$quote->getCustomerId()) {
                    $skipped++;
                    continue;
                }

                // Criar registro
                $abandonedCart = $this->abandonedCartFactory->create();
                $abandonedCart->setQuoteId($quoteId)
                    ->setCustomerId($quote->getCustomerId() ? (int) $quote->getCustomerId() : null)
                    ->setCustomerEmail($quote->getCustomerEmail())
                    ->setCustomerName($this->getCustomerName($quote))
                    ->setStoreId($storeId)
                    ->setCartValue((float) $quote->getGrandTotal())
                    ->setItemsCount((int) $quote->getItemsCount())
                    ->setAbandonedAt($quote->getUpdatedAt())
                    ->setStatus(AbandonedCartInterface::STATUS_PENDING);

                $this->abandonedCartRepository->save($abandonedCart);
                $processed++;
            } catch (\Exception $e) {
                $this->logger->error(sprintf(
                    '[AbandonedCart] Error processing quote %d: %s',
                    $quote->getId(),
                    $e->getMessage()
                ));
            }
        }

        $this->logger->info(sprintf(
            '[AbandonedCart] Processed %d new abandoned carts, skipped %d',
            $processed,
            $skipped
        ));
    }

    private function checkRecoveredCarts(): void
    {
        // Buscar pedidos recentes — limitado a 500 e somente coluna necessária
        $recentOrders = $this->orderCollectionFactory->create();
        $recentOrders
            ->addFieldToSelect(['quote_id'])
            ->addFieldToFilter('created_at', ['gteq' => date('Y-m-d H:i:s', strtotime('-24 hours'))])
            ->setPageSize(500);

        $quoteIds = [];
        foreach ($recentOrders as $order) {
            $quoteId = (int) $order->getQuoteId();
            if ($quoteId > 0) {
                $quoteIds[] = $quoteId;
            }
        }

        if (empty($quoteIds)) {
            return;
        }

        // Single batch UPDATE instead of N individual markAsRecovered() calls
        $recovered = $this->batchMarkRecovered($quoteIds);

        if ($recovered > 0) {
            $this->logger->info(sprintf('[AbandonedCart] Marked %d carts as recovered', $recovered));
        }
    }

    /**
     * Pre-load existing abandoned cart quote IDs as a hash-set for O(1) lookup.
     *
     * @param int[] $quoteIds
     * @return array<int, true>
     */
    private function loadExistingQuoteIds(array $quoteIds): array
    {
        if (empty($quoteIds)) {
            return [];
        }

        $connection = $this->resource->getConnection();
        $table = $this->resource->getTableName('grupoawamotos_abandoned_cart');

        $select = $connection->select()
            ->from($table, ['quote_id'])
            ->where('quote_id IN (?)', $quoteIds);

        $rows = $connection->fetchCol($select);

        return array_fill_keys(array_map('intval', $rows), true);
    }

    /**
     * Batch-mark abandoned carts as recovered using a single UPDATE statement.
     * Replaces N individual markAsRecovered() calls (each with SELECT + UPDATE).
     *
     * @param int[] $quoteIds
     * @return int Number of rows updated
     */
    private function batchMarkRecovered(array $quoteIds): int
    {
        $connection = $this->resource->getConnection();
        $table = $this->resource->getTableName('grupoawamotos_abandoned_cart');

        return (int) $connection->update(
            $table,
            [
                'recovered'    => 1,
                'recovered_at' => date('Y-m-d H:i:s'),
                'status'       => AbandonedCartInterface::STATUS_RECOVERED,
                'updated_at'   => date('Y-m-d H:i:s'),
            ],
            [
                'quote_id IN (?)' => $quoteIds,
                'recovered = ?'   => 0,
            ]
        );
    }

    private function getCustomerName($quote): string
    {
        $firstname = $quote->getCustomerFirstname();
        $lastname = $quote->getCustomerLastname();

        if ($firstname && $lastname) {
            return trim("{$firstname} {$lastname}");
        }

        if ($quote->getBillingAddress()) {
            $firstname = $quote->getBillingAddress()->getFirstname();
            $lastname = $quote->getBillingAddress()->getLastname();
            if ($firstname && $lastname) {
                return trim("{$firstname} {$lastname}");
            }
        }

        return '';
    }
}
