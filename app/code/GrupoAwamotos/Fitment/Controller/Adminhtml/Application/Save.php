<?php

declare(strict_types=1);

namespace GrupoAwamotos\Fitment\Controller\Adminhtml\Application;

use GrupoAwamotos\Fitment\Model\ApplicationFactory;
use GrupoAwamotos\Fitment\Model\ResourceModel\Application as ApplicationResource;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Exception\LocalizedException;

class Save extends Action implements HttpPostActionInterface
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
        $data = $this->getRequest()->getPostValue();

        if (!$data) {
            return $resultRedirect->setPath('*/*/');
        }

        $id = isset($data['application_id']) ? (int) $data['application_id'] : 0;
        $model = $this->applicationFactory->create();

        if ($id) {
            $this->applicationResource->load($model, $id);
            if (!$model->getId()) {
                $this->messageManager->addErrorMessage(__('Esta aplicação não existe mais.'));
                return $resultRedirect->setPath('*/*/');
            }
        }

        $model->setData($data);

        try {
            $this->applicationResource->save($model);
            $this->messageManager->addSuccessMessage(__('Aplicação salva com sucesso.'));

            if ($this->getRequest()->getParam('back')) {
                return $resultRedirect->setPath('*/*/edit', ['id' => $model->getId()]);
            }
            return $resultRedirect->setPath('*/*/');
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        } catch (\Exception $e) {
            $this->messageManager->addExceptionMessage($e, __('Erro ao salvar a aplicação.'));
        }

        return $resultRedirect->setPath('*/*/edit', ['id' => $id, '_current' => true]);
    }
}
