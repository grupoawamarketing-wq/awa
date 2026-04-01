<?php

declare(strict_types=1);

namespace Ayo\Curriculo\Api\Data;

interface SubmissionInterface
{
    public const ENTITY_ID = 'entity_id';
    public const TRACKING_CODE = 'tracking_code';
    public const NAME = 'name';
    public const EMAIL = 'email';
    public const PHONE = 'phone';
    public const CEP = 'cep';
    public const CPF = 'cpf';
    public const CNPJ = 'cnpj';
    public const CITY = 'city';
    public const STATE = 'state';
    public const POSITION = 'position';
    public const EXPERIENCE_LEVEL = 'experience_level';
    public const LINKEDIN = 'linkedin';
    public const PORTFOLIO = 'portfolio';
    public const MESSAGE = 'message';
    public const FILE_PATH = 'file_path';
    public const FILE_NAME = 'file_name';
    public const WORK_AREA = 'work_area';
    public const SPECIALTIES = 'specialties';
    public const CNH = 'cnh';
    public const AVAILABILITY = 'availability';
    public const CONTRACT_TYPE = 'contract_type';
    public const SALARY_EXPECTATION = 'salary_expectation';
    public const REFERRAL_SOURCE = 'referral_source';
    public const STATUS = 'status';
    public const NOTES = 'notes';
    public const STORE_ID = 'store_id';
    public const CREATED_AT = 'created_at';
    public const UPDATED_AT = 'updated_at';

    // Status constants
    public const STATUS_PENDING = 'pending';
    public const STATUS_REVIEWING = 'reviewing';
    public const STATUS_INTERVIEW = 'interview';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    public function getEntityId(): ?int;
    public function setEntityId(int $entityId): self;

    public function getTrackingCode(): ?string;
    public function setTrackingCode(string $trackingCode): self;

    public function getName(): ?string;
    public function setName(string $name): self;

    public function getEmail(): ?string;
    public function setEmail(string $email): self;

    public function getPhone(): ?string;
    public function setPhone(?string $phone): self;

    public function getPosition(): ?string;
    public function setPosition(?string $position): self;

    public function getStatus(): ?string;
    public function setStatus(string $status): self;

    public function getFilePath(): ?string;
    public function setFilePath(?string $filePath): self;

    public function getFileName(): ?string;
    public function setFileName(?string $fileName): self;

    public function getCreatedAt(): ?string;
    public function setCreatedAt(string $createdAt): self;
}
