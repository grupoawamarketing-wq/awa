<?php

declare(strict_types=1);

namespace GrupoAwamotos\Fitment\Model\Source;

use GrupoAwamotos\Fitment\Model\ResourceModel\MotorcycleModel\CollectionFactory;
use Magento\Framework\Data\OptionSourceInterface;

/**
 * MotorcycleModel Source Model for dropdown options
 */
class MotorcycleModel implements OptionSourceInterface
{
    private CollectionFactory $collectionFactory;
    private ?array $options = null;

    public function __construct(
        CollectionFactory $collectionFactory
    ) {
        $this->collectionFactory = $collectionFactory;
    }

    /**
     * @inheritdoc
     */
    public function toOptionArray(): array
    {
        if ($this->options === null) {
            $collection = $this->collectionFactory->create();
            $collection->addActiveFilter();
            $collection->joinBrand();
            $collection->setDefaultOrder();

            $this->options = [
                ['value' => '', 'label' => __('-- Selecione um Modelo --')],
            ];

            $grouped = [];
            foreach ($collection as $model) {
                $brandName = $model->getData('brand_name') ?? 'Sem Marca';
                if (!isset($grouped[$brandName])) {
                    $grouped[$brandName] = [];
                }
                $grouped[$brandName][] = [
                    'value' => $model->getModelId(),
                    'label' => $model->getName() . ' ' . $model->getFormattedYears(),
                ];
            }

            foreach ($grouped as $brandName => $models) {
                $this->options[] = [
                    'label' => $brandName,
                    'value' => $models,
                ];
            }
        }

        return $this->options;
    }
}
