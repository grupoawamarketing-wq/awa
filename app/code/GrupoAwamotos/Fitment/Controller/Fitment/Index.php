<?php

declare(strict_types=1);

namespace GrupoAwamotos\Fitment\Controller\Fitment;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Framework\View\Result\PageFactory;

class Index extends Action implements HttpGetActionInterface
{
    private CollectionFactory $collectionFactory;
    private PageFactory $pageFactory;

    public function __construct(Context $context, CollectionFactory $collectionFactory, PageFactory $pageFactory)
    {
        parent::__construct($context);
        $this->collectionFactory = $collectionFactory;
        $this->pageFactory = $pageFactory;
    }

    public function execute()
    {
        $params = $this->getRequest()->getParams();
        $collection = $this->collectionFactory->create();
        $collection->addAttributeToSelect(['name','price','small_image']);
        $filters = ['marca_moto','modelo_moto','ano_moto'];
        foreach ($filters as $attr) {
            if (!empty($params[$attr])) {
                $collection->addAttributeToFilter($attr, ['like' => '%' . $params[$attr] . '%']);
            }
        }
        $collection->setPageSize(30)->setCurPage(1);
        $this->_view->getLayout()->getBlock('fitment.results')?->setData('fitment_collection', $collection);
        return $this->pageFactory->create();
    }
}
