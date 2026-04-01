<?php

/**
 * B2B Shopping List Model
 */

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Model;

use GrupoAwamotos\B2B\Model\ResourceModel\ShoppingListItem\CollectionFactory as ItemCollectionFactory;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Model\Context;
use Magento\Framework\Registry;

class ShoppingList extends AbstractModel
{
    /**
     * @var ItemCollectionFactory
     */
    private $itemCollectionFactory;

    public function __construct(
        Context $context,
        Registry $registry,
        ItemCollectionFactory $itemCollectionFactory,
        ?\Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        ?\Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        $this->itemCollectionFactory = $itemCollectionFactory;
        parent::__construct($context, $registry, $resource, $resourceCollection, $data);
    }

    /**
     * @inheritDoc
     */
    protected function _construct()
    {
        $this->_init(\GrupoAwamotos\B2B\Model\ResourceModel\ShoppingList::class);
    }

    /**
     * Get items collection
     *
     * @return \GrupoAwamotos\B2B\Model\ResourceModel\ShoppingListItem\Collection
     */
    public function getItemsCollection()
    {
        $collection = $this->itemCollectionFactory->create();
        $collection->addFieldToFilter('list_id', $this->getId());
        return $collection;
    }

    /**
     * Get items count
     *
     * @return int
     */
    public function getItemsCount(): int
    {
        return $this->getItemsCollection()->getSize();
    }
}
