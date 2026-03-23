<?php

declare(strict_types=1);

namespace GrupoAwamotos\MarketingIntelligence\Model;

use GrupoAwamotos\MarketingIntelligence\Api\Data\CompetitorInterface;
use GrupoAwamotos\MarketingIntelligence\Model\ResourceModel\Competitor as CompetitorResource;
use Magento\Framework\Model\AbstractModel;

class Competitor extends AbstractModel implements CompetitorInterface
{
    protected $_eventPrefix = 'grupoawamotos_mktg_competitor';

    protected function _construct(): void
    {
        $this->_init(CompetitorResource::class);
    }

    public function getCompetitorId(): ?int
    {
        $value = $this->getData(self::COMPETITOR_ID);
        return $value !== null ? (int) $value : null;
    }

    public function setCompetitorId(int $competitorId): self
    {
        return $this->setData(self::COMPETITOR_ID, $competitorId);
    }

    public function getName(): ?string
    {
        return $this->getData(self::NAME);
    }

    public function setName(string $name): self
    {
        return $this->setData(self::NAME, $name);
    }

    public function getMetaPageId(): ?string
    {
        return $this->getData(self::META_PAGE_ID);
    }

    public function setMetaPageId(?string $metaPageId): self
    {
        return $this->setData(self::META_PAGE_ID, $metaPageId);
    }

    public function getWebsite(): ?string
    {
        return $this->getData(self::WEBSITE);
    }

    public function setWebsite(?string $website): self
    {
        return $this->setData(self::WEBSITE, $website);
    }

    public function getKeywords(): ?string
    {
        return $this->getData(self::KEYWORDS);
    }

    public function setKeywords(?string $keywords): self
    {
        return $this->setData(self::KEYWORDS, $keywords);
    }

    public function getIsActive(): bool
    {
        return (bool) $this->getData(self::IS_ACTIVE);
    }

    public function setIsActive(bool $isActive): self
    {
        return $this->setData(self::IS_ACTIVE, $isActive);
    }

    public function getNotes(): ?string
    {
        return $this->getData(self::NOTES);
    }

    public function setNotes(?string $notes): self
    {
        return $this->setData(self::NOTES, $notes);
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
