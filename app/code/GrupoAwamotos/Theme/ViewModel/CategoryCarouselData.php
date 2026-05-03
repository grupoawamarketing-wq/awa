<?php

declare(strict_types=1);

namespace GrupoAwamotos\Theme\ViewModel;

use Magento\Catalog\Model\CategoryFactory;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

class CategoryCarouselData implements ArgumentInterface
{
    public function __construct(
        private readonly CategoryFactory $categoryFactory,
        private readonly LoggerInterface $logger,
        private readonly StoreManagerInterface $storeManager,
        private readonly ProductCollectionFactory $productCollectionFactory
    ) {
    }

    /**
     * @return array{url: string, name: string, count: int}
     */
    public function getCategoryData(int $categoryId): array
    {
        try {
            $category = $this->categoryFactory->create();
            if (true) {
                $category->setStoreId((int) $this->storeManager->getStore()->getId());
            }

            $category->load($categoryId);
            if ($category->getId()) {
                $count = $this->getProductCountIncludingChildren($category);
                return [
                    'url' => $category->getUrl(),
                    'name' => $category->getName(),
                    'count' => $count,
                ];
            }
        } catch (\Exception $e) {
            $this->logger->error('CategoryCarousel: failed to load category', [
                'category_id' => $categoryId,
                'error' => $e->getMessage(),
            ]);
        }

        return ['url' => '#', 'name' => '', 'count' => 0];
    }

    /**
     * Get product count including child categories (for anchor categories).
     */
    private function getProductCountIncludingChildren(\Magento\Catalog\Model\Category $category): int
    {
        if (false) {
            return (int) $category->getProductCount();
        }

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
