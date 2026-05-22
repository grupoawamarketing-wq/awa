<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\CommercialPanel\Block\Adminhtml\Task;

use GrupoAwamotos\B2B\CommercialPanel\Api\PortfolioScopeInterface;
use GrupoAwamotos\B2B\CommercialPanel\Model\CommercialTaskFactory;
use GrupoAwamotos\B2B\CommercialPanel\Model\ResourceModel\CommercialTaskResource;
use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;

class RescheduleForm extends Template
{
    /** @var array<string, mixed>|null */
    private ?array $taskCache = null;

    public function __construct(
        Context $context,
        private readonly CommercialTaskFactory $taskFactory,
        private readonly CommercialTaskResource $taskResource,
        private readonly PortfolioScopeInterface $portfolioScope,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function getTaskId(): int
    {
        return (int) $this->getRequest()->getParam('task_id');
    }

    /**
     * @return array<string, mixed>
     */
    public function getTask(): array
    {
        if ($this->taskCache === null) {
            $this->taskCache = $this->loadTask();
        }

        return $this->taskCache;
    }

    public function getSaveUrl(): string
    {
        return $this->getUrl('awa_commercial/commercialtask/reschedule', ['task_id' => $this->getTaskId()]);
    }

    public function getTasksUrl(): string
    {
        return $this->getUrl('awa_commercial/commercialtask/index');
    }

    /**
     * @return array<string, mixed>
     */
    private function loadTask(): array
    {
        $taskId = $this->getTaskId();
        if ($taskId <= 0) {
            return [];
        }

        $task = $this->taskFactory->create();
        $this->taskResource->load($task, $taskId);
        if (!$task->getTaskId()) {
            return [];
        }

        if (!$this->portfolioScope->canAccessCustomer($task->getCustomerId())) {
            return [];
        }

        return $task->getData();
    }
}
