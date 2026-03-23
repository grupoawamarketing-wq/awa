<?php

declare(strict_types=1);

namespace GrupoAwamotos\MarketingIntelligence\Model;

use GrupoAwamotos\MarketingIntelligence\Api\Data\AudienceInterface;
use GrupoAwamotos\MarketingIntelligence\Model\ResourceModel\Audience as AudienceResource;
use Magento\Framework\Model\AbstractModel;

class Audience extends AbstractModel implements AudienceInterface
{
    protected $_eventPrefix = 'grupoawamotos_mktg_audience';

    protected function _construct(): void
    {
        $this->_init(AudienceResource::class);
    }

    public function getAudienceId(): ?int
    {
        $value = $this->getData(self::AUDIENCE_ID);
        return $value !== null ? (int) $value : null;
    }

    public function setAudienceId(int $audienceId): self
    {
        return $this->setData(self::AUDIENCE_ID, $audienceId);
    }

    public function getMetaAudienceId(): ?string
    {
        return $this->getData(self::META_AUDIENCE_ID);
    }

    public function setMetaAudienceId(?string $metaAudienceId): self
    {
        return $this->setData(self::META_AUDIENCE_ID, $metaAudienceId);
    }

    public function getName(): ?string
    {
        return $this->getData(self::NAME);
    }

    public function setName(string $name): self
    {
        return $this->setData(self::NAME, $name);
    }

    public function getAudienceType(): ?string
    {
        return $this->getData(self::AUDIENCE_TYPE);
    }

    public function setAudienceType(string $audienceType): self
    {
        return $this->setData(self::AUDIENCE_TYPE, $audienceType);
    }

    public function getSegmentRule(): ?string
    {
        return $this->getData(self::SEGMENT_RULE);
    }

    public function setSegmentRule(?string $segmentRule): self
    {
        return $this->setData(self::SEGMENT_RULE, $segmentRule);
    }

    public function getCustomerCount(): ?int
    {
        $value = $this->getData(self::CUSTOMER_COUNT);
        return $value !== null ? (int) $value : null;
    }

    public function setCustomerCount(?int $customerCount): self
    {
        return $this->setData(self::CUSTOMER_COUNT, $customerCount);
    }

    public function getMetaMatchRate(): ?float
    {
        $value = $this->getData(self::META_MATCH_RATE);
        return $value !== null ? (float) $value : null;
    }

    public function setMetaMatchRate(?float $metaMatchRate): self
    {
        return $this->setData(self::META_MATCH_RATE, $metaMatchRate);
    }

    public function getAutoRefresh(): bool
    {
        return (bool) $this->getData(self::AUTO_REFRESH);
    }

    public function setAutoRefresh(bool $autoRefresh): self
    {
        return $this->setData(self::AUTO_REFRESH, $autoRefresh);
    }

    public function getRefreshFrequency(): ?string
    {
        return $this->getData(self::REFRESH_FREQUENCY);
    }

    public function setRefreshFrequency(?string $refreshFrequency): self
    {
        return $this->setData(self::REFRESH_FREQUENCY, $refreshFrequency);
    }

    public function getLastSyncedAt(): ?string
    {
        return $this->getData(self::LAST_SYNCED_AT);
    }

    public function setLastSyncedAt(?string $lastSyncedAt): self
    {
        return $this->setData(self::LAST_SYNCED_AT, $lastSyncedAt);
    }

    public function getStatus(): ?string
    {
        return $this->getData(self::STATUS);
    }

    public function setStatus(string $status): self
    {
        return $this->setData(self::STATUS, $status);
    }

    public function getSourceAudienceId(): ?int
    {
        $value = $this->getData(self::SOURCE_AUDIENCE_ID);
        return $value !== null ? (int) $value : null;
    }

    public function setSourceAudienceId(?int $sourceAudienceId): self
    {
        return $this->setData(self::SOURCE_AUDIENCE_ID, $sourceAudienceId);
    }

    public function getLookalikeRatio(): ?float
    {
        $value = $this->getData(self::LOOKALIKE_RATIO);
        return $value !== null ? (float) $value : null;
    }

    public function setLookalikeRatio(?float $lookalikeRatio): self
    {
        return $this->setData(self::LOOKALIKE_RATIO, $lookalikeRatio);
    }

    public function getLookalikeCountry(): ?string
    {
        return $this->getData(self::LOOKALIKE_COUNTRY);
    }

    public function setLookalikeCountry(?string $lookalikeCountry): self
    {
        return $this->setData(self::LOOKALIKE_COUNTRY, $lookalikeCountry);
    }

    public function getCreatedAt(): ?string
    {
        return $this->getData(self::CREATED_AT);
    }

    public function setCreatedAt(?string $createdAt): self
    {
        return $this->setData(self::CREATED_AT, $createdAt);
    }

    public function getUpdatedAt(): ?string
    {
        return $this->getData(self::UPDATED_AT);
    }

    public function setUpdatedAt(?string $updatedAt): self
    {
        return $this->setData(self::UPDATED_AT, $updatedAt);
    }
}
