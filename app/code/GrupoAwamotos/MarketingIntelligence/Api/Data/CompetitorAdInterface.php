<?php

declare(strict_types=1);

namespace GrupoAwamotos\MarketingIntelligence\Api\Data;

/**
 * @api
 */
interface CompetitorAdInterface
{
    public const AD_ID = 'ad_id';
    public const COMPETITOR_ID = 'competitor_id';
    public const META_AD_ID = 'meta_ad_id';
    public const PAGE_ID = 'page_id';
    public const PAGE_NAME = 'page_name';
    public const AD_CREATIVE_BODY = 'ad_creative_body';
    public const AD_CREATIVE_TITLE = 'ad_creative_title';
    public const AD_CREATIVE_LINK = 'ad_creative_link';
    public const AD_CREATIVE_IMAGE_URL = 'ad_creative_image_url';
    public const AD_DELIVERY_START = 'ad_delivery_start';
    public const AD_DELIVERY_STOP = 'ad_delivery_stop';
    public const PLATFORMS = 'platforms';
    public const IS_ACTIVE = 'is_active';
    public const CATEGORY = 'category';
    public const FIRST_SEEN_AT = 'first_seen_at';
    public const LAST_SEEN_AT = 'last_seen_at';

    public function getAdId(): ?int;

    public function setAdId(int $adId): self;

    public function getCompetitorId(): ?int;

    public function setCompetitorId(?int $competitorId): self;

    public function getMetaAdId(): ?string;

    public function setMetaAdId(string $metaAdId): self;

    public function getPageId(): ?string;

    public function setPageId(?string $pageId): self;

    public function getPageName(): ?string;

    public function setPageName(?string $pageName): self;

    public function getAdCreativeBody(): ?string;

    public function setAdCreativeBody(?string $adCreativeBody): self;

    public function getAdCreativeTitle(): ?string;

    public function setAdCreativeTitle(?string $adCreativeTitle): self;

    public function getAdCreativeLink(): ?string;

    public function setAdCreativeLink(?string $adCreativeLink): self;

    public function getAdCreativeImageUrl(): ?string;

    public function setAdCreativeImageUrl(?string $adCreativeImageUrl): self;

    public function getAdDeliveryStart(): ?string;

    public function setAdDeliveryStart(?string $adDeliveryStart): self;

    public function getAdDeliveryStop(): ?string;

    public function setAdDeliveryStop(?string $adDeliveryStop): self;

    public function getPlatforms(): ?string;

    public function setPlatforms(?string $platforms): self;

    public function getIsActive(): bool;

    public function setIsActive(bool $isActive): self;

    public function getCategory(): ?string;

    public function setCategory(?string $category): self;

    public function getFirstSeenAt(): ?string;

    public function setFirstSeenAt(?string $firstSeenAt): self;

    public function getLastSeenAt(): ?string;

    public function setLastSeenAt(?string $lastSeenAt): self;
}
