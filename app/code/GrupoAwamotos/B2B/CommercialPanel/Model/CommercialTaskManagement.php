<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\CommercialPanel\Model;

use GrupoAwamotos\B2B\CommercialPanel\Api\CommercialTaskManagementInterface;
use GrupoAwamotos\B2B\CommercialPanel\Api\Data\CommercialTaskInterface;
use GrupoAwamotos\B2B\CommercialPanel\Api\PortfolioScopeInterface;
use GrupoAwamotos\B2B\CommercialPanel\Model\ResourceModel\CommercialTask\CollectionFactory as TaskCollectionFactory;
use GrupoAwamotos\B2B\CommercialPanel\Model\ResourceModel\CommercialTaskResource;
use GrupoAwamotos\B2B\Helper\CurrentAttendant;
use GrupoAwamotos\B2B\Model\ResourceModel\CustomerAttendant\CollectionFactory as CustomerAttendantCollectionFactory;
use Magento\Framework\Exception\LocalizedException;
use Psr\Log\LoggerInterface;

class CommercialTaskManagement implements CommercialTaskManagementInterface
{
    /** @var string[] */
    private const OPEN_STATUSES = ['open', 'in_progress'];

    /** @var string[] */
    private const ALLOWED_PRIORITIES = ['low', 'normal', 'high', 'urgent'];

    public function __construct(
        private readonly PortfolioScopeInterface $portfolioScope,
        private readonly CurrentAttendant $currentAttendant,
        private readonly CustomerAttendantCollectionFactory $customerAttendantCollectionFactory,
        private readonly CommercialTaskFactory $taskFactory,
        private readonly CommercialTaskResource $taskResource,
        private readonly TaskCollectionFactory $taskCollectionFactory,
        private readonly LoggerInterface $logger
    ) {
    }

    public function createManual(array $data, int $adminUserId): CommercialTaskInterface
    {
        $customerId = (int) ($data['customer_id'] ?? 0);
        if ($customerId <= 0) {
            throw new LocalizedException(__('Cliente inválido.'));
        }

        if (!$this->portfolioScope->canAccessCustomer($customerId)) {
            throw new LocalizedException(__('Cliente fora da sua carteira comercial.'));
        }

        $attendantId = $this->resolveAttendantId($customerId);
        $title = trim((string) ($data['title'] ?? ''));
        if ($title === '') {
            throw new LocalizedException(__('Informe o título da tarefa.'));
        }

        $priority = strtolower(trim((string) ($data['priority'] ?? 'normal')));
        if (!in_array($priority, self::ALLOWED_PRIORITIES, true)) {
            $priority = 'normal';
        }

        $dedupKey = sprintf('manual:%d:%d:%s', $customerId, $adminUserId, uniqid('', true));

        /** @var CommercialTaskInterface $task */
        $task = $this->taskFactory->create();
        $task->setCustomerId($customerId);
        $task->setAttendantId($attendantId);
        $task->setTaskType(TaskType::MANUAL);
        $task->setPriority($priority);
        $task->setStatus('open');
        $task->setTitle(mb_substr($title, 0, 255));
        $task->setObservation(trim((string) ($data['observation'] ?? '')) ?: null);
        $task->setDedupKey($dedupKey);
        $task->setDueAt($this->normalizeOptionalDateTime($data['due_at'] ?? null));
        $task->setData('created_by', $adminUserId);

        $this->taskResource->save($task);

        return $task;
    }

