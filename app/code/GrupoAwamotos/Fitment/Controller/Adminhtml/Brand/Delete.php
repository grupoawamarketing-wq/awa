<?php

declare(strict_types=1);

namespace GrupoAwamotos\Fitment\Controller\Adminhtml\Brand;

use GrupoAwamotos\Fitment\Model\BrandFactory;
use GrupoAwamotos\Fitment\Model\ResourceModel\Brand as BrandResource;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;

class Delete extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'GrupoAwamotos_Fitment::brand';

    public function __construct(
        Context $context,
        private readonly BrandFactory $brandFactory,
        private readonly BrandResource $brandResource
    ) {
        parent::__construct($context);
    }

    public function execute(): \Magento\Framework\Controller\Result\Redirect
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        $id = (int) $this->getRequest()->getParam('id');

        if (!$id) {
            $this->messageManager->addErrorMessage(__('ID inválido.'));
            return $resultRedirect->setPath('*/*/');
        }

        $model = $this->brandFactory->create();
        $this->brandResource->load($model, $id);

        if (!$model->getId()) {
            $this->messageManager->addErrorMessage(__('Esta marca não existe mais.'));
            return $resultRedirect->setPath('*/*/');
        }

        try {
            $this->brandResource->delete($model);
            $this->messageManager->addSuccessMessage(__('Marca excluída com sucesso.'));
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        }

        return $resultRedirect->setPath('*/*/');
    }
}
