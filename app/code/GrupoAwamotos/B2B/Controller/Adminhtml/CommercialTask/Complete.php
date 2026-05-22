<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Controller\Adminhtml\CommercialTask;

use GrupoAwamotos\B2B\CommercialPanel\Api\CommercialTaskManagementInterface;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\LocalizedException;

class Complete extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'GrupoAwamotos_B2B::commercial_tasks_manage';

    public function __construct(
        Context $context,
        private readonly CommercialTaskManagementInterface $taskManagement
    ) {
        parent::__construct($context);
    }

    public function execute(): ResultInterface
    {
        $redirect = $this->resultRedirectFactory->create();
        $taskId = (int) $this->getRequest()->getPostValue('task_id');

        try {
            if (!$this->_formKeyValidator->validate($this->getRequest())) {
                throw new LocalizedException(__('Formulário inválido.'));
            }

            $user = $this->_auth->getUser();
            if (!$user || !$user->getId()) {
                throw new LocalizedException(__('Sessão expirada.'));
            }

            $this->taskManagement->complete($taskId, (int) $user->getId());
            $this->messageManager->addSuccessMessage(__('Tarefa concluída.'));
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        } catch (\Exception) {
            $this->messageManager->addErrorMessage(__('Não foi possível concluir a tarefa.'));
        }

        return $redirect->setPath('awa_commercial/commercialtask/index');
    }
}
