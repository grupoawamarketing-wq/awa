<?php

/**
 * Admin Credit Adjustment Controller
 */

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Controller\Adminhtml\Credit;

use GrupoAwamotos\B2B\Model\CreditService;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\LocalizedException;
use Psr\Log\LoggerInterface;

class Adjust extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'GrupoAwamotos_B2B::credit';

    /**
     * @var CreditService
     */
    private CreditService $creditService;

    /**
     * @var JsonFactory
     */
    private JsonFactory $jsonFactory;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    public function __construct(
        Context $context,
        CreditService $creditService,
        JsonFactory $jsonFactory,
        LoggerInterface $logger
    ) {
        $this->creditService = $creditService;
        $this->jsonFactory = $jsonFactory;
        $this->logger = $logger;
        parent::__construct($context);
    }

    /**
     * Adjust credit limit or balance for a customer
     *
     * @return \Magento\Framework\Controller\Result\Json
     */
    public function execute()
    {
        $result = $this->jsonFactory->create();

        $customerId = (int)$this->getRequest()->getParam('customer_id');
        $action = $this->getRequest()->getParam('credit_action');
        $amount = (float)$this->getRequest()->getParam('amount');
        $comment = $this->getRequest()->getParam('comment', '');

        if (!$customerId || !$action || $amount <= 0) {
            return $result->setData([
                'success' => false,
                'message' => __('Parâmetros inválidos.')
            ]);
        }

        try {
            $adminUser = $this->_auth->getUser();
            $adminId = $adminUser ? (int)$adminUser->getId() : null;

            switch ($action) {
                case 'set_limit':
                    $this->creditService->setLimit($customerId, $amount, $adminId, $comment);
                    $message = __('Limite de crédito definido para R$ %1', number_format($amount, 2, ',', '.'));
                    break;

                case 'add_credit':
                    $this->creditService->recordPayment(
                        $customerId,
                        $amount,
                        $adminId,
                        $comment ?: 'Ajuste manual de crédito'
                    );
                    $message = __('Crédito de R$ %1 adicionado.', number_format($amount, 2, ',', '.'));
                    break;

                case 'debit':
                    $this->creditService->charge(
                        $customerId,
                        $amount,
                        0,
                        $comment ?: 'Ajuste manual administrativo'
                    );
                    $message = __('Débito de R$ %1 realizado.', number_format($amount, 2, ',', '.'));
                    break;

                default:
                    return $result->setData(['success' => false, 'message' => __('Ação inválida.')]);
            }

            $creditLimit = $this->creditService->getCreditLimit($customerId);

            return $result->setData([
                'success' => true,
                'message' => $message,
                'credit_limit' => (float)$creditLimit->getCreditLimit(),
                'credit_used' => (float)$creditLimit->getUsedCredit(),
                'credit_available' => max(0.0, (float)$creditLimit->getAvailableCredit())
            ]);
        } catch (LocalizedException $e) {
            return $result->setData(['success' => false, 'message' => $e->getMessage()]);
        } catch (\Throwable $e) {
            $this->logger->error('B2B Credit Adjust error', ['exception' => $e]);
            return $result->setData(['success' => false, 'message' => __('Erro inesperado ao ajustar crédito.')]);
        }
    }
}
