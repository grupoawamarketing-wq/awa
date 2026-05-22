<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Controller\Adminhtml\Customer;

use GrupoAwamotos\B2B\Model\Sectra\ProspectPipeline;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultInterface;

class SyncProspect extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'GrupoAwamotos_B2B::customer_approval';

    public function __construct(
        Context $context,
        private readonly ProspectPipeline $prospectPipeline
    ) {
        parent::__construct($context);
    }

    public function execute(): ResultInterface
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        $customerId = (int) $this->getRequest()->getParam('customer_id');

        if ($customerId <= 0) {
            $this->messageManager->addErrorMessage(__('Cliente inválido.'));
            return $resultRedirect->setPath('*/*/erpPending');
        }

        try {
            $result = $this->prospectPipeline->processApprovedCustomer($customerId);
            if ($result['success']) {
                $this->messageManager->addSuccessMessage(
                    __('Prospect reprocessado: %1', $result['message'] ?? 'OK')
                );
            } else {
                $this->messageManager->addWarningMessage(
                    __('Prospect não reprocessado: %1', $result['message'] ?? '—')
                );
            }
        } catch (\Throwable $e) {
            $this->messageManager->addErrorMessage(__('Erro ao reprocessar prospect: %1', $e->getMessage()));
        }

        return $resultRedirect->setPath('*/*/erpPending');
    }
}
