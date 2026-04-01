<?php

/**
 * B2B Shopping List Item Model
 */

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Model;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Model\Context;
use Magento\Framework\Registry;

class ShoppingListItem extends AbstractModel
{
    /**
     * @var ProductRepositoryInterface
     */
    private $productRepository;

    public function __construct(
        Context $context,
        Registry $registry,
        ProductRepositoryInterface $productRepository,
        array $data = []
    ) {
        $this->productRepository = $productRepository;
        parent::__construct($context, $registry, null, null, $data);
    }

    /**
     * @inheritDoc
     */
    protected function _construct()
    {
        $this->_init(\GrupoAwamotos\B2B\Model\ResourceModel\ShoppingListItem::class);
    }

    /**
     * Get product
     *
     * @return \Magento\Catalog\Api\Data\ProductInterface|null
     */
    public function getProduct()
    {
        if (!$this->getProductId()) {
            return null;
        }

        try {
            return $this->productRepository->getById($this->getProductId());
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get line total
     *
     * @return float
     */
    public function getLineTotal(): float
    {
        $product = $this->getProduct();
        if (!$product) {
            return 0;
        }
        return (float)$product->getFinalPrice() * (float)$this->getQty();
    }
}
