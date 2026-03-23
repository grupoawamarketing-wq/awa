<?php
declare(strict_types=1);

namespace GrupoAwamotos\CatalogFix\Block\Categorytab;

use Magento\Catalog\Block\Product\AbstractProduct;
use Magento\Catalog\Block\Product\Context;
use Magento\Catalog\Model\CategoryFactory;
use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;

class AjaxProducts extends AbstractProduct
{
    public function __construct(
        Context $context,
        private readonly CategoryFactory $categoryFactory,
        private readonly CollectionFactory $productCollectionFactory,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * @return Collection
     */
    public function getProducts(): Collection
    {
        $categoryId = (int) $this->getData('category_id');
        $limit = (int) ($this->getData('limit') ?: 8);

        $category = $this->categoryFactory->create()->load($categoryId);

        return $this->productCollectionFactory->create()
            ->addAttributeToSelect('*')
            ->addCategoryFilter($category)
            ->setPageSize($limit);
    }
}
