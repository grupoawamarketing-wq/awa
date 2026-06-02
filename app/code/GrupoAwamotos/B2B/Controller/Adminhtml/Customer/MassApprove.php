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

class MassApprove extends Action implements HttpPostActionInterface
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

        try {
            $collection = $this->filter->getCollection($this->collectionFactory->create());
            $collection->addAttributeToSelect('b2b_approval_status');
            $collection->addAttributeToFilter(
                'b2b_approval_status',
                ['eq' => ApprovalStatus::STATUS_PENDING]
            );

            $adminUserId = (int) $this->adminSession->getUser()->getId();
            $comment = (string) $this->getRequest()->getParam('comment', '');
            $approved = 0;
            $skipped = 0;

            foreach ($collection as $customer) {
                $customerId = (int) $customer->getId();
                if ($customerId <= 0) {
                    $skipped++;
                    continue;
                }

                try {
                    $this->customerApproval->approveCustomer($customerId, $adminUserId, $comment ?: null);
                    $approved++;
                } catch (\Exception $e) {
                    $this->logger->error('[B2B MassApprove] Falha ao aprovar cliente #' . $customerId . ': ' . $e->getMessage());
                    $skipped++;
                }
            }

            if ($approved > 0) {
                $this->messageManager->addSuccessMessage(__('%1 cliente(s) aprovado(s) com sucesso.', $approved));
            }

            if ($skipped > 0) {
                $this->messageManager->addWarningMessage(
                    __('%1 registro(s) ignorado(s) ou com erro.', $skipped)
                );
            }
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        } catch (\Exception $e) {
            $this->logger->error('[B2B MassApprove] Erro: ' . $e->getMessage());
            $this->messageManager->addErrorMessage(__('Erro interno ao aprovar. Verifique os logs.'));
        }

        return $redirect->setPath('*/*/pending');
    }
}
