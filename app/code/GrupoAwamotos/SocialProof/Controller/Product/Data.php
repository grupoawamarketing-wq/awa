<?php

declare(strict_types=1);

namespace GrupoAwamotos\SocialProof\Controller\Product;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\Result\Json;
use Psr\Log\LoggerInterface;

/**
 * AJAX endpoint para dados de prova social por produto.
 *
 * GET /socialproof/product/data?product_id=123
 *
 * Resposta: JSON com views_today, is_best_seller, low_stock, qty.
 * Internamente cacheada por 10 minutos no object cache do Magento.
 *
 * Permite que o bloco product.info.social.proof seja removido de cacheable="false",
 * tornando a PDP cacheavel no FPC/Varnish e reduzindo TTFB de ~1.4s para ~30ms.
 */
class Data implements HttpGetActionInterface
{
    private const CACHE_PREFIX       = 'socialproof_pdp_';
    private const CACHE_LIFETIME     = 600; // 10 minutos
    private const BESTSELLER_DAYS    = 30;
    private const BESTSELLER_MIN_QTY = 2;
    private const LOW_STOCK_THRESHOLD = 10;
    private const DEFAULT_DATA = [
        'views_today'    => 0,
        'is_best_seller' => false,
        'low_stock'      => false,
        'qty'            => 0,
    ];

    public function __construct(
        private readonly RequestInterface $request,
        private readonly JsonFactory $resultJsonFactory,
        private readonly ResourceConnection $resourceConnection,
        private readonly CacheInterface $cache,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function execute(): Json
    {
        $result = $this->resultJsonFactory->create();
        $result->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate', true);

        $productId = (int) $this->request->getParam('product_id');
        if ($productId <= 0) {
            return $result->setData(self::DEFAULT_DATA);
        }

        $cacheKey = self::CACHE_PREFIX . $productId;
        $cached   = $this->cache->load($cacheKey);
        if ($cached !== false) {
            $decoded = json_decode($cached, true);
            return $result->setData(
                is_array($decoded) ? array_replace(self::DEFAULT_DATA, $decoded) : self::DEFAULT_DATA
            );
        }

        try {
            $data = $this->fetchData($productId);
            $this->cache->save(json_encode($data), $cacheKey, ['socialproof'], self::CACHE_LIFETIME);
            return $result->setData($data);
        } catch (\Exception $e) {
            $this->logger->warning('[SocialProof::Data] ' . $e->getMessage());
            return $result->setData(self::DEFAULT_DATA);
        }
    }

    /**
     * @return array<string, int|bool>
     */
    private function fetchData(int $productId): array
    {
        $conn          = $this->resourceConnection->getConnection();
        $todayStart    = date('Y-m-d 00:00:00');
        $tomorrowStart = date('Y-m-d 00:00:00', strtotime('+1 day'));

        // Visualizacoes hoje
        $viewsTable = $this->resourceConnection->getTableName('report_viewed_product_index');
        $viewsToday = (int) $conn->fetchOne(
            $conn->select()
                ->from($viewsTable, ['cnt' => 'COUNT(*)'])
                ->where('product_id = ?', $productId)
                ->where('added_at >= ?', $todayStart)
                ->where('added_at < ?', $tomorrowStart)
        );

        // Mais vendido (ultimos 30 dias)
        $oiTable     = $this->resourceConnection->getTableName('sales_order_item');
        $oTable      = $this->resourceConnection->getTableName('sales_order');
        $periodStart = date('Y-m-d 00:00:00', strtotime('-' . self::BESTSELLER_DAYS . ' days'));
        $totalQty    = (int) $conn->fetchOne(
            $conn->select()
                ->from(['soi' => $oiTable], ['total_qty' => 'SUM(soi.qty_ordered)'])
                ->join(['so' => $oTable], 'soi.order_id = so.entity_id', [])
                ->where('soi.product_id = ?', $productId)
                ->where('so.status IN (?)', ['processing', 'complete'])
                ->where('so.created_at >= ?', $periodStart)
        );
        $isBestSeller = $totalQty >= self::BESTSELLER_MIN_QTY;

        // Estoque (via cataloginventory_stock_item)
        $stockTable = $this->resourceConnection->getTableName('cataloginventory_stock_item');
        $stockRow   = $conn->fetchRow(
            $conn->select()
                ->from($stockTable, ['qty', 'is_in_stock'])
                ->where('product_id = ?', $productId)
                ->where('stock_id = ?', 1)
        );
        $qty        = $stockRow ? (int) $stockRow['qty'] : 0;
        $isLowStock = $qty > 0 && $qty < self::LOW_STOCK_THRESHOLD;

        return [
            'views_today'    => $viewsToday,
            'is_best_seller' => $isBestSeller,
            'low_stock'      => $isLowStock,
            'qty'            => $qty,
        ];
    }
}
