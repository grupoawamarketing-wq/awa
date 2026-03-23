<?php
declare(strict_types=1);

namespace GrupoAwamotos\SocialProof\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\App\ResourceConnection;
use Psr\Log\LoggerInterface;

/**
 * Batch observer para páginas de categoria/listagem.
 * Carrega dados de bestseller para todos os produtos da collection em uma única query.
 */
class AddCollectionSocialProofObserver implements ObserverInterface
{
    private const BESTSELLER_DAYS = 30;
    private const BESTSELLER_MIN_QTY = 5;

    private ResourceConnection $resourceConnection;
    private LoggerInterface $logger;

    public function __construct(
        ResourceConnection $resourceConnection,
        LoggerInterface $logger
    ) {
        $this->resourceConnection = $resourceConnection;
        $this->logger = $logger;
    }

    public function execute(Observer $observer): void
    {
        $collection = $observer->getEvent()->getCollection();
        if (!$collection || $collection->count() === 0) {
            return;
        }

        $productIds = $collection->getColumnValues('entity_id');
        if (empty($productIds)) {
            return;
        }

        // Cap to avoid unbounded IN clause on very large collections
        if (count($productIds) > 500) {
            $productIds = array_slice($productIds, 0, 500);
        }

        try {
            $connection = $this->resourceConnection->getConnection();
            $today = date('Y-m-d');
            $periodStart = date('Y-m-d', strtotime('-' . self::BESTSELLER_DAYS . ' days'));

            $bestsellersTable = $this->resourceConnection->getTableName('sales_bestsellers_aggregated_daily');
            $select = $connection->select()
                ->from($bestsellersTable, ['product_id', 'total_qty' => 'SUM(qty_ordered)'])
                ->where('product_id IN (?)', $productIds)
                ->where('period >= ?', $periodStart)
                ->where('period <= ?', $today)
                ->group('product_id')
                ->having('SUM(qty_ordered) >= ?', self::BESTSELLER_MIN_QTY);

            $bestsellerIds = array_column($connection->fetchAll($select), 'product_id');

            foreach ($collection as $product) {
                $product->setData('is_best_seller', in_array($product->getId(), $bestsellerIds));
            }
        } catch (\Exception $e) {
            $this->logger->warning('[SocialProof] Batch: ' . $e->getMessage());
        }
    }
}
