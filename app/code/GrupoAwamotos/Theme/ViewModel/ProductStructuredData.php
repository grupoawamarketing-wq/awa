<?php

declare(strict_types=1);

namespace GrupoAwamotos\Theme\ViewModel;

use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Helper\Image as ImageHelper;
use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\Product;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Registry;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Review\Model\ReviewFactory;
use Magento\Store\Model\StoreManagerInterface;

final class ProductStructuredData implements ArgumentInterface
{
    public function __construct(
        private readonly Registry $registry,
        private readonly ImageHelper $imageHelper,
        private readonly ReviewFactory $reviewFactory,
        private readonly StoreManagerInterface $storeManager,
        private readonly CategoryRepositoryInterface $categoryRepository,
    ) {
    }

    public function getCurrentProduct(): ?Product
    {
        $product = $this->registry->registry('product') ?: $this->registry->registry('current_product');

        return $product instanceof Product ? $product : null;
    }

    /**
     * URL da imagem principal do produto — usada no preload LCP do <head>.
     */
    public function getProductLcpImageUrl(): string
    {
        $product = $this->getCurrentProduct();
        if (!$product || !$product->getId()) {
            return '';
        }

        return (string) $this->imageHelper->init($product, 'product_base_image')->getUrl();
    }

    /**
     * @return array<string, mixed>
     */
    public function getProductStructuredData(): array
    {
        $product = $this->getCurrentProduct();
        if (!$product || !$product->getId()) {
            return [];
        }

        $productUrl = (string) $product->getProductUrl();
        $schema = [
            '@context' => 'https://schema.org/',
            '@type' => 'Product',
            'name' => $this->normalizeText((string) $product->getName()),
            'sku' => (string) $product->getSku(),
            'image' => (string) $this->imageHelper->init($product, 'product_base_image')->getUrl(),
            'url' => $productUrl,
            'itemCondition' => 'https://schema.org/NewCondition',
            'brand' => [
                '@type' => 'Brand',
                'name' => $this->resolveBrandName($product),
            ],
        ];

        $description = $this->normalizeText(
            (string) ($product->getShortDescription() ?: $product->getDescription() ?: '')
        );
        if ($description !== '') {
            $schema['description'] = mb_substr($description, 0, 1000);
        }

        $finalPrice = (float) $product->getFinalPrice();
        if ($finalPrice > 0) {
            $schema['offers'] = [
                '@type' => 'Offer',
                'url' => $productUrl,
                'priceCurrency' => 'BRL',
                'price' => number_format($finalPrice, 2, '.', ''),
                'itemCondition' => 'https://schema.org/NewCondition',
                'priceValidUntil' => date('Y-12-31', strtotime('+1 year')),
                'availability' => $product->isAvailable()
                    ? 'https://schema.org/InStock'
                    : 'https://schema.org/OutOfStock',
                'seller' => [
                    '@type' => 'Organization',
                    'name' => 'Grupo Awamotos',
                ],
            ];
        }

        $aggregateRating = $this->getAggregateRating($product);
        if ($aggregateRating !== null) {
            $schema['aggregateRating'] = $aggregateRating;
        }

        $mpn = $product->getData('mpn') ?: $product->getData('codigo_original');
        if ($mpn) {
            $schema['mpn'] = (string) $mpn;
        }

        $gtin = $product->getData('ean') ?: $product->getData('gtin');
        if ($gtin) {
            $schema['gtin13'] = (string) $gtin;
        }

        return $schema;
    }

    /**
     * BreadcrumbList JSON-LD: Home → Category (optional) → Product.
     *
     * @return array<string, mixed>
     */
    public function getBreadcrumbStructuredData(): array
    {
        $product = $this->getCurrentProduct();
        if (!$product || !$product->getId()) {
            return [];
        }

        try {
            $baseUrl = rtrim($this->storeManager->getStore()->getBaseUrl(), '/');
            $storeId = (int) $this->storeManager->getStore()->getId();
        } catch (\Throwable) {
            return [];
        }

        $items = [
            [
                '@type' => 'ListItem',
                'position' => 1,
                'name' => 'Home',
                'item' => $baseUrl . '/',
            ],
        ];

        $position = 2;
        $category = $this->resolveCurrentCategory($product, $storeId);

        if ($category !== null) {
            $items[] = [
                '@type' => 'ListItem',
                'position' => $position++,
                'name' => $this->normalizeText((string) $category->getName()),
                'item' => (string) $category->getUrl(),
            ];
        }

        $items[] = [
            '@type' => 'ListItem',
            'position' => $position,
            'name' => $this->normalizeText((string) $product->getName()),
        ];

        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => $items,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function getAggregateRating(Product $product): ?array
    {
        try {
            $storeId = (int) $this->storeManager->getStore()->getId();
            $this->reviewFactory->create()->getEntitySummary($product, $storeId);

            $ratingValue = (float) $product->getData('rating_summary');
            $reviewCount = (int) $product->getData('reviews_count');

            if ($ratingValue <= 0 || $reviewCount <= 0) {
                return null;
            }

            return [
                '@type' => 'AggregateRating',
                'ratingValue' => number_format($ratingValue / 20, 1),
                'reviewCount' => $reviewCount,
                'bestRating' => '5',
                'worstRating' => '1',
            ];
        } catch (\Throwable) {
            return null;
        }
    }

    private function resolveCurrentCategory(Product $product, int $storeId): ?Category
    {
        $category = $this->registry->registry('current_category');
        if ($category instanceof Category && $category->getId()) {
            return $category;
        }

        $categoryIds = $product->getCategoryIds();
        if (empty($categoryIds)) {
            return null;
        }

        foreach (array_reverse($categoryIds) as $categoryId) {
            try {
                /** @var Category $cat */
                $cat = $this->categoryRepository->get((int) $categoryId, $storeId);
                if ($cat->getIsActive() && (int) $cat->getLevel() >= 2) {
                    return $cat;
                }
            } catch (NoSuchEntityException) {
                continue;
            }
        }

        return null;
    }

    private function resolveBrandName(Product $product): string
    {
        try {
            $brand = $product->getAttributeText('manufacturer') ?: $product->getAttributeText('marca');
            $brand = is_scalar($brand) ? (string) $brand : '';

            return $this->normalizeText($brand) ?: 'AWA Motos';
        } catch (\Throwable) {
            return 'AWA Motos';
        }
    }

    private function normalizeText(string $value): string
    {
        $value = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = (string) preg_replace('/\s+/u', ' ', $value);

        return trim($value);
    }
}
