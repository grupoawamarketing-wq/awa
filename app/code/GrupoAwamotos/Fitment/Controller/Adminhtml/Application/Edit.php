<?php

declare(strict_types=1);

namespace GrupoAwamotos\Fitment\Controller\Adminhtml\Application;

use GrupoAwamotos\Fitment\Model\ApplicationFactory;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Registry;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;

class Edit extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'GrupoAwamotos_Fitment::application';

    public function __construct(
        Context $context,
        private readonly ApplicationFactory $applicationFactory,
        private readonly Registry $coreRegistry,
        private readonly PageFactory $resultPageFactory
    ) {
        parent::__construct($context);
    }

    public function execute(): Page
    {
        $id = (int) $this->getRequest()->getParam('id');
        $model = $this->applicationFactory->create();

        if ($id) {
            $model->load($id);
            if (!$model->getId()) {
                $this->messageManager->addErrorMessage(__('Esta aplicação não existe mais.'));
                $resultRedirect = $this->resultRedirectFactory->create();
                return $resultRedirect->setPath('*/*/');
            }
        }

        $this->coreRegistry->register('fitment_application', $model);

        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('GrupoAwamotos_Fitment::application');
        $title = $model->getId() ? __('Editar Aplicação #%1', $model->getId()) : __('Nova Aplicação');
        $resultPage->getConfig()->getTitle()->prepend($title);
        return $resultPage;
    }
}
