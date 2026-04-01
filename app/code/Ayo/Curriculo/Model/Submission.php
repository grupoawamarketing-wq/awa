<?php

declare(strict_types=1);

namespace Ayo\Curriculo\Model;

use Ayo\Curriculo\Api\Data\SubmissionInterface;
use Magento\Framework\Model\AbstractModel;

class Submission extends AbstractModel implements SubmissionInterface
{
    protected function _construct(): void
    {
        $this->_init(ResourceModel\Submission::class);
    }

    public function getEntityId(): ?int
    {
        $id = $this->getData(self::ENTITY_ID);
        return $id !== null ? (int)$id : null;
    }

    public function setEntityId($entityId): self
    {
        return $this->setData(self::ENTITY_ID, $entityId);
    }

    public function getTrackingCode(): ?string
    {
        return $this->getData(self::TRACKING_CODE);
    }

    public function setTrackingCode(string $trackingCode): self
    {
        return $this->setData(self::TRACKING_CODE, $trackingCode);
    }

    public function getName(): ?string
    {
        return $this->getData(self::NAME);
    }

    public function setName(string $name): self
    {
        return $this->setData(self::NAME, $name);
    }

    public function getEmail(): ?string
    {
        return $this->getData(self::EMAIL);
    }

    public function setEmail(string $email): self
    {
        return $this->setData(self::EMAIL, $email);
    }

    public function getPhone(): ?string
    {
        return $this->getData(self::PHONE);
    }

    public function setPhone(?string $phone): self
    {
        return $this->setData(self::PHONE, $phone);
    }

    public function getPosition(): ?string
    {
        return $this->getData(self::POSITION);
    }

    public function setPosition(?string $position): self
    {
        return $this->setData(self::POSITION, $position);
    }

    public function getStatus(): ?string
    {
        return $this->getData(self::STATUS);
    }

    public function setStatus(string $status): self
    {
        return $this->setData(self::STATUS, $status);
    }

    public function getFilePath(): ?string
    {
        return $this->getData(self::FILE_PATH);
    }

    public function setFilePath(?string $filePath): self
    {
        return $this->setData(self::FILE_PATH, $filePath);
    }

    public function getFileName(): ?string
    {
        return $this->getData(self::FILE_NAME);
    }

    public function setFileName(?string $fileName): self
    {
        return $this->setData(self::FILE_NAME, $fileName);
    }

    public function getWorkArea(): ?string
    {
        return $this->getData(self::WORK_AREA);
    }

    public function setWorkArea(?string $workArea): self
    {
        return $this->setData(self::WORK_AREA, $workArea);
    }

    public function getSpecialties(): ?string
    {
        return $this->getData(self::SPECIALTIES);
    }

    public function setSpecialties(?string $specialties): self
    {
        return $this->setData(self::SPECIALTIES, $specialties);
    }

    public function getCnh(): ?string
    {
        return $this->getData(self::CNH);
    }

    public function setCnh(?string $cnh): self
    {
        return $this->setData(self::CNH, $cnh);
    }

    public function getAvailability(): ?string
    {
        return $this->getData(self::AVAILABILITY);
    }

    public function setAvailability(?string $availability): self
    {
        return $this->setData(self::AVAILABILITY, $availability);
    }

    public function getContractType(): ?string
    {
        return $this->getData(self::CONTRACT_TYPE);
    }

    public function setContractType(?string $contractType): self
    {
        return $this->setData(self::CONTRACT_TYPE, $contractType);
    }

    public function getSalaryExpectation(): ?string
    {
        return $this->getData(self::SALARY_EXPECTATION);
    }

    public function setSalaryExpectation(?string $salaryExpectation): self
    {
        return $this->setData(self::SALARY_EXPECTATION, $salaryExpectation);
    }

    public function getReferralSource(): ?string
    {
        return $this->getData(self::REFERRAL_SOURCE);
    }

    public function setReferralSource(?string $referralSource): self
    {
        return $this->setData(self::REFERRAL_SOURCE, $referralSource);
    }

    public function getCreatedAt(): ?string
    {
        return $this->getData(self::CREATED_AT);
    }

    public function setCreatedAt(string $createdAt): self
    {
        return $this->setData(self::CREATED_AT, $createdAt);
    }
}
