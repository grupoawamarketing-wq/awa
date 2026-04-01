<?php

/**
 * Source model: opções de transportadoras ativas (CarrierSelect)
 */

declare(strict_types=1);

namespace GrupoAwamotos\CarrierSelect\Model\Customer\Attribute\Source;

use GrupoAwamotos\CarrierSelect\Model\ResourceModel\Carrier\CollectionFactory;
use Magento\Eav\Model\Entity\Attribute\Source\AbstractSource;

class CarrierOptions extends AbstractSource
{
    private CollectionFactory $carrierCollectionFactory;

    public function __construct(CollectionFactory $carrierCollectionFactory)
    {
        $this->carrierCollectionFactory = $carrierCollectionFactory;
    }

    public function getAllOptions(): array
    {
        if ($this->_options !== null) {
            return $this->_options;
        }

        $options = [
            ['label' => __('Sem transportadora definida'), 'value' => ''],
        ];

        $carriers = $this->carrierCollectionFactory->create()
            ->addActiveFilter()
            ->addSortOrder();

        foreach ($carriers as $carrier) {
            $options[] = [
                'label' => (string)$carrier->getName(),
                'value' => (string)$carrier->getCode(),
            ];
        }

        $this->_options = $options;
        return $this->_options;
    }
}
