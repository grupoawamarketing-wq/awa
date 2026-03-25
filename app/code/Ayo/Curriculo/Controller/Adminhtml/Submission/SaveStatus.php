<?php
declare(strict_types=1);

namespace Ayo\Curriculo\Controller\Adminhtml\Submission;

use Ayo\Curriculo\Model\Mail\StatusNotifier;
use Ayo\Curriculo\Model\SubmissionFactory;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;

class SaveStatus extends Action
{
    public const ADMIN_RESOURCE = 'Ayo_Curriculo::submission_update';

    /**
     * @var SubmissionFactory
     */
    private $submissionFactory;

    /**
     * @var StatusNotifier
     */
    private $statusNotifier;

    public function __construct(
        Context $context,
        SubmissionFactory $submissionFactory,
        StatusNotifier $statusNotifier
    ) {
        parent::__construct($context);
        $this->submissionFactory = $submissionFactory;
        $this->statusNotifier = $statusNotifier;
    }

    public function execute()
    {
        $id = (int)$this->getRequest()->getParam('id');
        $status = $this->getRequest()->getParam('status');

        if (!$id || !$status) {
            $this->messageManager->addErrorMessage(__('Parâmetros inválidos.'));
            return $this->resultRedirectFactory->create()->setPath('*/*/');
        }

        try {
            $submission = $this->submissionFactory->create();
            $submission->load($id);

            if (!$submission->getId()) {
                $this->messageManager->addErrorMessage(__('Candidatura não encontrada.'));
                return $this->resultRedirectFactory->create()->setPath('*/*/');
            }

            $previousStatus = (string)$submission->getData('status');
            $submission->setData('status', $status);

            $notes = trim((string)$this->getRequest()->getParam('notes', ''));
            if ($notes !== '') {
                $existing = trim((string)$submission->getData('notes'));
                $timestamp = date('d/m/Y H:i');
                $newNote = '[' . $timestamp . '] ' . strip_tags($notes);
                $submission->setData(
                    'notes',
                    $existing !== '' ? $existing . "\n" . $newNote : $newNote
                );
            }

            $submission->save();

            if ($status !== $previousStatus) {
                $this->statusNotifier->send($submission);
            }

            $this->messageManager->addSuccessMessage(__('Status atualizado com sucesso.'));
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(__('Erro ao atualizar status. Tente novamente.'));
        }

        return $this->resultRedirectFactory->create()->setPath('*/*/view', ['id' => $id]);
    }
}
