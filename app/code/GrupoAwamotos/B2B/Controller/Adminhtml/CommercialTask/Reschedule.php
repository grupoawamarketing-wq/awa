<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Controller\Adminhtml\CommercialTask;

use GrupoAwamotos\B2B\CommercialPanel\Api\CommercialTaskManagementInterface;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\View\Result\PageFactory;

class Reschedule extends Action implements HttpGetActionInterface, HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'GrupoAwamotos_B2B::commercial_tasks_manage';

    public function __construct(
        Context $context,
        private readonly PageFactory $resultPageFactory,
        private readonly CommercialTaskManagementInterface $taskManagement
    ) {
        parent::__construct($context);
    }

    public function execute(): ResultInterface
    {
        $taskId = (int) $this->getRequest()->getParam('task_id');
        if ($taskId <= 0) {
            $this->messageManager->addErrorMessage(__('Tarefa inválida.'));
            return $this->resultRedirectFactory->create()->setPath('awa_commercial/commercialtask/index');
        }

        if ($this->getRequest()->isPost()) {
            return $this->processPost($taskId);
        }

        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('GrupoAwamotos_B2B::commercial_tasks');
        $resultPage->getConfig()->getTitle()->prepend(__('Reagendar Tarefa'));

        return $resultPage;
    }

    private function processPost(int $taskId): ResultInterface
    {
        $redirect = $this->resultRedirectFactory->create();

        try {
            if (!$this->_formKeyValidator->validate($this->getRequest())) {
                throw new LocalizedException(__('Formulário inválido.'));
            }

            $user = $this->_auth->getUser();
            if (!$user || !$user->getId()) {
                throw new LocalizedException(__('Sessão expirada.'));
            }

            $dueAt = (string) $this->getRequest()->getPostValue('due_at');
            $this->taskManagement->reschedule($taskId, $dueAt, (int) $user->getId());
            $this->messageManager->addSuccessMessage(__('Tarefa reagendada com sucesso.'));
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
            return $redirect->setPath('*/*/reschedule', ['task_id' => $taskId]);
        } catch (\Exception) {
            $this->messageManager->addErrorMessage(__('Não foi possível reagendar a tarefa.'));
            return $redirect->setPath('*/*/reschedule', ['task_id' => $taskId]);
        }

        return $redirect->setPath('awa_commercial/commercialtask/index');
    }
}
