<?php

declare(strict_types=1);

namespace GrupoAwamotos\Fitment\Block;

use Magento\Framework\View\Element\Template;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;

class Form extends Template
{
    private CollectionFactory $collectionFactory;
    public function __construct(Template\Context $context, CollectionFactory $collectionFactory, array $data = [])
    {
        parent::__construct($context, $data);
        $this->collectionFactory = $collectionFactory;
    }
    public function getDistinctValues(string $attribute, int $limit = 200): array
    {
        $collection = $this->collectionFactory->create();
        $collection->addAttributeToSelect($attribute)->addAttributeToFilter($attribute, ['notnull' => true])->setPageSize($limit);
        $values = [];
        foreach ($collection as $p) {
            $v = trim((string)$p->getData($attribute));
            if ($v !== '' && !in_array($v, $values, true)) {
                $values[] = $v;
            }
        }
        sort($values);
        return $values;
    }
}
