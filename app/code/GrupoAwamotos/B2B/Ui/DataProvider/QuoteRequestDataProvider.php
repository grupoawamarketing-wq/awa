<?php

/**
 * Quote Request DataProvider
 */

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Ui\DataProvider;

use GrupoAwamotos\B2B\Model\ResourceModel\QuoteRequest\CollectionFactory;
use Magento\Ui\DataProvider\AbstractDataProvider;

class QuoteRequestDataProvider extends AbstractDataProvider
{
    /**
     * @var array
     */
    protected $loadedData;

    public function __construct(
        $name,
        $primaryFieldName,
        $requestFieldName,
        CollectionFactory $collectionFactory,
        array $meta = [],
        array $data = []
    ) {
        $this->collection = $collectionFactory->create();
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
    }

    /**
     * Get data
     *
     * @return array
     */
    public function getData()
    {
        if (isset($this->loadedData)) {
            return $this->loadedData;
        }

        $items = $this->collection->getItems();
        $this->loadedData = [];

        foreach ($items as $item) {
            $this->loadedData[$item->getId()] = $item->getData();
        }

        return $this->loadedData;
    }
}
