<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Ui\DataProvider\Company;

use GrupoAwamotos\B2B\Model\ResourceModel\Company\CollectionFactory;
use Magento\Framework\App\RequestInterface;
use Magento\Ui\DataProvider\AbstractDataProvider;

class FormDataProvider extends AbstractDataProvider
{
    public function __construct(
        string $name,
        string $primaryFieldName,
        string $requestFieldName,
        CollectionFactory $collectionFactory,
        private readonly RequestInterface $request,
        array $meta = [],
        array $data = []
    ) {
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
        $this->collection = $collectionFactory->create();
    }

    /**
     * @return array<int|string, mixed>
     */
    public function getData(): array
    {
        $entityId = (int) $this->request->getParam($this->requestFieldName);
        if (!$entityId) {
            return [];
        }

        $this->collection->addFieldToFilter($this->primaryFieldName, ['eq' => $entityId]);

        $result = [];
        foreach ($this->collection->getItems() as $item) {
            $result[$item->getId()] = $item->getData();
        }

        return $result;
    }
}
