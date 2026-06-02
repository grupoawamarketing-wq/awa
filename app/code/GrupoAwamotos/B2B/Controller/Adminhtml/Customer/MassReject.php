<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Controller\Adminhtml\Customer;

use GrupoAwamotos\B2B\Api\CustomerApprovalInterface;
use GrupoAwamotos\B2B\Model\Customer\Attribute\Source\ApprovalStatus;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\Auth\Session as AdminSession;
use Magento\Customer\Model\ResourceModel\Customer\CollectionFactory;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Ui\Component\MassAction\Filter;
use Psr\Log\LoggerInterface;

class MassReject extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'GrupoAwamotos_B2B::customer_approval';

    public function __construct(
        Context $context,
        private readonly Filter $filter,
        private readonly CollectionFactory $collectionFactory,
        private readonly CustomerApprovalInterface $customerApproval,
        private readonly AdminSession $adminSession,
        private readonly LoggerInterface $logger
    ) {
        parent::__construct($context);
    }

    public function execute(): \Magento\Framework\Controller\ResultInterface
    {
        $redirect = $this->resultRedirectFactory->create();
        $reason = trim((string) $this->getRequest()->getParam('rejection_reason', 'Cadastro não aprovado.'));

        try {
            $collection = $this->filter->getCollection($this->collectionFactory->create());
            $collection->addAttributeToSelect('b2b_approval_status');
            $collection->addAttributeToFilter(
                'b2b_approval_status',
                ['eq' => ApprovalStatus::STATUS_PENDING]
            );

            $adminUserId = (int) $this->adminSession->getUser()->getId();
            $rejected = 0;
            $skipped = 0;

            foreach ($collection as $customer) {
                $customerId = (int) $customer->getId();
                if ($customerId <= 0) {
                    $skipped++;
                    continue;
                }

                try {
                    $this->customerApproval->rejectCustomer($customerId, $adminUserId, $reason);
                    $rejected++;
                } catch (\Exception $e) {
                    $this->logger->error('[B2B MassReject] Falha ao rejeitar cliente #' . $customerId . ': ' . $e->getMessage());
                    $skipped++;
                }
            }

            if ($rejected > 0) {
                $this->messageManager->addSuccessMessage(__('%1 cliente(s) rejeitado(s).', $rejected));
            }

            if ($skipped > 0) {
                $this->messageManager->addWarningMessage(
                    __('%1 registro(s) ignorado(s) ou com erro.', $skipped)
                );
            }
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        } catch (\Exception $e) {
            $this->logger->error('[B2B MassReject] Erro: ' . $e->getMessage());
            $this->messageManager->addErrorMessage(__('Erro interno. Verifique os logs.'));
        }

        return $redirect->setPath('*/*/pending');
    }
}
