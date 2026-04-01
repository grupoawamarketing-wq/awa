<?php

declare(strict_types=1);

namespace GrupoAwamotos\Fitment\Model\Source;

use GrupoAwamotos\Fitment\Model\ResourceModel\Brand\CollectionFactory;
use Magento\Framework\Data\OptionSourceInterface;

/**
 * Brand Source Model for dropdown options
 */
class Brand implements OptionSourceInterface
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
            $collection->setDefaultOrder();

            $this->options = [
                ['value' => '', 'label' => __('-- Selecione uma Marca --')],
            ];

            foreach ($collection as $brand) {
                $this->options[] = [
                    'value' => $brand->getBrandId(),
                    'label' => $brand->getName(),
                ];
            }
        }

        return $this->options;
    }
}
