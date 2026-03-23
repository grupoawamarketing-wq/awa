<?php
declare(strict_types=1);

namespace GrupoAwamotos\Theme\ViewModel;

use Magento\Catalog\Model\CategoryFactory;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Psr\Log\LoggerInterface;

class CategoryCarouselData implements ArgumentInterface
{
    public function __construct(
        private readonly CategoryFactory $categoryFactory,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @return array{url: string, name: string, count: int}
     */
    public function getCategoryData(int $categoryId): array
    {
        try {
            $category = $this->categoryFactory->create()->load($categoryId);
            if ($category->getId()) {
                return [
                    'url' => $category->getUrl(),
                    'name' => $category->getName(),
                    'count' => (int) $category->getProductCount(),
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
}
