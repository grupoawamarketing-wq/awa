<?php

declare(strict_types=1);

namespace GrupoAwamotos\Theme\ViewModel;

use Magento\Catalog\Model\CategoryFactory;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

class CategoryCarouselData implements ArgumentInterface
{
    private const CACHE_PREFIX   = 'carousel_cat_';
    private const CACHE_LIFETIME = 3600; // 1 hora

    /** @var array<int, array{url: string, name: string, count: int}> */
    private array $localCache = [];

    public function __construct(
        private readonly CategoryFactory $categoryFactory,
        private readonly LoggerInterface $logger,
        private readonly StoreManagerInterface $storeManager,
        private readonly ProductCollectionFactory $productCollectionFactory,
        private readonly CacheInterface $cache
    ) {
    }

    /**
     * @return array{url: string, name: string, count: int}
     */
    public function getCategoryData(int $categoryId): array
    {
        if (isset($this->localCache[$categoryId])) {
            return $this->localCache[$categoryId];
        }

        $storeId  = (int) $this->storeManager->getStore()->getId();
        $cacheKey = self::CACHE_PREFIX . $storeId . '_' . $categoryId;
        $cached   = $this->cache->load($cacheKey);

        if ($cached !== false) {
            $decoded = json_decode($cached, true);
            if (is_array($decoded)) {
                return $this->localCache[$categoryId] = $decoded;
            }
        }

        try {
            $category = $this->categoryFactory->create();
            $category->setStoreId($storeId);
            $category->load($categoryId);

            if ($category->getId()) {
                $data = [
                    'url'   => $category->getUrl(),
                    'name'  => $category->getName(),
                    'count' => $this->getProductCountIncludingChildren($category),
                ];
                $this->cache->save(
                    json_encode($data),
                    $cacheKey,
                    ['CATEGORY_' . $categoryId, 'CAROUSEL'],
                    self::CACHE_LIFETIME
                );
                return $this->localCache[$categoryId] = $data;
            }
        } catch (\Exception $e) {
            $this->logger->error('CategoryCarousel: failed to load category', [
                'category_id' => $categoryId,
                'error'       => $e->getMessage(),
            ]);
        }

        return $this->localCache[$categoryId] = ['url' => '#', 'name' => '', 'count' => 0];
    }

    /**
     * Get product count including child categories (for anchor categories).
     */
    private function getProductCountIncludingChildren(\Magento\Catalog\Model\Category $category): int
    {
        $collection = $this->productCollectionFactory->create();
        $collection->addCategoryFilter($category);
        $collection->addAttributeToFilter('status', \Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED);
        $collection->addAttributeToFilter('visibility', ['in' => [
            \Magento\Catalog\Model\Product\Visibility::VISIBILITY_IN_CATALOG,
            \Magento\Catalog\Model\Product\Visibility::VISIBILITY_BOTH,
        ]]);

        return (int) $collection->getSize();
    }
}
