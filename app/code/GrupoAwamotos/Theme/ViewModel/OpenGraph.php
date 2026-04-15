<?php

declare(strict_types=1);

namespace GrupoAwamotos\Theme\ViewModel;

use Magento\Catalog\Helper\Image as ImageHelper;
use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\Product;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\Registry;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Framework\View\Page\Config as PageConfig;
use Magento\Store\Model\StoreManagerInterface;

final class OpenGraph implements ArgumentInterface
{
    private const DEFAULT_DESCRIPTION = 'Distribuidora B2B de peças e acessórios para motos. Bauletos, retrovisores, guidões e mais. Preços exclusivos para lojistas.';
    private const DEFAULT_IMAGE_PATH = '/media/logo/stores/1/logo-awa.png';

    public function __construct(
        private readonly StoreManagerInterface $storeManager,
        private readonly Registry $registry,
        private readonly PageConfig $pageConfig,
        private readonly ImageHelper $imageHelper,
        private readonly HttpRequest $request,
    ) {
    }

    public function getBaseUrl(): string
    {
        return rtrim($this->storeManager->getStore()->getBaseUrl(), '/');
    }

    public function getStoreName(): string
    {
        return (string) $this->storeManager->getStore()->getName();
    }

    public function getCurrentUrl(): string
    {
        $baseUrl = $this->getBaseUrl();
        $identifier = trim((string) $this->request->getPathInfo(), '/');

        return $identifier !== '' ? $baseUrl . '/' . $identifier : $baseUrl . '/';
    }

    public function getCurrentProduct(): ?Product
    {
        $product = $this->registry->registry('current_product') ?: $this->registry->registry('product');

        return $product instanceof Product ? $product : null;
    }

    public function getCurrentCategory(): ?Category
    {
        $category = $this->registry->registry('current_category');

        return $category instanceof Category ? $category : null;
    }

    public function getPageTitle(): string
    {
        return $this->normalizeText((string) $this->pageConfig->getTitle()->get());
    }

    public function getPageDescription(): string
    {
        $description = $this->normalizeText((string) $this->pageConfig->getDescription());

        return $description !== '' ? $description : self::DEFAULT_DESCRIPTION;
    }

    public function getDefaultImage(): string
    {
        return $this->getBaseUrl() . self::DEFAULT_IMAGE_PATH;
    }

    public function getProductImageUrl(Product $product): string
    {
        return (string) $this->imageHelper->init($product, 'product_base_image')->getUrl();
    }

    public function isHomepage(): bool
    {
        return $this->request->getFullActionName() === 'cms_index_index';
    }

    /**
     * @return array<string, string>
     */
    public function getMetaData(): array
    {
        $title = $this->getPageTitle();
        $description = $this->getPageDescription();
        $image = $this->getDefaultImage();
        $meta = [
            'type' => 'website',
            'title' => $title !== '' ? $title : 'AWA Motos | Peças e Acessórios para Motos',
            'description' => $description,
            'image' => $image,
            'url' => $this->getCurrentUrl(),
            'site_name' => $this->getStoreName() !== '' ? $this->getStoreName() : 'AWA Motos',
            'locale' => 'pt_BR',
            'image_alt' => $title !== '' ? $title : 'AWA Motos',
        ];

        $product = $this->getCurrentProduct();
        if ($product && $product->getId()) {
            $meta['type'] = 'product';
            $meta['title'] = $this->normalizeText((string) $product->getName()) ?: $meta['title'];
            $meta['description'] = $this->resolveProductDescription($product);
            $meta['image'] = $this->getProductImageUrl($product) ?: $meta['image'];
            $meta['image_alt'] = $meta['title'];

            $finalPrice = (float) $product->getFinalPrice();
            if ($finalPrice > 0) {
                $meta['price_amount'] = number_format($finalPrice, 2, '.', '');
                $meta['price_currency'] = 'BRL';
            }

            if ($product->isAvailable()) {
                $meta['availability'] = 'in stock';
            }

            return $meta;
        }

        $category = $this->getCurrentCategory();
        if ($category && $category->getId()) {
            $meta['title'] = $this->normalizeText((string) $category->getName()) ?: $meta['title'];
            $meta['description'] = $this->resolveCategoryDescription($category);
            $meta['image_alt'] = $meta['title'];

            $categoryImage = (string) $category->getImageUrl();
            if ($categoryImage !== '') {
                $meta['image'] = $categoryImage;
            }
        }

        return $meta;
    }

    private function resolveProductDescription(Product $product): string
    {
        $description = $this->normalizeText(
            (string) ($product->getShortDescription() ?: $product->getDescription() ?: '')
        );

        if ($description !== '') {
            return mb_substr($description, 0, 200);
        }

        return $this->getPageDescription();
    }

    private function resolveCategoryDescription(Category $category): string
    {
        $description = $this->normalizeText((string) ($category->getDescription() ?: ''));

        if ($description !== '') {
            return mb_substr($description, 0, 200);
        }

        return $this->getPageDescription();
    }

    private function normalizeText(string $value): string
    {
        $value = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = (string) preg_replace('/\s+/u', ' ', $value);

        return trim($value);
    }
}
