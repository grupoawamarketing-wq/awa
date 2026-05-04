<?php

declare(strict_types=1);

namespace GrupoAwamotos\CatalogFix\Plugin\Catalog\Helper\Data;

use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Api\Data\CategoryInterface;
use Magento\Catalog\Helper\Data as CatalogHelper;
use Magento\Catalog\Model\Product;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;
use ReflectionProperty;

/**
 * BREAD-001: em URLs diretas de produto não existe `current_category` no registry,
 * e o breadcrumb fica só "Início > Nome do produto". Este plugin injeta a trilho da
 * categoria mais específica (maior level) entre as categorias atribuídas ao produto.
 */
final class ProductBreadcrumbCategoryFallbackPlugin
{
    public function __construct(
        private readonly CategoryRepositoryInterface $categoryRepository,
        private readonly StoreManagerInterface $storeManager,
    ) {
    }

    /**
     * @return array<string, array{label: string, link?: string}>
     */
    public function aroundGetBreadcrumbPath(CatalogHelper $subject, \Closure $proceed): array
    {
        $path = $proceed();
        $extended = $this->extendPathWithProductCategories($subject, $path);
        if ($extended === null) {
            return $path;
        }

        $this->replaceCategoryPathCache($subject, $extended);

        return $extended;
    }

    /**
     * @param array<string, array<string, mixed>> $path
     * @return array<string, array<string, mixed>>|null
     */
    private function extendPathWithProductCategories(CatalogHelper $subject, array $path): ?array
    {
        if ($subject->getCategory() !== null) {
            return null;
        }

        $product = $subject->getProduct();
        if (!$product instanceof Product) {
            return null;
        }

        foreach (array_keys($path) as $key) {
            if (is_string($key) && str_starts_with($key, 'category')) {
                return null;
            }
        }

        if (!isset($path['product'])) {
            return null;
        }

        $best = $this->resolveDeepestActiveCategory($product);
        if ($best === null) {
            return null;
        }

        $trail = $this->buildCategoryTrail($best);
        if ($trail === []) {
            return null;
        }

        $trail['product'] = $path['product'];

        return $trail;
    }

    private function resolveDeepestActiveCategory(Product $product): ?CategoryInterface
    {
        $ids = $product->getCategoryIds();
        if ($ids === null || $ids === []) {
            return null;
        }

        $best = null;
        $bestLevel = -1;

        foreach ($ids as $rawId) {
            $id = (int) $rawId;
            if ($id <= 2) {
                continue;
            }
            try {
                $cat = $this->categoryRepository->get($id, $this->storeManager->getStore()->getId());
            } catch (NoSuchEntityException) {
                continue;
            }
            if (!$cat->getIsActive()) {
                continue;
            }
            $level = (int) $cat->getLevel();
            if ($level > $bestLevel) {
                $bestLevel = $level;
                $best = $cat;
            }
        }

        return $best;
    }

    /**
     * @return array<string, array{label: string, link: string}>
     */
    private function buildCategoryTrail(CategoryInterface $category): array
    {
        $model = $category instanceof \Magento\Catalog\Model\Category ? $category : null;
        if ($model === null) {
            return [];
        }

        $pathInStore = (string) $model->getPathInStore();
        if ($pathInStore === '') {
            return [];
        }

        $pathIds = array_reverse(explode(',', $pathInStore));
        $parents = $model->getParentCategories();
        $out = [];

        foreach ($pathIds as $categoryId) {
            $cid = (int) $categoryId;
            if ($cid <= 2) {
                continue;
            }
            if (!isset($parents[$cid]) || !$parents[$cid]->getName()) {
                continue;
            }
            $c = $parents[$cid];
            $out['category' . $cid] = [
                'label' => (string) $c->getName(),
                'link' => (string) $c->getUrl(),
            ];
        }

        return $out;
    }

    /**
     * @param array<string, array<string, mixed>> $path
     */
    private function replaceCategoryPathCache(CatalogHelper $helper, array $path): void
    {
        $rp = new ReflectionProperty(CatalogHelper::class, '_categoryPath');
        $rp->setAccessible(true);
        $rp->setValue($helper, $path);
    }
}
