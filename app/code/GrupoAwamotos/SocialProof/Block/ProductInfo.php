<?php

declare(strict_types=1);

namespace GrupoAwamotos\SocialProof\Block;

use Magento\Framework\View\Element\Template;
use Magento\Framework\Registry;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\App\CacheInterface;
use Psr\Log\LoggerInterface;

class ProductInfo extends Template
{
    private const CACHE_PREFIX   = 'socialproof_pdp_';
    private const CACHE_LIFETIME = 600; // 10 minutos
    private const BESTSELLER_DAYS = 30;
    private const BESTSELLER_MIN_QTY = 2;
    private const LOW_STOCK_THRESHOLD = 10;

    protected Registry $registry;
    private ResourceConnection $resourceConnection;
    private CacheInterface $cache;
    private LoggerInterface $logger;

    public function __construct(
        Template\Context $context,
        Registry $registry,
        ResourceConnection $resourceConnection,
        CacheInterface $cache,
        LoggerInterface $logger,
        array $data = []
    ) {
        $this->registry            = $registry;
        $this->resourceConnection  = $resourceConnection;
        $this->cache               = $cache;
        $this->logger              = $logger;
        parent::__construct($context, $data);
    }

    public function getProduct(): ?\Magento\Catalog\Model\Product
    {
        return $this->registry->registry('current_product');
    }

    private function getProductId(): int
    {
        $product = $this->getProduct();
        return $product ? (int) $product->getId() : 0;
    }

    private function getSocialProofData(): array
    {
        $productId = $this->getProductId();
        if ($productId === 0) {
            return ['views_today' => 0, 'is_best_seller' => false, 'low_stock' => false, 'qty' => 0];
        }

        $cacheKey = self::CACHE_PREFIX . $productId;
        $cached = $this->cache->load($cacheKey);
        if ($cached !== false) {
            return json_decode($cached, true);
        }

        try {
            $conn     = $this->resourceConnection->getConnection();
            $today    = date('Y-m-d');

            // Visualizações hoje
            $viewsTable = $this->resourceConnection->getTableName('report_viewed_product_index');
            $viewsToday = (int) $conn->fetchOne(
                $conn->select()
                    ->from($viewsTable, ['cnt' => 'COUNT(*)'])
                    ->where('product_id = ?', $productId)
                    ->where('DATE(added_at) = ?', $today)
            );

            // Mais vendido (últimos 30 dias)
            $oiTable   = $this->resourceConnection->getTableName('sales_order_item');
            $oTable    = $this->resourceConnection->getTableName('sales_order');
            $periodStart = date('Y-m-d', strtotime('-' . self::BESTSELLER_DAYS . ' days'));
            $totalQty  = (int) $conn->fetchOne(
                $conn->select()
                    ->from(['soi' => $oiTable], ['total_qty' => 'SUM(soi.qty_ordered)'])
                    ->join(['so' => $oTable], 'soi.order_id = so.entity_id', [])
                    ->where('soi.product_id = ?', $productId)
                    ->where('so.status IN (?)', ['processing', 'complete'])
                    ->where('DATE(so.created_at) >= ?', $periodStart)
            );
            $isBestSeller = $totalQty >= self::BESTSELLER_MIN_QTY;

            // Estoque baixo (lê do produto no Registry)
            $product = $this->getProduct();
            $qty = 0;
            $isLowStock = false;
            if ($product) {
                $ea = $product->getExtensionAttributes();
                $si = $ea ? $ea->getStockItem() : null;
                if ($si) {
                    $qty = (int) $si->getQty();
                    $isLowStock = $qty > 0 && $qty < self::LOW_STOCK_THRESHOLD;
                }
            }

            $data = [
                'views_today'   => $viewsToday,
                'is_best_seller' => $isBestSeller,
                'low_stock'     => $isLowStock,
                'qty'           => $qty,
            ];

            $this->cache->save(
                json_encode($data),
                $cacheKey,
                ['socialproof'],
                self::CACHE_LIFETIME
            );

            return $data;
        } catch (\Exception $e) {
            $this->logger->warning('[SocialProof::ProductInfo] ' . $e->getMessage());
            return ['views_today' => 0, 'is_best_seller' => false, 'low_stock' => false, 'qty' => 0];
        }
    }

    public function getViewsToday(): int
    {
        return (int) ($this->getSocialProofData()['views_today'] ?? 0);
    }

    public function isBestSeller(): bool
    {
        return (bool) ($this->getSocialProofData()['is_best_seller'] ?? false);
    }

    public function isLowStock(): bool
    {
        return (bool) ($this->getSocialProofData()['low_stock'] ?? false);
    }

    public function getStockQty(): int
    {
        return (int) ($this->getSocialProofData()['qty'] ?? 0);
    }
}
