<?php

declare(strict_types=1);

namespace Ayo\Curriculo\Block\Adminhtml\Submission;

use Ayo\Curriculo\Model\SubmissionFactory;
use Ayo\Curriculo\Model\Source\Status;
use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;

class View extends Template
{
    /**
     * @var SubmissionFactory
     */
    private $submissionFactory;

    /**
     * @var Status
     */
    private $statusSource;

    /**
     * @var \Ayo\Curriculo\Model\Submission|null
     */
    private $submission;

    public function __construct(
        Context $context,
        SubmissionFactory $submissionFactory,
        Status $statusSource,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->submissionFactory = $submissionFactory;
        $this->statusSource = $statusSource;
    }

    public function getSubmission()
    {
        if ($this->submission === null) {
            $id = (int)$this->getRequest()->getParam('id');
            $this->submission = $this->submissionFactory->create();
            $this->submission->load($id);
        }
        return $this->submission;
    }

    public function getStatusOptions(): array
    {
        return $this->statusSource->toOptionArray();
    }

    public function getStatusLabel(string $status): string
    {
        $options = $this->getStatusOptions();
        foreach ($options as $option) {
            if ($option['value'] === $status) {
                return (string)$option['label'];
            }
        }
        return $status;
    }

    public function getBackUrl(): string
    {
        return $this->getUrl('*/*/');
    }

    public function getDownloadUrl(): string
    {
        return $this->getUrl('*/*/download', ['id' => $this->getSubmission()->getId()]);
    }

    public function getSaveStatusUrl(): string
    {
        return $this->getUrl('*/*/saveStatus', ['id' => $this->getSubmission()->getId()]);
    }

    public function getDeleteUrl(): string
    {
        return $this->getUrl('*/*/delete', ['id' => $this->getSubmission()->getId()]);
    }
}
