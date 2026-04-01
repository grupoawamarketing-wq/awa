<?php

declare(strict_types=1);

namespace GrupoAwamotos\Fitment\Controller\Ajax;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;

class Years extends Action implements HttpGetActionInterface
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
        $modelo = trim((string)$this->getRequest()->getParam('modelo'));
        $result = $this->resultJsonFactory->create();

        if ($marca === '' || $modelo === '') {
            return $result->setData(['success' => false, 'items' => [], 'message' => __('Marca ou modelo não informado.')]);
        }

        $collection = $this->productCollectionFactory->create();
        $collection->addAttributeToSelect('ano_moto')
            ->addAttributeToFilter('marca_moto', $marca)
            ->addAttributeToFilter('modelo_moto', $modelo)
            ->addAttributeToFilter('ano_moto', ['notnull' => true]);

        $anos = [];
        foreach ($collection as $product) {
            $val = $product->getData('ano_moto');
            if ($val !== null && $val !== '' && !in_array($val, $anos, true)) {
                $anos[] = $val;
            }
        }
        sort($anos, SORT_NATURAL | SORT_FLAG_CASE);

        return $result->setData(['success' => true, 'items' => $anos]);
    }
}
