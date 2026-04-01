<?php

declare(strict_types=1);

namespace GrupoAwamotos\Fitment\Model\Brand;

use GrupoAwamotos\Fitment\Model\ResourceModel\Brand\CollectionFactory;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Ui\DataProvider\AbstractDataProvider;

/**
 * Brand Form DataProvider
 */
class DataProvider extends AbstractDataProvider
{
    /** @var array<int, array<string, mixed>> */
    private array $loadedData = [];

    public function __construct(
        string $name,
        string $primaryFieldName,
        string $requestFieldName,
        CollectionFactory $collectionFactory,
        private readonly DataPersistorInterface $dataPersistor,
        array $meta = [],
        array $data = []
    ) {
        $this->collection = $collectionFactory->create();
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getData(): array
    {
        if (!empty($this->loadedData)) {
            return $this->loadedData;
        }

        $items = $this->collection->getItems();
        foreach ($items as $item) {
            $this->loadedData[(int) $item->getId()] = $item->getData();
        }

        $data = $this->dataPersistor->get('fitment_brand');
        if (!empty($data)) {
            $item = $this->collection->getNewEmptyItem();
            $item->setData($data);
            $this->loadedData[(int) $item->getId()] = $item->getData();
            $this->dataPersistor->clear('fitment_brand');
        }

        return $this->loadedData;
    }
}
