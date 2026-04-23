<?php

declare(strict_types=1);

namespace GrupoAwamotos\SchemaOrg\Plugin;

use Magento\Catalog\Helper\Product\View as ProductViewHelper;
use Magento\Framework\View\Result\Page;
use Magento\Catalog\Model\Product;
use Magento\Framework\Registry;

/**
 * Generates a human-readable meta description fallback when the product
 * `meta_description` attribute is empty or consists only of its uppercase name.
 */
class ProductMetaDescriptionFallback
{
    public function __construct(
        private readonly Registry $registry
    ) {
    }

    /**
     * After prepareAndRender, fix generic meta descriptions.
     *
     * @param ProductViewHelper $subject
     * @param ProductViewHelper $result
     * @param Page $resultPage
     * @param int $productId
     * @param \Magento\Catalog\Controller\Product\View $controller
     * @param \Magento\Framework\DataObject|null $params
     * @return ProductViewHelper
     */
    public function afterPrepareAndRender(
        ProductViewHelper $subject,
        $result,
        Page $resultPage,
        $productId,
        $controller,
        $params = null
    ) {
        $product = $this->registry->registry('current_product');
        if (!$product instanceof Product) {
            return $result;
        }

        $pageConfig = $resultPage->getConfig();
        $currentDesc = trim((string) $pageConfig->getDescription());
        $productName = trim((string) $product->getName());

        // Check if description is just the uppercase product name or too short/generic
        if ($this->isGenericDescription($currentDesc, $productName)) {
            $pageConfig->setDescription($this->buildFallbackDescription($product));
        }

        return $result;
    }

    private function isGenericDescription(string $description, string $productName): bool
    {
        if ($description === '') {
            return true;
        }
        // Magento default: just the product name (often ALL CAPS)
        if (mb_strtoupper($description) === mb_strtoupper($productName)) {
            return true;
        }
        // Too short to be useful for SEO
        if (mb_strlen($description) < 50) {
            return true;
        }

        return false;
    }

    private function buildFallbackDescription(Product $product): string
    {
        $name = $this->normalizeCase((string) $product->getName());
        $shortDesc = trim(strip_tags((string) $product->getShortDescription()));

        if ($shortDesc !== '' && mb_strlen($shortDesc) >= 30) {
            // Use short_description if it's substantial
            return mb_substr($shortDesc, 0, 160);
        }

        // Build from product attributes
        $parts = [$name];

        $sku = (string) $product->getSku();
        if ($sku !== '') {
            $parts[] = "Cód. $sku";
        }

        $brand = $product->getAttributeText('manufacturer');
        if ($brand && is_string($brand)) {
            $parts[] = $brand;
        }

        $suffix = 'Compre na AWA Motos com entrega para todo Brasil.';
        $base = implode(' | ', $parts);

        // Ensure it fits within 160 chars
        $maxBase = 160 - mb_strlen($suffix) - 2;
        if (mb_strlen($base) > $maxBase) {
            $base = mb_substr($base, 0, $maxBase);
        }

        return "$base. $suffix";
    }

    /**
     * Convert ALL CAPS product names to Title Case.
     */
    private function normalizeCase(string $text): string
    {
        // Only convert if it looks ALL CAPS (more than 60% uppercase letters)
        $letters = preg_replace('/[^a-zA-ZÀ-ÿ]/', '', $text);
        if ($letters === '' || $letters === null) {
            return $text;
        }
        $upper = preg_replace('/[^A-ZÀ-Ý]/', '', $letters);
        if ($upper === null) {
            return $text;
        }
        $ratio = mb_strlen($upper) / mb_strlen($letters);

        if ($ratio > 0.6) {
            return mb_convert_case($text, MB_CASE_TITLE, 'UTF-8');
        }

        return $text;
    }
}
