<?php

declare(strict_types=1);

namespace GrupoAwamotos\SchemaOrg\Plugin;

use GrupoAwamotos\Theme\ViewModel\ProductStructuredData;
use GrupoAwamotos\Fitment\Model\ResourceModel\Application\CollectionFactory;

/**
 * Plugin to inject Fitment (Compatibility) data into the active Product ViewModel Schema.org
 */
class AddFitmentToViewModel
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
     * @param ProductStructuredData $subject
     * @param array $result
     * @return array
     */
    public function afterGetProductStructuredData(ProductStructuredData $subject, array $result): array
    {
        if (empty($result)) {
            return $result;
        }

        $product = $subject->getCurrentProduct();
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
