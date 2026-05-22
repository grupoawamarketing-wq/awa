<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\CommercialPanel\Api\Data;

interface CommercialTaskInterface
{
    public const TASK_ID = 'task_id';
    public const CUSTOMER_ID = 'customer_id';
    public const ATTENDANT_ID = 'attendant_id';
    public const TASK_TYPE = 'task_type';
    public const PRIORITY = 'priority';
    public const STATUS = 'status';
    public const TITLE = 'title';
    public const OBSERVATION = 'observation';
    public const SOURCE_ENTITY_TYPE = 'source_entity_type';
    public const SOURCE_ENTITY_ID = 'source_entity_id';
    public const DEDUP_KEY = 'dedup_key';
    public const DUE_AT = 'due_at';
    public const COMPLETED_AT = 'completed_at';
    public const CREATED_BY = 'created_by';
    public const CREATED_AT = 'created_at';

    public function getTaskId(): ?int;

    public function getCustomerId(): int;

    public function setCustomerId(int $customerId): self;

    public function getAttendantId(): int;

    public function setAttendantId(int $attendantId): self;

    public function getTaskType(): string;

    public function setTaskType(string $taskType): self;

    public function getPriority(): string;

    public function setPriority(string $priority): self;

    public function getStatus(): string;

    public function setStatus(string $status): self;

    public function getTitle(): string;

    public function setTitle(string $title): self;

    public function getObservation(): ?string;

    public function setObservation(?string $observation): self;

    public function getDedupKey(): string;

    public function setDedupKey(string $dedupKey): self;

    public function getDueAt(): ?string;

    public function setDueAt(?string $dueAt): self;

    public function getCompletedAt(): ?string;

    public function setCompletedAt(?string $completedAt): self;
}
