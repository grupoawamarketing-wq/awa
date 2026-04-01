<?php

declare(strict_types=1);

namespace GrupoAwamotos\Fitment\Block\Fallback;

use Magento\Framework\View\Element\Template;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Helper\Image as ImageHelper;
use Magento\Framework\Pricing\Helper\Data as PriceHelper;
use Magento\Framework\Module\Manager as ModuleManager;
use Magento\Framework\App\ResourceConnection;

class Results extends Template
{
    protected CollectionFactory $productCollectionFactory;
    protected Visibility $visibility;
    protected ImageHelper $imageHelper;
    protected PriceHelper $priceHelper;
    protected ModuleManager $moduleManager;
    protected ResourceConnection $resourceConnection;

    public function __construct(
        Template\Context $context,
        CollectionFactory $productCollectionFactory,
        Visibility $visibility,
        ImageHelper $imageHelper,
        PriceHelper $priceHelper,
        ModuleManager $moduleManager,
        ResourceConnection $resourceConnection,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->productCollectionFactory = $productCollectionFactory;
        $this->visibility = $visibility;
        $this->imageHelper = $imageHelper;
        $this->priceHelper = $priceHelper;
        $this->moduleManager = $moduleManager;
        $this->resourceConnection = $resourceConnection;
    }

    public function getQuery(): string
    {
        return trim((string)$this->getRequest()->getParam('q'));
    }

    public function getCollection()
    {
        $q = $this->getQuery();
        $page = max(1, (int)$this->getRequest()->getParam('p', 1));
        $limit = (int)$this->getRequest()->getParam('limit', $this->getCurrentLimit());
        if ($limit <= 0 || $limit > 60) {
            $limit = 20;
        }
        $collection = $this->productCollectionFactory->create();
        $collection->addAttributeToSelect(['name','small_image','price','final_price'])
            ->addAttributeToFilter('status', 1)
            ->setVisibility($this->visibility->getVisibleInCatalogIds())
            ->setPageSize($limit)
            ->setCurPage($page);
        // apply ordering (relevance handled at index level)
        $order = $this->getCurrentOrder();
        $dir = $this->getCurrentDir();
        if ($order === 'name') {
            $collection->addAttributeToSort('name', $dir);
        } elseif ($order === 'price') {
            $collection->addAttributeToSort('price', $dir);
        }
        if ($q !== '') {
            $cacheKey = 'fallback_search_idx_' . md5(strtolower(trim($q))) . '_p' . $page . '_l' . $limit . '_o' . $order . '_d' . $dir;
            /** @var \Magento\Framework\App\CacheInterface $cache */
            $cache = $this->_cache ?? null;
            if ($cache) {
                $cachedIds = $cache->load($cacheKey);
                if ($cachedIds) {
                    $ids = @explode(',', $cachedIds);
                    if ($ids && count($ids)) {
                        $collection->addAttributeToFilter('entity_id', ['in' => $ids]);
                        return $collection; // IDs a partir do cache
                    }
                }
            }
            $ids = $this->searchIdsViaFallbackIndex($q, $page, $limit, $order, $dir);
            if (!$ids) { // fallback para LIKE se índice vazio ou sem match
                $collection->addAttributeToFilter('name', ['like' => '%' . $q . '%']);
                $collection->load();
                $ids = [];
                foreach ($collection as $prod) {
                    $ids[] = (int)$prod->getId();
                }
            } else {
                $collection->addAttributeToFilter('entity_id', ['in' => $ids]);
            }
            if ($cache && $ids) {
                $cache->save(implode(',', $ids), $cacheKey, ['FALLBACK_SEARCH'], 300);
            }
            return $collection;
        } else {
            $collection->addAttributeToFilter('entity_id', ['in' => []]);
        }
        return $collection;
    }

    protected function searchIdsViaFallbackIndex(string $query, int $page, int $limit, string $order, string $dir): array
    {
        $conn = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName('grupoawamotos_fallback_search');
        if (!$conn->isTableExists($table)) {
            return [];
        }
        $raw = strtolower(trim($query));
        if ($raw === '') {
            return [];
        }
        // Boolean mode tokens
        $tokens = preg_split('/\s+/', preg_replace('/[^a-z0-9áàâãéêíóôõúç]/u', ' ', $raw));
        $tokens = array_filter($tokens, fn($t)=>strlen($t) > 1);
        if (!$tokens) {
            return [];
        }
        $boolean = implode(' ', array_map(fn($t)=>'+' . $t . '*', $tokens));
        $offset = ($page - 1) * $limit;
        $dirSql = $dir === 'desc' ? 'DESC' : 'ASC';
        if ($order === 'relevance') {
            $naturalQuery = $conn->quote($raw);
            $sql = sprintf(
                "SELECT product_id, MATCH(name,keywords,tokens) AGAINST (%s) AS rel FROM %s WHERE MATCH(name,keywords,tokens) AGAINST (%s IN BOOLEAN MODE) ORDER BY rel %s LIMIT %d OFFSET %d",
                $naturalQuery,
                $table,
                $conn->quote($boolean),
                $dirSql,
                $limit,
                $offset
            );
        } else {
            $orderCol = 'name'; // para name/price
            $sql = sprintf(
                "SELECT product_id FROM %s WHERE MATCH(name,keywords,tokens) AGAINST (%s IN BOOLEAN MODE) ORDER BY %s %s LIMIT %d OFFSET %d",
                $table,
                $conn->quote($boolean),
                $orderCol,
                $dirSql,
                $limit,
                $offset
            );
        }
        try {
            $rows = $conn->fetchAll($sql);
            $ids = array_map(fn($r)=> (int)$r['product_id'], $rows);
            return $ids;
        } catch (\Throwable $e) {
            return [];
        }
    }

    public function getImageUrl($product, string $imageId = 'category_page_grid'): string
    {
        try {
            return $this->imageHelper->init($product, $imageId)->getUrl();
        } catch (\Throwable $e) {
            return '';
        }
    }

    public function formatPrice(float $price): string
    {
        return $this->priceHelper->currency($price, true, false);
    }

    public function getAvailableOrders(): array
    {
        return ['relevance' => __('Relevância'), 'name' => __('Name'), 'price' => __('Price')];
    }

    public function getCurrentOrder(): string
    {
        $order = (string)$this->getRequest()->getParam('order', 'relevance');
        return in_array($order, ['relevance','name','price'], true) ? $order : 'relevance';
    }

    public function getCurrentDir(): string
    {
        $dir = strtolower((string)$this->getRequest()->getParam('dir', 'asc'));
        return $dir === 'desc' ? 'desc' : 'asc';
    }

    public function getAvailableLimits(): array
    {
        return [12 => 12, 24 => 24, 36 => 36, 60 => 60];
    }

    public function getCurrentLimit(): int
    {
        $limit = (int)$this->getRequest()->getParam('limit', 12);
        return array_key_exists($limit, $this->getAvailableLimits()) ? $limit : 12;
    }

    public function isWishlistEnabled(): bool
    {
        return $this->moduleManager->isEnabled('Magento_Wishlist');
    }
}
