<?php

declare(strict_types=1);

namespace GrupoAwamotos\SocialProof\Block;

use Magento\Framework\View\Element\Template;
use Magento\Framework\Registry;

class ProductInfo extends Template
{
    /**
     * @var Registry
     */
    protected $registry;

    /**
     * @param Template\Context $context
     * @param Registry $registry
     * @param array $data
     */
    public function __construct(
        Template\Context $context,
        Registry $registry,
        array $data = []
    ) {
        $this->registry = $registry;
        parent::__construct($context, $data);
    }

    /**
     * Retorna o produto atual
     *
     * @return \Magento\Catalog\Model\Product|null
     */
    public function getProduct()
    {
        return $this->registry->registry('current_product');
    }

    /**
     * Retorna número de visualizações hoje
     *
     * @return int
     */
    public function getViewsToday()
    {
        $product = $this->getProduct();
        return $product ? (int)$product->getData('views_today') : 0;
    }

    /**
     * Verifica se produto é mais vendido
     *
     * @return bool
     */
    public function isBestSeller()
    {
        $product = $this->getProduct();
        return $product ? (bool)$product->getData('is_best_seller') : false;
    }

    /**
     * Verifica se estoque está baixo (urgência)
     *
     * @return bool
     */
    public function isLowStock()
    {
        $product = $this->getProduct();
        if (!$product) {
            return false;
        }

        $extensionAttributes = $product->getExtensionAttributes();
        $stockItem = $extensionAttributes ? $extensionAttributes->getStockItem() : null;
        if (!$stockItem) {
            return false;
        }

        $qty = $stockItem->getQty();
        return $qty > 0 && $qty < 10;
    }

    /**
     * Retorna quantidade em estoque
     *
     * @return int
     */
    public function getStockQty()
    {
        $product = $this->getProduct();
        if (!$product) {
            return 0;
        }

        $extensionAttributes = $product->getExtensionAttributes();
        $stockItem = $extensionAttributes ? $extensionAttributes->getStockItem() : null;
        return $stockItem ? (int)$stockItem->getQty() : 0;
    }
}
