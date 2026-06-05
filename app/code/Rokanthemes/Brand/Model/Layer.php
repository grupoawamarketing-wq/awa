<?php
namespace Rokanthemes\Brand\Model;

use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Model\ResourceModel\Product\Attribute\CollectionFactory as AttributeCollectionFactory;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;

class Layer extends \Magento\Catalog\Model\Layer
{
    public function __construct(
        \Magento\Catalog\Model\Layer\ContextInterface $context,
        \Magento\Catalog\Model\Layer\StateFactory $layerStateFactory,
        AttributeCollectionFactory $attributeCollectionFactory,
        \Magento\Catalog\Model\ResourceModel\Product $catalogProduct,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\Registry $registry,
        CategoryRepositoryInterface $categoryRepository,
        CollectionFactory $productCollectionFactory,
		\Magento\Catalog\Model\Product\Visibility $catalogProductVisibility,
        array $data = []
    ) {
        $this->productCollectionFactory = $productCollectionFactory;
		$this->_coreRegistry = $registry;
		$this->_catalogProductVisibility = $catalogProductVisibility;
        parent::__construct(
            $context,
            $layerStateFactory,
            $attributeCollectionFactory,
            $catalogProduct,
            $storeManager,
            $registry,
            $categoryRepository,
            $data
        );
    }

	public function getProductCollection()
	{
		$brand = $this->_coreRegistry->registry('current_brand');
        if (!$brand) {
            return parent::getProductCollection();
        }
        if (isset($this->_productCollections['current_brand'])) {
            $collection = $this->_productCollections['current_brand'];
			$products = $brand->getData('products');
            $productIds = [];
            foreach ($products as $k => $v) {
                $productIds[] = $v['product_id'];
            }
            $collection->setVisibility($this->_catalogProductVisibility->getVisibleInCatalogIds())->addAttributeToSelect('*')->addAttributeToFilter('entity_id',['in'=>$productIds]);
        } else {
            $collection = $this->productCollectionFactory->create();
            $this->prepareProductCollection($collection);
            $this->_productCollections['current_brand'] = $collection;
			$products = $brand->getData('products');
            $productIds = [];
            foreach ($products as $k => $v) {
                $productIds[] = $v['product_id'];
            }
            $collection->setVisibility($this->_catalogProductVisibility->getVisibleInCatalogIds())->addAttributeToSelect('*')->addAttributeToFilter('entity_id',['in'=>$productIds]);
        }
        return $collection;
	}
	
	public function getCurrentBrand()
    {
        $brand = $this->_coreRegistry->registry('current_brand');
        if ($brand) {
            $this->setData('current_brand', $brand);
        }
        return $brand;
    }

}