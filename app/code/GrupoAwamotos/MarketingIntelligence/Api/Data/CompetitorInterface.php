<?php

declare(strict_types=1);

namespace GrupoAwamotos\MarketingIntelligence\Api\Data;

/**
 * @api
 */
interface CompetitorInterface
{
    public const COMPETITOR_ID = 'competitor_id';
    public const NAME = 'name';
    public const META_PAGE_ID = 'meta_page_id';
    public const WEBSITE = 'website';
    public const KEYWORDS = 'keywords';
    public const IS_ACTIVE = 'is_active';
    public const NOTES = 'notes';
    public const CREATED_AT = 'created_at';
    public const UPDATED_AT = 'updated_at';

    public function getCompetitorId(): ?int;

    public function setCompetitorId(int $competitorId): self;

    public function getName(): ?string;

    public function setName(string $name): self;

    public function getMetaPageId(): ?string;

    public function setMetaPageId(?string $metaPageId): self;

    public function getWebsite(): ?string;

    public function setWebsite(?string $website): self;

    public function getKeywords(): ?string;

    public function setKeywords(?string $keywords): self;

    public function getIsActive(): bool;

    public function setIsActive(bool $isActive): self;

    public function getNotes(): ?string;

    public function setNotes(?string $notes): self;

    public function getCreatedAt(): ?string;

    public function setCreatedAt(?string $createdAt): self;

    public function getUpdatedAt(): ?string;

    public function setUpdatedAt(?string $updatedAt): self;
}
