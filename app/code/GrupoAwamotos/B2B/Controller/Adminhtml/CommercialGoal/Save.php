<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Controller\Adminhtml\CommercialGoal;

use GrupoAwamotos\B2B\CommercialPanel\Api\CommercialGoalManagementInterface;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\ResultInterface;

class Save extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'GrupoAwamotos_B2B::commercial_goals_manage';

    public function __construct(
        Context $context,
        private readonly CommercialGoalManagementInterface $goalManagement
    ) {
        parent::__construct($context);
    }

    public function execute(): ResultInterface
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        $resultRedirect->setPath('awa_commercial/commercialgoal/index');

        if (!$this->getRequest()->isPost()) {
            return $resultRedirect;
        }

        $data = (array) $this->getRequest()->getPostValue();
        if ($data === []) {
            $this->messageManager->addErrorMessage(__('Dados inválidos.'));

            return $resultRedirect;
        }

        try {
            $this->goalManagement->saveGoal($data);
            $this->messageManager->addSuccessMessage(__('Meta comercial salva com sucesso.'));
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        } catch (\Throwable) {
            $this->messageManager->addErrorMessage(__('Não foi possível salvar a meta.'));
        }

        return $resultRedirect;
    }
}
