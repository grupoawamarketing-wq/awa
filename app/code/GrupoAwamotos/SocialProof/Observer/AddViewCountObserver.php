<?php

declare(strict_types=1);

namespace GrupoAwamotos\SocialProof\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\App\CacheInterface;
use Psr\Log\LoggerInterface;

/**
 * Adiciona dados REAIS de prova social ao produto.
 *
 * Fontes:
 *   - report_viewed_product_index (visualizações do dia — sem depender de cron)
 *   - sales_order_item + sales_order (mais vendidos — sem depender de cron de agregação)
 *
 * Conformidade com CDC Art. 37 — somente dados reais, sem simulação.
 */
class AddViewCountObserver implements ObserverInterface
{
    private const CACHE_PREFIX = 'socialproof_';
    private const CACHE_LIFETIME = 900; // 15 minutos
    private const BESTSELLER_DAYS = 30;
    private const BESTSELLER_MIN_QTY = 2; // threshold realista para volume B2B

    private ResourceConnection $resourceConnection;
    private CacheInterface $cache;
    private LoggerInterface $logger;

    public function __construct(
        ResourceConnection $resourceConnection,
        CacheInterface $cache,
        LoggerInterface $logger
    ) {
        $this->resourceConnection = $resourceConnection;
        $this->cache = $cache;
        $this->logger = $logger;
    }

    public function execute(Observer $observer): void
    {
        $product = $observer->getEvent()->getProduct();
        if (!$product || !$product->getId()) {
            return;
        }

        $productId = (int) $product->getId();
        $cacheKey = self::CACHE_PREFIX . $productId;

        // Cache hit
        $cached = $this->cache->load($cacheKey);
        if ($cached !== false) {
            $data = json_decode($cached, true);
            $product->setData('views_today', $data['views_today'] ?? 0);
            $product->setData('is_best_seller', $data['is_best_seller'] ?? false);
            return;
        }

        try {
            $connection = $this->resourceConnection->getConnection();
            $today = date('Y-m-d');

            // Visualizações hoje — lê de report_viewed_product_index (tempo real)
            // report_viewed_product_aggregated_daily depende de cron de agregação que pode não rodar diariamente
            $viewsTable = $this->resourceConnection->getTableName('report_viewed_product_index');
            $viewsToday = (int) $connection->fetchOne(
                $connection->select()
                    ->from($viewsTable, ['cnt' => 'COUNT(*)'])
                    ->where('product_id = ?', $productId)
                    ->where('DATE(added_at) = ?', $today)
            );

            // Mais vendido (últimos 30 dias) — lê de sales_order_item diretamente
            // sales_bestsellers_aggregated_daily depende de cron de agregação
            $orderItemTable = $this->resourceConnection->getTableName('sales_order_item');
            $orderTable = $this->resourceConnection->getTableName('sales_order');
            $periodStart = date('Y-m-d', strtotime('-' . self::BESTSELLER_DAYS . ' days'));
            $totalQty = (int) $connection->fetchOne(
                $connection->select()
                    ->from(['soi' => $orderItemTable], ['total_qty' => 'SUM(soi.qty_ordered)'])
                    ->join(['so' => $orderTable], 'soi.order_id = so.entity_id', [])
                    ->where('soi.product_id = ?', $productId)
                    ->where('so.status IN (?)', ['processing', 'complete'])
                    ->where('DATE(so.created_at) >= ?', $periodStart)
            );
            $isBestSeller = $totalQty >= self::BESTSELLER_MIN_QTY;

            $product->setData('views_today', $viewsToday);
            $product->setData('is_best_seller', $isBestSeller);

            // Cachear resultado
            $this->cache->save(
                json_encode(['views_today' => $viewsToday, 'is_best_seller' => $isBestSeller]),
                $cacheKey,
                ['socialproof'],
                self::CACHE_LIFETIME
            );
        } catch (\Exception $e) {
            $this->logger->warning('[SocialProof] ' . $e->getMessage());
            $product->setData('views_today', 0);
            $product->setData('is_best_seller', false);
        }
    }
}
