<?php

/**
 * Customer Pending Data Provider
 */

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Ui\DataProvider;

use GrupoAwamotos\B2B\Model\Customer\Attribute\Source\ApprovalStatus;
use Magento\Customer\Model\ResourceModel\Customer\CollectionFactory;
use Magento\Ui\DataProvider\AbstractDataProvider;

class CustomerPendingDataProvider extends AbstractDataProvider
{
    /**
     * @var CollectionFactory
     */
    private $collectionFactory;

    public function __construct(
        $name,
        $primaryFieldName,
        $requestFieldName,
        CollectionFactory $collectionFactory,
        array $meta = [],
        array $data = []
    ) {
        $this->collectionFactory = $collectionFactory;
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
    }

    /**
     * Get collection
     *
     * @return \Magento\Customer\Model\ResourceModel\Customer\Collection
     */
    public function getCollection()
    {
        if (!$this->collection) {
            $this->collection = $this->collectionFactory->create();
            $this->collection->addAttributeToSelect([
                'firstname',
                'lastname',
                'email',
                'b2b_approval_status',
                'b2b_cnpj',
                'b2b_razao_social',
                'b2b_phone',
                'b2b_person_type',
                'created_at',
            ]);
            $this->collection->addAttributeToFilter(
                'b2b_approval_status',
                ['eq' => ApprovalStatus::STATUS_PENDING]
            );
        }

        return $this->collection;
    }

    /**
     * Get data
     *
     * @return array
     */
    public function getData()
    {
        if (!$this->getCollection()->isLoaded()) {
            $this->getCollection()->load();
        }

        $items = $this->getCollection()->toArray();

        return [
            'totalRecords' => $this->getCollection()->getSize(),
            'items' => array_values($items),
        ];
    }
}
