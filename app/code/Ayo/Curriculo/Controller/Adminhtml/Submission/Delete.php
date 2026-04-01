<?php

declare(strict_types=1);

namespace Ayo\Curriculo\Controller\Adminhtml\Submission;

use Ayo\Curriculo\Model\SubmissionFactory;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;

class Delete extends Action
{
    public const ADMIN_RESOURCE = 'Ayo_Curriculo::submission_delete';

    /**
     * @var SubmissionFactory
     */
    private $submissionFactory;

    public function __construct(
        Context $context,
        SubmissionFactory $submissionFactory
    ) {
        parent::__construct($context);
        $this->submissionFactory = $submissionFactory;
    }

    public function execute()
    {
        $id = (int)$this->getRequest()->getParam('id');
        if ($id <= 0) {
            $this->messageManager->addErrorMessage(__('Candidatura não encontrada.'));
            return $this->resultRedirectFactory->create()->setPath('*/*/');
        }

        try {
            $submission = $this->submissionFactory->create();
            $submission->load($id);

            if (!$submission->getId()) {
                $this->messageManager->addErrorMessage(__('Candidatura não encontrada.'));
                return $this->resultRedirectFactory->create()->setPath('*/*/');
            }

            $submission->delete();
            $this->messageManager->addSuccessMessage(__('Candidatura excluída com sucesso.'));
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(__('Não foi possível excluir a candidatura.'));
        }

        return $this->resultRedirectFactory->create()->setPath('*/*/');
    }
}
