<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Controller\Adminhtml\CommercialInactive;

use GrupoAwamotos\B2B\CommercialPanel\Model\Intelligence\InactiveCustomerService;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultInterface;

class MarkInProgress extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'GrupoAwamotos_B2B::commercial_inactive_manage';

    public function __construct(
        Context $context,
        private readonly InactiveCustomerService $inactiveCustomerService
    ) {
        parent::__construct($context);
    }

    public function execute(): ResultInterface
    {
        $customerId = (int) $this->getRequest()->getParam('customer_id');
        $resultRedirect = $this->resultRedirectFactory->create();
        $resultRedirect->setPath('awa_commercial/commercialinactive/index');

        if ($customerId <= 0) {
            $this->messageManager->addErrorMessage(__('Cliente inválido.'));

            return $resultRedirect;
        }

        try {
            if ($this->inactiveCustomerService->markInProgress($customerId)) {
                $this->messageManager->addSuccessMessage(__('Cliente marcado como em atendimento.'));
            } else {
                $this->messageManager->addErrorMessage(__('Não foi possível atualizar o cliente.'));
            }
        } catch (\Throwable) {
            $this->messageManager->addErrorMessage(__('Ocorreu um erro ao processar a solicitação.'));
        }

        return $resultRedirect;
    }
}
