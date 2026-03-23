<?php

declare(strict_types=1);

namespace GrupoAwamotos\MarketingIntelligence\Model;

use GrupoAwamotos\MarketingIntelligence\Api\Data\CompetitorAdInterface;
use GrupoAwamotos\MarketingIntelligence\Model\ResourceModel\CompetitorAd as CompetitorAdResource;
use Magento\Framework\Model\AbstractModel;

class CompetitorAd extends AbstractModel implements CompetitorAdInterface
{
    protected $_eventPrefix = 'grupoawamotos_mktg_competitor_ad';

    protected function _construct(): void
    {
        $this->_init(CompetitorAdResource::class);
    }

    public function getAdId(): ?int
    {
        $value = $this->getData(self::AD_ID);
        return $value !== null ? (int) $value : null;
    }

    public function setAdId(int $adId): self
    {
        return $this->setData(self::AD_ID, $adId);
    }

    public function getCompetitorId(): ?int
    {
        $value = $this->getData(self::COMPETITOR_ID);
        return $value !== null ? (int) $value : null;
    }

    public function setCompetitorId(?int $competitorId): self
    {
        return $this->setData(self::COMPETITOR_ID, $competitorId);
    }

    public function getMetaAdId(): ?string
    {
        return $this->getData(self::META_AD_ID);
    }

    public function setMetaAdId(string $metaAdId): self
    {
        return $this->setData(self::META_AD_ID, $metaAdId);
    }

    public function getPageId(): ?string
    {
        return $this->getData(self::PAGE_ID);
    }

    public function setPageId(?string $pageId): self
    {
        return $this->setData(self::PAGE_ID, $pageId);
    }

    public function getPageName(): ?string
    {
        return $this->getData(self::PAGE_NAME);
    }

    public function setPageName(?string $pageName): self
    {
        return $this->setData(self::PAGE_NAME, $pageName);
    }

    public function getAdCreativeBody(): ?string
    {
        return $this->getData(self::AD_CREATIVE_BODY);
    }

    public function setAdCreativeBody(?string $adCreativeBody): self
    {
        return $this->setData(self::AD_CREATIVE_BODY, $adCreativeBody);
    }

    public function getAdCreativeTitle(): ?string
    {
        return $this->getData(self::AD_CREATIVE_TITLE);
    }

    public function setAdCreativeTitle(?string $adCreativeTitle): self
    {
        return $this->setData(self::AD_CREATIVE_TITLE, $adCreativeTitle);
    }

    public function getAdCreativeLink(): ?string
    {
        return $this->getData(self::AD_CREATIVE_LINK);
    }

    public function setAdCreativeLink(?string $adCreativeLink): self
    {
        return $this->setData(self::AD_CREATIVE_LINK, $adCreativeLink);
    }

    public function getAdCreativeImageUrl(): ?string
    {
        return $this->getData(self::AD_CREATIVE_IMAGE_URL);
    }

    public function setAdCreativeImageUrl(?string $adCreativeImageUrl): self
    {
        return $this->setData(self::AD_CREATIVE_IMAGE_URL, $adCreativeImageUrl);
    }

    public function getAdDeliveryStart(): ?string
    {
        return $this->getData(self::AD_DELIVERY_START);
    }

    public function setAdDeliveryStart(?string $adDeliveryStart): self
    {
        return $this->setData(self::AD_DELIVERY_START, $adDeliveryStart);
    }

    public function getAdDeliveryStop(): ?string
    {
        return $this->getData(self::AD_DELIVERY_STOP);
    }

    public function setAdDeliveryStop(?string $adDeliveryStop): self
    {
        return $this->setData(self::AD_DELIVERY_STOP, $adDeliveryStop);
    }

    public function getPlatforms(): ?string
    {
        return $this->getData(self::PLATFORMS);
    }

    public function setPlatforms(?string $platforms): self
    {
        return $this->setData(self::PLATFORMS, $platforms);
    }

    public function getIsActive(): bool
    {
        return (bool) $this->getData(self::IS_ACTIVE);
    }

    public function setIsActive(bool $isActive): self
    {
        return $this->setData(self::IS_ACTIVE, $isActive);
    }

    public function getCategory(): ?string
    {
        return $this->getData(self::CATEGORY);
    }

    public function setCategory(?string $category): self
    {
        return $this->setData(self::CATEGORY, $category);
    }

    public function getFirstSeenAt(): ?string
    {
        return $this->getData(self::FIRST_SEEN_AT);
    }

    public function setFirstSeenAt(?string $firstSeenAt): self
    {
        return $this->setData(self::FIRST_SEEN_AT, $firstSeenAt);
    }

    public function getLastSeenAt(): ?string
    {
        return $this->getData(self::LAST_SEEN_AT);
    }

    public function setLastSeenAt(?string $lastSeenAt): self
    {
        return $this->setData(self::LAST_SEEN_AT, $lastSeenAt);
    }
}
