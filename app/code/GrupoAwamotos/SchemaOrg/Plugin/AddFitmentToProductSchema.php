<?php

declare(strict_types=1);

namespace GrupoAwamotos\SchemaOrg\Plugin;

use GrupoAwamotos\SchemaOrg\Block\ProductSchema;
use GrupoAwamotos\Fitment\Model\ResourceModel\Application\CollectionFactory;
use Magento\Framework\App\RequestInterface;

/**
 * Plugin to inject Fitment (Compatibility) data into Product Schema.org
 */
class AddFitmentToProductSchema
{
    /**
     * @param CollectionFactory $fitmentCollectionFactory
     */
    public function __construct(
        private readonly CollectionFactory $fitmentCollectionFactory
    ) {
    }

    /**
     * Add compatibility data to schema
     *
     * @param ProductSchema $subject
     * @param array $result
     * @return array
     */
    public function afterGetProductSchemaData(ProductSchema $subject, array $result): array
    {
        if (empty($result)) {
            return $result;
        }

        $product = $subject->getProduct();
        if (!$product || !$product->getId()) {
            return $result;
        }

        $applications = $this->getApplications((int) $product->getId());
        if (empty($applications)) {
            return $result;
        }

        // Add compatibility as additionalProperty
        $result['additionalProperty'] = $result['additionalProperty'] ?? [];
        
        foreach ($applications as $brand) {
            $brandName = $brand['brand_name'] ?? 'Universal';
            foreach ($brand['models'] as $model) {
                $label = $model['model_name'];
                if (!empty($model['years'])) {
                    $label .= " ({$model['years']})";
                }
                
                $result['additionalProperty'][] = [
                    '@type' => 'PropertyValue',
                    'name' => 'Compatible Model',
                    'value' => "{$brandName} {$label}"
                ];
            }
        }

        // If it's a spare part (which most AWA products are), we can use isAccessoryOrSparePartFor
        // But Schema.org usually expects a Product object here. 
        // For now, additionalProperty is safer for Google Search.

        return $result;
    }

    /**
     * Get grouped applications
     *
     * @param int $productId
     * @return array
     */
    private function getApplications(int $productId): array
    {
        try {
            $collection = $this->fitmentCollectionFactory->create();
            $collection->addProductFilter($productId);
            return $collection->getGroupedByBrand();
        } catch (\Exception $e) {
            return [];
        }
    }
}
