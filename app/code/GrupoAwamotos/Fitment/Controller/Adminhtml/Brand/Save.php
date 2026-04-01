<?php

declare(strict_types=1);

namespace GrupoAwamotos\Fitment\Controller\Adminhtml\Brand;

use GrupoAwamotos\Fitment\Model\BrandFactory;
use GrupoAwamotos\Fitment\Model\ResourceModel\Brand as BrandResource;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Exception\LocalizedException;

class Save extends Action implements HttpPostActionInterface
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
        $data = $this->getRequest()->getPostValue();

        if (!$data) {
            return $resultRedirect->setPath('*/*/');
        }

        $id = isset($data['brand_id']) ? (int) $data['brand_id'] : 0;
        $model = $this->brandFactory->create();

        if ($id) {
            $this->brandResource->load($model, $id);
            if (!$model->getId()) {
                $this->messageManager->addErrorMessage(__('Esta marca não existe mais.'));
                return $resultRedirect->setPath('*/*/');
            }
        }

        $model->setData($data);

        try {
            $this->brandResource->save($model);
            $this->messageManager->addSuccessMessage(__('Marca salva com sucesso.'));

            if ($this->getRequest()->getParam('back')) {
                return $resultRedirect->setPath('*/*/edit', ['id' => $model->getId()]);
            }
            return $resultRedirect->setPath('*/*/');
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        } catch (\Exception $e) {
            $this->messageManager->addExceptionMessage($e, __('Erro ao salvar a marca.'));
        }

        return $resultRedirect->setPath('*/*/edit', ['id' => $id, '_current' => true]);
    }
}
