<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\CommercialPanel\Model;

use GrupoAwamotos\B2B\CommercialPanel\Api\Data\CommercialTaskInterface;
use GrupoAwamotos\B2B\CommercialPanel\Model\ResourceModel\CommercialTaskResource;
use Magento\Framework\Model\AbstractModel;

class CommercialTask extends AbstractModel implements CommercialTaskInterface
{
    protected function _construct(): void
    {
        $this->_init(CommercialTaskResource::class);
    }

    public function getTaskId(): ?int
    {
        $value = $this->getData(self::TASK_ID);

        return $value !== null ? (int) $value : null;
    }

    public function getCustomerId(): int
    {
        return (int) $this->getData(self::CUSTOMER_ID);
    }

    public function setCustomerId(int $customerId): CommercialTaskInterface
    {
        return $this->setData(self::CUSTOMER_ID, $customerId);
    }

    public function getAttendantId(): int
    {
        return (int) $this->getData(self::ATTENDANT_ID);
    }

    public function setAttendantId(int $attendantId): CommercialTaskInterface
    {
        return $this->setData(self::ATTENDANT_ID, $attendantId);
    }

    public function getTaskType(): string
    {
        return (string) $this->getData(self::TASK_TYPE);
    }

    public function setTaskType(string $taskType): CommercialTaskInterface
    {
        return $this->setData(self::TASK_TYPE, $taskType);
    }

    public function getPriority(): string
    {
        return (string) $this->getData(self::PRIORITY);
    }

    public function setPriority(string $priority): CommercialTaskInterface
    {
        return $this->setData(self::PRIORITY, $priority);
    }

    public function getStatus(): string
    {
        return (string) $this->getData(self::STATUS);
    }

    public function setStatus(string $status): CommercialTaskInterface
    {
        return $this->setData(self::STATUS, $status);
    }

    public function getTitle(): string
    {
        return (string) $this->getData(self::TITLE);
    }

    public function setTitle(string $title): CommercialTaskInterface
    {
        return $this->setData(self::TITLE, $title);
    }

    public function getObservation(): ?string
    {
        $value = $this->getData(self::OBSERVATION);

        return $value !== null && $value !== '' ? (string) $value : null;
    }

    public function setObservation(?string $observation): CommercialTaskInterface
    {
        return $this->setData(self::OBSERVATION, $observation);
    }

    public function getDedupKey(): string
    {
        return (string) $this->getData(self::DEDUP_KEY);
    }

    public function setDedupKey(string $dedupKey): CommercialTaskInterface
    {
        return $this->setData(self::DEDUP_KEY, $dedupKey);
    }

    public function getDueAt(): ?string
    {
        $value = $this->getData(self::DUE_AT);

        return $value !== null && $value !== '' ? (string) $value : null;
    }

    public function setDueAt(?string $dueAt): CommercialTaskInterface
    {
        return $this->setData(self::DUE_AT, $dueAt);
    }

    public function getCompletedAt(): ?string
    {
        $value = $this->getData(self::COMPLETED_AT);

        return $value !== null && $value !== '' ? (string) $value : null;
    }

    public function setCompletedAt(?string $completedAt): CommercialTaskInterface
    {
        return $this->setData(self::COMPLETED_AT, $completedAt);
    }
}
