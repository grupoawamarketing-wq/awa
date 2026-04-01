<?php

declare(strict_types=1);

namespace GrupoAwamotos\Fitment\Controller\Adminhtml\Model;

use GrupoAwamotos\Fitment\Model\ResourceModel\MotorcycleModel\Collection;
use GrupoAwamotos\Fitment\Model\ResourceModel\MotorcycleModel\CollectionFactory;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Ui\Component\MassAction\Filter;

class MassDelete extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'GrupoAwamotos_Fitment::model';

    public function __construct(
        Context $context,
        private readonly Filter $filter,
        private readonly CollectionFactory $collectionFactory
    ) {
        parent::__construct($context);
    }

    public function execute(): \Magento\Framework\Controller\Result\Redirect
    {
        $resultRedirect = $this->resultRedirectFactory->create();

        try {
            /** @var Collection $collection */
            $collection = $this->filter->getCollection($this->collectionFactory->create());
            $count = 0;
            foreach ($collection as $item) {
                $item->delete();
                $count++;
            }
            $this->messageManager->addSuccessMessage(__('%1 modelo(s) excluído(s) com sucesso.', $count));
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        }

        return $resultRedirect->setPath('*/*/index');
    }
}
