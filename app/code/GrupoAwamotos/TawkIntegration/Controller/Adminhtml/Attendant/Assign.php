<?php

declare(strict_types=1);

namespace GrupoAwamotos\TawkIntegration\Controller\Adminhtml\Attendant;

use GrupoAwamotos\TawkIntegration\Model\AttendantService;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;

class Assign extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'GrupoAwamotos_TawkIntegration::config';

    private AttendantService $attendantService;

    public function __construct(Context $context, AttendantService $attendantService)
    {
        parent::__construct($context);
        $this->attendantService = $attendantService;
    }

    /**
     * @return \Magento\Framework\Controller\Result\Redirect
     */
    public function execute()
    {
        $customerId    = (int) $this->getRequest()->getParam('customer_id');
        $attendantCode = (string) $this->getRequest()->getParam('attendant_code');
        $resultRedirect = $this->resultRedirectFactory->create();

        if ($customerId <= 0 || $attendantCode === '') {
            $this->messageManager->addErrorMessage(__('Parâmetros inválidos.'));
            return $resultRedirect->setPath('customer/index/index');
        }

        try {
            $this->attendantService->assignAttendant($customerId, $attendantCode, false);
            $attendant = $this->attendantService->getAttendantByCode($attendantCode);
            $this->messageManager->addSuccessMessage(
                __('Atendente "%1" atribuído com sucesso.', $attendant['name'] ?? $attendantCode)
            );
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(__('Erro ao atribuir atendente: %1', $e->getMessage()));
        }

        return $resultRedirect->setPath('customer/index/edit', ['id' => $customerId, '_fragment' => 'tawk-chat-log']);
    }
}
