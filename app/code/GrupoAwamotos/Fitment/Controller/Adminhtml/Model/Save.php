<?php

declare(strict_types=1);

namespace GrupoAwamotos\Fitment\Controller\Adminhtml\Model;

use GrupoAwamotos\Fitment\Model\MotorcycleModelFactory;
use GrupoAwamotos\Fitment\Model\ResourceModel\MotorcycleModel as ModelResource;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Exception\LocalizedException;

class Save extends Action implements HttpPostActionInterface
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
        $data = $this->getRequest()->getPostValue();

        if (!$data) {
            return $resultRedirect->setPath('*/*/');
        }

        $id = isset($data['model_id']) ? (int) $data['model_id'] : 0;
        $model = $this->modelFactory->create();

        if ($id) {
            $this->modelResource->load($model, $id);
            if (!$model->getId()) {
                $this->messageManager->addErrorMessage(__('Este modelo não existe mais.'));
                return $resultRedirect->setPath('*/*/');
            }
        }

        $model->setData($data);

        try {
            $this->modelResource->save($model);
            $this->messageManager->addSuccessMessage(__('Modelo salvo com sucesso.'));

            if ($this->getRequest()->getParam('back')) {
                return $resultRedirect->setPath('*/*/edit', ['id' => $model->getId()]);
            }
            return $resultRedirect->setPath('*/*/');
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        } catch (\Exception $e) {
            $this->messageManager->addExceptionMessage($e, __('Erro ao salvar o modelo.'));
        }

        return $resultRedirect->setPath('*/*/edit', ['id' => $id, '_current' => true]);
    }
}
