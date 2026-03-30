<?php
/**
 * Admin Order Approval Action Controller (Approve/Reject)
 */
declare(strict_types=1);

namespace GrupoAwamotos\B2B\Controller\Adminhtml\Approval;

use GrupoAwamotos\B2B\Model\OrderApprovalService;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\Auth\Session as AuthSession;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Exception\LocalizedException;
use Psr\Log\LoggerInterface;

class ProcessAction extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'GrupoAwamotos_B2B::order_approval';

    /**
     * @var OrderApprovalService
     */
    private OrderApprovalService $approvalService;

    /**
     * @var AuthSession
     */
    private AuthSession $authSession;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    public function __construct(
        Context $context,
        OrderApprovalService $approvalService,
        AuthSession $authSession,
        LoggerInterface $logger
    ) {
        $this->approvalService = $approvalService;
        $this->authSession = $authSession;
        $this->logger = $logger;
        parent::__construct($context);
    }

    /**
     * Process admin approve/reject action
     *
     * @return \Magento\Framework\Controller\Result\Redirect
     */
    public function execute()
    {
        $redirect = $this->resultRedirectFactory->create();
        $redirect->setPath('grupoawamotos_b2b/approval/index');

        $approvalId = (int)$this->getRequest()->getParam('approval_id');
        $action = $this->getRequest()->getParam('approval_action');

        if (!$approvalId || !in_array($action, ['approve', 'reject'])) {
            $this->messageManager->addErrorMessage(__('Parâmetros inválidos.'));
            return $redirect;
        }

        $adminId = (int) $this->authSession->getUser()->getId();

        try {
            if ($action === 'approve') {
                $this->approvalService->approve($approvalId, $adminId);
                $this->messageManager->addSuccessMessage(__('Pedido aprovado com sucesso.'));
            } else {
                $reason = $this->getRequest()->getParam('reason', 'Rejeitado pelo administrador');
                $this->approvalService->reject($approvalId, $adminId, $reason);
                $this->messageManager->addSuccessMessage(__('Pedido rejeitado.'));
            }
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        } catch (\Exception $e) {
            $this->logger->error('[B2B Admin] Approval action failed', ['exception' => $e]);
            $this->messageManager->addErrorMessage(__('Erro ao processar aprovação.'));
        }

        return $redirect;
    }
}
