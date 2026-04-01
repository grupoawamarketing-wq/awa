<?php

/**
 * Product Schema.org Block (DEPRECATED)
 * Gera JSON-LD para páginas de produto
 *
 * @deprecated Use \GrupoAwamotos\SchemaOrg\Block\ProductSchema instead.
 *             This class is not referenced by any layout XML and will be
 *             removed in a future release.
 */

declare(strict_types=1);

namespace GrupoAwamotos\SchemaOrg\Block;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Framework\Registry;
use Magento\Framework\Pricing\Helper\Data as PriceHelper;
use Magento\Review\Model\ReviewFactory;
use Magento\Store\Model\StoreManagerInterface;

class Product extends Template
{
    protected Registry $registry;
    protected PriceHelper $priceHelper;
    protected ReviewFactory $reviewFactory;
    protected StoreManagerInterface $storeManager;

    public function __construct(
        Context $context,
        Registry $registry,
        PriceHelper $priceHelper,
        ReviewFactory $reviewFactory,
        StoreManagerInterface $storeManager,
        array $data = []
    ) {
        $this->registry = $registry;
        $this->priceHelper = $priceHelper;
        $this->reviewFactory = $reviewFactory;
        $this->storeManager = $storeManager;
        parent::__construct($context, $data);
    }

    /**
     * Retorna produto atual
     */
    public function getProduct()
    {
        return $this->registry->registry('current_product');
    }

    /**
     * Gera schema.org Product JSON-LD
     */
    public function getProductSchema()
    {
        $product = $this->getProduct();
        if (!$product || !$product->getId()) {
            return '';
        }

        $store = $this->storeManager->getStore();
        $currency = $store->getCurrentCurrency()->getCode();

        // Dados básicos
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $product->getName(),
            'description' => strip_tags($product->getShortDescription() ?: $product->getDescription()),
            'sku' => $product->getSku(),
            'image' => $this->getProductImage($product),
            'url' => $product->getProductUrl(),
        ];

        // Marca
        if ($brand = $product->getAttributeText('manufacturer')) {
            $schema['brand'] = [
                '@type' => 'Brand',
                'name' => $brand
            ];
        }

        // Ofertas
        $price = $product->getFinalPrice();
        $specialPrice = $product->getSpecialPrice();

        $offer = [
            '@type' => 'Offer',
            'price' => number_format($price, 2, '.', ''),
            'priceCurrency' => $currency,
            'url' => $product->getProductUrl(),
            'availability' => $product->isAvailable()
                ? 'https://schema.org/InStock'
                : 'https://schema.org/OutOfStock',
        ];

        // Preço válido até (se promoção)
        if ($specialPrice && $product->getSpecialToDate()) {
            $offer['priceValidUntil'] = date('Y-m-d', strtotime($product->getSpecialToDate()));
        }

        $schema['offers'] = $offer;

        // Reviews agregados (se houver)
        $ratingSummary = $product->getRatingSummary();
        if ($ratingSummary && $ratingSummary->getRatingSummary()) {
            $reviewCount = $product->getReviewsCollection()->getSize();
            $ratingValue = ($ratingSummary->getRatingSummary() / 20); // Converter de 0-100 para 0-5

            if ($reviewCount > 0) {
                $schema['aggregateRating'] = [
                    '@type' => 'AggregateRating',
                    'ratingValue' => number_format($ratingValue, 1),
                    'reviewCount' => $reviewCount,
                    'bestRating' => '5',
                    'worstRating' => '1'
                ];
            }
        }

        return json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    /**
     * Retorna URL da imagem do produto
     */
    protected function getProductImage($product)
    {
        try {
            $imageUrl = $this->storeManager->getStore()
                ->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA)
                . 'catalog/product' . $product->getImage();
            return $imageUrl;
        } catch (\Exception $e) {
            return '';
        }
    }
}
