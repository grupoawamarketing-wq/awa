<?php

declare(strict_types=1);

namespace Ayo\Curriculo\Controller\Adminhtml\Submission;

use Ayo\Curriculo\Model\SubmissionFactory;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\Response\Http\FileFactory;
use Magento\Framework\Filesystem;

class Download extends Action
{
    public const ADMIN_RESOURCE = 'Ayo_Curriculo::submission_view';

    /**
     * @var SubmissionFactory
     */
    private $submissionFactory;

    /**
     * @var FileFactory
     */
    private $fileFactory;

    /**
     * @var Filesystem
     */
    private $filesystem;

    public function __construct(
        Context $context,
        SubmissionFactory $submissionFactory,
        FileFactory $fileFactory,
        Filesystem $filesystem
    ) {
        parent::__construct($context);
        $this->submissionFactory = $submissionFactory;
        $this->fileFactory = $fileFactory;
        $this->filesystem = $filesystem;
    }

    public function execute()
    {
        $id = (int)$this->getRequest()->getParam('id');

        if (!$id) {
            $this->messageManager->addErrorMessage(__('Candidatura não encontrada.'));
            return $this->resultRedirectFactory->create()->setPath('*/*/');
        }

        $submission = $this->submissionFactory->create();
        $submission->load($id);

        if (!$submission->getId()) {
            $this->messageManager->addErrorMessage(__('Candidatura não encontrada.'));
            return $this->resultRedirectFactory->create()->setPath('*/*/');
        }

        $filePath = $submission->getData('file_path');
        $fileName = $submission->getData('file_name') ?: basename($filePath);

        if (!$filePath) {
            $this->messageManager->addErrorMessage(__('Arquivo não encontrado.'));
            return $this->resultRedirectFactory->create()->setPath('*/*/');
        }

        $varDirectory = $this->filesystem->getDirectoryRead(DirectoryList::VAR_DIR);
        $absolutePath = $varDirectory->getAbsolutePath($filePath);

        if (!file_exists($absolutePath)) {
            $this->messageManager->addErrorMessage(__('Arquivo não encontrado no servidor.'));
            return $this->resultRedirectFactory->create()->setPath('*/*/');
        }

        $content = file_get_contents($absolutePath);

        return $this->fileFactory->create(
            $fileName,
            $content,
            DirectoryList::VAR_DIR,
            'application/octet-stream'
        );
    }
}
