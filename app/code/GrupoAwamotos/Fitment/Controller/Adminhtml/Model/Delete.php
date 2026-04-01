<?php

declare(strict_types=1);

namespace GrupoAwamotos\Fitment\Controller\Adminhtml\Model;

use GrupoAwamotos\Fitment\Model\MotorcycleModelFactory;
use GrupoAwamotos\Fitment\Model\ResourceModel\MotorcycleModel as ModelResource;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;

class Delete extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'GrupoAwamotos_Fitment::model';

    public function __construct(
        Context $context,
        private readonly MotorcycleModelFactory $modelFactory,
        private readonly ModelResource $modelResource
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

        $model = $this->modelFactory->create();
        $this->modelResource->load($model, $id);

        if (!$model->getId()) {
            $this->messageManager->addErrorMessage(__('Este modelo não existe mais.'));
            return $resultRedirect->setPath('*/*/');
        }

        try {
            $this->modelResource->delete($model);
            $this->messageManager->addSuccessMessage(__('Modelo excluído com sucesso.'));
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        }

        return $resultRedirect->setPath('*/*/');
    }
}
