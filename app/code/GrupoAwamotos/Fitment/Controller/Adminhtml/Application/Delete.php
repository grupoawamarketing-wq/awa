<?php

declare(strict_types=1);

namespace GrupoAwamotos\Fitment\Controller\Adminhtml\Application;

use GrupoAwamotos\Fitment\Model\ApplicationFactory;
use GrupoAwamotos\Fitment\Model\ResourceModel\Application as ApplicationResource;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;

class Delete extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'GrupoAwamotos_Fitment::application';

    public function __construct(
        Context $context,
        private readonly ApplicationFactory $applicationFactory,
        private readonly ApplicationResource $applicationResource
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

        $model = $this->applicationFactory->create();
        $this->applicationResource->load($model, $id);

        if (!$model->getId()) {
            $this->messageManager->addErrorMessage(__('Esta aplicação não existe mais.'));
            return $resultRedirect->setPath('*/*/');
        }

        try {
            $this->applicationResource->delete($model);
            $this->messageManager->addSuccessMessage(__('Aplicação excluída com sucesso.'));
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        }

        return $resultRedirect->setPath('*/*/');
    }
}
