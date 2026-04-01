<?php

declare(strict_types=1);

namespace GrupoAwamotos\Fitment\Controller\Ajax;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;

class Models extends Action implements HttpGetActionInterface
{
    protected CollectionFactory $productCollectionFactory;
    protected JsonFactory $resultJsonFactory;

    public function __construct(
        Context $context,
        CollectionFactory $productCollectionFactory,
        JsonFactory $resultJsonFactory
    ) {
        parent::__construct($context);
        $this->productCollectionFactory = $productCollectionFactory;
        $this->resultJsonFactory = $resultJsonFactory;
    }

    public function execute()
    {
        $marca = trim((string)$this->getRequest()->getParam('marca'));
        $result = $this->resultJsonFactory->create();

        if ($marca === '') {
            return $result->setData(['success' => false, 'items' => [], 'message' => __('Marca não informada.')]);
        }

        $collection = $this->productCollectionFactory->create();
        $collection->addAttributeToSelect('modelo_moto')
            ->addAttributeToFilter('marca_moto', $marca)
            ->addAttributeToFilter('modelo_moto', ['notnull' => true]);

        $modelos = [];
        foreach ($collection as $product) {
            $val = $product->getData('modelo_moto');
            if ($val !== null && $val !== '' && !in_array($val, $modelos, true)) {
                $modelos[] = $val;
            }
        }
        sort($modelos, SORT_NATURAL | SORT_FLAG_CASE);

        return $result->setData(['success' => true, 'items' => $modelos]);
    }
}