    public function createAutomatic(array $data): ?CommercialTaskInterface
    {
        $dedupKey = (string) ($data['dedup_key'] ?? '');
        if ($dedupKey === '') {
            return null;
        }

        if ($this->existsByDedupKey($dedupKey)) {
            return null;
        }

        $customerId = (int) ($data['customer_id'] ?? 0);
        $attendantId = (int) ($data['attendant_id'] ?? 0);
        if ($customerId <= 0 || $attendantId <= 0) {
            return null;
        }

        try {
            /** @var CommercialTaskInterface $task */
            $task = $this->taskFactory->create();
            $task->setCustomerId($customerId);
            $task->setAttendantId($attendantId);
            $task->setTaskType((string) ($data['task_type'] ?? TaskType::MANUAL));
            $task->setPriority((string) ($data['priority'] ?? 'normal'));
            $task->setStatus('open');
            $task->setTitle(mb_substr((string) ($data['title'] ?? 'Tarefa comercial'), 0, 255));
            $task->setObservation(isset($data['observation']) ? (string) $data['observation'] : null);
            $task->setDedupKey($dedupKey);
            $task->setDueAt($this->normalizeOptionalDateTime($data['due_at'] ?? null));
            $task->setData('source_entity_type', $data['source_entity_type'] ?? null);
            $task->setData('source_entity_id', $data['source_entity_id'] ?? null);
            $task->setData('created_by', null);

            $this->taskResource->save($task);

            return $task;
        } catch (\Exception $e) {
            $this->logger->warning('[AWA Commercial Task] Falha ao criar tarefa automática: ' . $e->getMessage(), [
                'dedup_key' => $dedupKey,
            ]);

            return null;
        }
    }

    public function complete(int $taskId, int $adminUserId): CommercialTaskInterface
    {
        /** @var CommercialTaskInterface $task */
        $task = $this->taskFactory->create();
        $this->taskResource->load($task, $taskId);

        if (!$task->getTaskId()) {
            throw new LocalizedException(__('Tarefa não encontrada.'));
        }

        if (!$this->portfolioScope->canAccessCustomer($task->getCustomerId())) {
            throw new LocalizedException(__('Tarefa fora do seu escopo comercial.'));
        }

        if ($task->getStatus() === 'done') {
            return $task;
        }

        $task->setStatus('done');
        $task->setCompletedAt(date('Y-m-d H:i:s'));
        $this->taskResource->save($task);

        return $task;
    }

    public function reschedule(int $taskId, string $dueAt, int $adminUserId): CommercialTaskInterface
    {
        /** @var CommercialTaskInterface $task */
        $task = $this->taskFactory->create();
        $this->taskResource->load($task, $taskId);

        if (!$task->getTaskId()) {
            throw new LocalizedException(__('Tarefa não encontrada.'));
        }

        if (!$this->portfolioScope->canAccessCustomer($task->getCustomerId())) {
            throw new LocalizedException(__('Tarefa fora do seu escopo comercial.'));
        }

        if ($task->getStatus() === 'done') {
            throw new LocalizedException(__('Não é possível reagendar tarefa concluída.'));
        }

        $normalized = $this->normalizeOptionalDateTime($dueAt);
        if ($normalized === null) {
            throw new LocalizedException(__('Informe uma data de prazo válida.'));
        }

        $task->setDueAt($normalized);
        if ($task->getStatus() === 'open') {
            $task->setStatus('in_progress');
        }
        $this->taskResource->save($task);

        return $task;
    }

    public function hasOpenTaskByDedupKey(string $dedupKey): bool
    {
        $collection = $this->taskCollectionFactory->create();
        $collection->addFieldToFilter('dedup_key', $dedupKey);
        $collection->addFieldToFilter('status', ['in' => self::OPEN_STATUSES]);
        $collection->setPageSize(1);

        return (bool) $collection->getSize();
    }

    public function existsByDedupKey(string $dedupKey): bool
    {
        $collection = $this->taskCollectionFactory->create();
        $collection->addFieldToFilter('dedup_key', $dedupKey);
        $collection->setPageSize(1);

        return (bool) $collection->getSize();
    }

    private function resolveAttendantId(int $customerId): int
    {
        $currentAttendantId = $this->currentAttendant->getId();
        if ($currentAttendantId) {
            return $currentAttendantId;
        }

        $collection = $this->customerAttendantCollectionFactory->create();
        $collection->addFieldToFilter('customer_id', $customerId);
        $collection->setPageSize(1);
        $item = $collection->getFirstItem();

        $attendantId = (int) $item->getData('attendant_id');
        if ($attendantId <= 0) {
            throw new LocalizedException(__('Cliente sem vendedora responsável.'));
        }

        return $attendantId;
    }

    private function normalizeOptionalDateTime(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $timestamp = strtotime((string) $value);

        return $timestamp !== false ? date('Y-m-d H:i:s', $timestamp) : null;
    }
}
