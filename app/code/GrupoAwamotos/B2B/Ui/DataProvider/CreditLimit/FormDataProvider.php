<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Ui\DataProvider\CreditLimit;

use GrupoAwamotos\B2B\Model\ResourceModel\CreditLimit\CollectionFactory;
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
            $rowData = $item->getData();
            if (isset($rowData['payment_terms']) && is_array($rowData['payment_terms'])) {
                $rowData['payment_terms'] = implode(',', $rowData['payment_terms']);
            }
            $result[$item->getId()] = $rowData;
        }

        return $result;
    }
}
