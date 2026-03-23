<?php

declare(strict_types=1);

namespace GrupoAwamotos\MarketingIntelligence\Api\Data;

/**
 * @api
 */
interface AudienceInterface
{
    public const AUDIENCE_ID = 'audience_id';
    public const META_AUDIENCE_ID = 'meta_audience_id';
    public const NAME = 'name';
    public const AUDIENCE_TYPE = 'audience_type';
    public const SEGMENT_RULE = 'segment_rule';
    public const CUSTOMER_COUNT = 'customer_count';
    public const META_MATCH_RATE = 'meta_match_rate';
    public const AUTO_REFRESH = 'auto_refresh';
    public const REFRESH_FREQUENCY = 'refresh_frequency';
    public const LAST_SYNCED_AT = 'last_synced_at';
    public const STATUS = 'status';
    public const SOURCE_AUDIENCE_ID = 'source_audience_id';
    public const LOOKALIKE_RATIO = 'lookalike_ratio';
    public const LOOKALIKE_COUNTRY = 'lookalike_country';
    public const CREATED_AT = 'created_at';
    public const UPDATED_AT = 'updated_at';

    public function getAudienceId(): ?int;

    public function setAudienceId(int $audienceId): self;

    public function getMetaAudienceId(): ?string;

    public function setMetaAudienceId(?string $metaAudienceId): self;

    public function getName(): ?string;

    public function setName(string $name): self;

    public function getAudienceType(): ?string;

    public function setAudienceType(string $audienceType): self;

    public function getSegmentRule(): ?string;

    public function setSegmentRule(?string $segmentRule): self;

    public function getCustomerCount(): ?int;

    public function setCustomerCount(?int $customerCount): self;

    public function getMetaMatchRate(): ?float;

    public function setMetaMatchRate(?float $metaMatchRate): self;

    public function getAutoRefresh(): bool;

    public function setAutoRefresh(bool $autoRefresh): self;

    public function getRefreshFrequency(): ?string;

    public function setRefreshFrequency(?string $refreshFrequency): self;

    public function getLastSyncedAt(): ?string;

    public function setLastSyncedAt(?string $lastSyncedAt): self;

    public function getStatus(): ?string;

    public function setStatus(string $status): self;

    public function getSourceAudienceId(): ?int;

    public function setSourceAudienceId(?int $sourceAudienceId): self;

    public function getLookalikeRatio(): ?float;

    public function setLookalikeRatio(?float $lookalikeRatio): self;

    public function getLookalikeCountry(): ?string;

    public function setLookalikeCountry(?string $lookalikeCountry): self;

    public function getCreatedAt(): ?string;

    public function setCreatedAt(?string $createdAt): self;

    public function getUpdatedAt(): ?string;

    public function setUpdatedAt(?string $updatedAt): self;
}
