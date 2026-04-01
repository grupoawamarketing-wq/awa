<?php

declare(strict_types=1);

namespace Ayo\Curriculo\Controller\Adminhtml\Submission;

use Ayo\Curriculo\Model\ResourceModel\Submission\CollectionFactory;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Ui\Component\MassAction\Filter;

class MassDelete extends Action
{
    public const ADMIN_RESOURCE = 'Ayo_Curriculo::submission_delete';

    /**
     * @var Filter
     */
    private $filter;

    /**
     * @var CollectionFactory
     */
    private $collectionFactory;

    public function __construct(
        Context $context,
        Filter $filter,
        CollectionFactory $collectionFactory
    ) {
        parent::__construct($context);
        $this->filter = $filter;
        $this->collectionFactory = $collectionFactory;
    }

    public function execute()
    {
        try {
            $collection = $this->filter->getCollection($this->collectionFactory->create());
            $deletedCount = 0;

            foreach ($collection as $submission) {
                $submission->delete();
                $deletedCount++;
            }

            if ($deletedCount > 0) {
                $this->messageManager->addSuccessMessage(
                    __('%1 candidatura(s) excluída(s) com sucesso.', $deletedCount)
                );
            } else {
                $this->messageManager->addNoticeMessage(__('Nenhuma candidatura foi selecionada.'));
            }
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(__('Não foi possível excluir as candidaturas selecionadas.'));
        }

        return $this->resultRedirectFactory->create()->setPath('*/*/');
    }
}
