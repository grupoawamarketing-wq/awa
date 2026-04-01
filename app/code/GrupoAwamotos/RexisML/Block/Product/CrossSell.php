<?php

declare(strict_types=1);

namespace GrupoAwamotos\RexisML\Block\Product;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Framework\Registry;
use Magento\Framework\App\ResourceConnection;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Block\Product\ListProduct;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Psr\Log\LoggerInterface;

class CrossSell extends Template
{
    private Registry $registry;
    private ResourceConnection $resource;
    private ProductRepositoryInterface $productRepository;
    private ListProduct $listProductBlock;
    private ScopeConfigInterface $scopeConfig;
    private PriceCurrencyInterface $priceCurrency;
    private LoggerInterface $logger;
    private ?array $cachedItems = null;

    public function __construct(
        Context $context,
        Registry $registry,
        ResourceConnection $resource,
        ProductRepositoryInterface $productRepository,
        ListProduct $listProductBlock,
        ScopeConfigInterface $scopeConfig,
        PriceCurrencyInterface $priceCurrency,
        LoggerInterface $logger,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->registry = $registry;
        $this->resource = $resource;
        $this->productRepository = $productRepository;
        $this->listProductBlock = $listProductBlock;
        $this->scopeConfig = $scopeConfig;
        $this->priceCurrency = $priceCurrency;
        $this->logger = $logger;
    }

    /**
     * Get cross-sell products for the current product using MBA rules
     */
    public function getCrossSellProducts(): array
    {
        if ($this->cachedItems !== null) {
            return $this->cachedItems;
        }

        $this->cachedItems = [];

        if (!$this->isEnabled()) {
            return $this->cachedItems;
        }

        $product = $this->registry->registry('current_product');
        if (!$product) {
            return $this->cachedItems;
        }

        $sku = $product->getSku();
        $limit = (int)($this->getData('limit') ?: 8);

        try {
            $connection = $this->resource->getConnection();
            $table = $this->resource->getTableName('rexis_network_rules');

            $rules = $connection->fetchAll(
                $connection->select()
                    ->from($table, ['consequent', 'lift', 'confidence', 'support'])
                    ->where('antecedent = ?', $sku)
                    ->where('is_active = 1')
                    ->where('lift >= 1.5')
                    ->order('lift DESC')
                    ->limit($limit + 5) // fetch extra in case some aren't saleable
            );

            foreach ($rules as $rule) {
                if (count($this->cachedItems) >= $limit) {
                    break;
                }

                try {
                    $relatedProduct = $this->productRepository->get($rule['consequent']);
                    if ($relatedProduct->isSaleable()) {
                        $lift = (float)$rule['lift'];
                        $this->cachedItems[] = [
                            'product' => $relatedProduct,
                            'lift' => $lift,
                            'confidence' => (float)$rule['confidence'],
                            'support' => (float)$rule['support'],
                            'badge' => $this->getLiftBadge($lift),
                        ];
                    }
                } catch (\Exception $e) {
                    continue;
                }
            }
        } catch (\Exception $e) {
            $this->logger->error('[RexisML PDP CrossSell] Error: ' . $e->getMessage());
        }

        return $this->cachedItems;
    }

    public function isEnabled(): bool
    {
        return (bool)$this->scopeConfig->getValue(
            'rexisml/general/enabled',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    public function getProductImageUrl($product): string
    {
        try {
            return $this->listProductBlock->getImage($product, 'category_page_grid')->getImageUrl();
        } catch (\Exception $e) {
            return '';
        }
    }

    public function getAddToCartPostParams($product)
    {
        return $this->listProductBlock->getAddToCartPostParams($product);
    }

    /**
     * Format product final price using Magento locale/currency settings.
     */
    public function formatProductPrice($product): string
    {
        try {
            return (string)$this->priceCurrency->convertAndFormat(
                (float)$product->getFinalPrice(),
                false
            );
        } catch (\Exception $e) {
            return '';
        }
    }

    private function getLiftBadge(float $lift): array
    {
        if ($lift >= 3.0) {
            return ['label' => 'Muito Recomendado', 'class' => 'rx-badge-high', 'color' => '#10b981'];
        }
        if ($lift >= 2.0) {
            return ['label' => 'Recomendado', 'class' => 'rx-badge-medium', 'color' => '#3b82f6'];
        }
        return ['label' => 'Sugerido', 'class' => 'rx-badge-low', 'color' => '#94a3b8'];
    }
}
