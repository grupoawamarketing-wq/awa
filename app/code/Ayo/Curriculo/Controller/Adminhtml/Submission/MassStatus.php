<?php

declare(strict_types=1);

namespace Ayo\Curriculo\Controller\Adminhtml\Submission;

use Ayo\Curriculo\Model\ResourceModel\Submission\CollectionFactory;
use Ayo\Curriculo\Model\Source\Status;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Ui\Component\MassAction\Filter;

class MassStatus extends Action
{
    public const ADMIN_RESOURCE = 'Ayo_Curriculo::submission_update';

    /**
     * @var Filter
     */
    private $filter;

    /**
     * @var CollectionFactory
     */
    private $collectionFactory;

    /**
     * @var Status
     */
    private $statusSource;

    public function __construct(
        Context $context,
        Filter $filter,
        CollectionFactory $collectionFactory,
        Status $statusSource
    ) {
        parent::__construct($context);
        $this->filter = $filter;
        $this->collectionFactory = $collectionFactory;
        $this->statusSource = $statusSource;
    }

    public function execute()
    {
        $status = (string)$this->getRequest()->getParam('status', '');
        if (!$this->isAllowedStatus($status)) {
            $this->messageManager->addErrorMessage(__('Status inválido para atualização em massa.'));
            return $this->resultRedirectFactory->create()->setPath('*/*/');
        }

        try {
            $collection = $this->filter->getCollection($this->collectionFactory->create());
            $updatedCount = 0;

            foreach ($collection as $submission) {
                $submission->setData('status', $status);
                $submission->save();
                $updatedCount++;
            }

            if ($updatedCount > 0) {
                $this->messageManager->addSuccessMessage(
                    __('Status atualizado para %1 candidatura(s).', $updatedCount)
                );
            } else {
                $this->messageManager->addNoticeMessage(__('Nenhuma candidatura foi selecionada.'));
            }
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(__('Não foi possível atualizar o status das candidaturas selecionadas.'));
        }

        return $this->resultRedirectFactory->create()->setPath('*/*/');
    }

    private function isAllowedStatus(string $status): bool
    {
        $allowed = array_map(static function (array $option): string {
            return (string)($option['value'] ?? '');
        }, $this->statusSource->toOptionArray());

        return in_array($status, $allowed, true);
    }
}
