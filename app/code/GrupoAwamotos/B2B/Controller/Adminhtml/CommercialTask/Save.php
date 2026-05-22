<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Controller\Adminhtml\CommercialTask;

use GrupoAwamotos\B2B\CommercialPanel\Api\CommercialTaskManagementInterface;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\LocalizedException;

class Save extends Action implements HttpPostActionInterface
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
        $customerId = (int) $this->getRequest()->getPostValue('customer_id');

        try {
            if (!$this->_formKeyValidator->validate($this->getRequest())) {
                throw new LocalizedException(__('Formulário inválido.'));
            }

            $user = $this->_auth->getUser();
            if (!$user || !$user->getId()) {
                throw new LocalizedException(__('Sessão expirada.'));
            }

            $this->taskManagement->createManual(
                (array) $this->getRequest()->getPostValue(),
                (int) $user->getId()
            );

            $this->messageManager->addSuccessMessage(__('Tarefa criada com sucesso.'));
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        } catch (\Exception) {
            $this->messageManager->addErrorMessage(__('Não foi possível criar a tarefa.'));
        }

        if ($customerId > 0) {
            return $redirect->setPath('awa_commercial/commercialcustomer/view', ['customer_id' => $customerId]);
        }

        return $redirect->setPath('awa_commercial/commercialtask/index');
    }
}
