<?php

declare(strict_types=1);

namespace GrupoAwamotos\Fitment\Controller\Adminhtml\Model;

use GrupoAwamotos\Fitment\Model\MotorcycleModelFactory;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Registry;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;

class Edit extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'GrupoAwamotos_Fitment::model';

    public function __construct(
        Context $context,
        private readonly MotorcycleModelFactory $modelFactory,
        private readonly Registry $coreRegistry,
        private readonly PageFactory $resultPageFactory
    ) {
        parent::__construct($context);
    }

    public function execute(): Page
    {
        $id = (int) $this->getRequest()->getParam('id');
        $model = $this->modelFactory->create();

        if ($id) {
            $model->load($id);
            if (!$model->getId()) {
                $this->messageManager->addErrorMessage(__('Este modelo não existe mais.'));
                $resultRedirect = $this->resultRedirectFactory->create();
                return $resultRedirect->setPath('*/*/');
            }
        }

        $this->coreRegistry->register('fitment_model', $model);

        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('GrupoAwamotos_Fitment::model');
        $title = $model->getId() ? __('Editar Modelo: %1', $model->getName()) : __('Novo Modelo');
        $resultPage->getConfig()->getTitle()->prepend($title);
        return $resultPage;
    }
}
