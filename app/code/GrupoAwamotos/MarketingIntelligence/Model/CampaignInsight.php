<?php

declare(strict_types=1);

namespace GrupoAwamotos\MarketingIntelligence\Model;

use GrupoAwamotos\MarketingIntelligence\Api\Data\CampaignInsightInterface;
use GrupoAwamotos\MarketingIntelligence\Model\ResourceModel\CampaignInsight as CampaignInsightResource;
use Magento\Framework\Model\AbstractModel;

class CampaignInsight extends AbstractModel implements CampaignInsightInterface
{
    protected $_eventPrefix = 'grupoawamotos_mktg_campaign_insight';

    protected function _construct(): void
    {
        $this->_init(CampaignInsightResource::class);
    }

    public function getInsightId(): ?int
    {
        $value = $this->getData(self::INSIGHT_ID);
        return $value !== null ? (int) $value : null;
    }

    public function setInsightId(int $insightId): self
    {
        return $this->setData(self::INSIGHT_ID, $insightId);
    }

    public function getCampaignId(): ?string
    {
        return $this->getData(self::CAMPAIGN_ID);
    }

    public function setCampaignId(?string $campaignId): self
    {
        return $this->setData(self::CAMPAIGN_ID, $campaignId);
    }

    public function getCampaignName(): ?string
    {
        return $this->getData(self::CAMPAIGN_NAME);
    }

    public function setCampaignName(?string $campaignName): self
    {
        return $this->setData(self::CAMPAIGN_NAME, $campaignName);
    }

    public function getAdsetId(): ?string
    {
        return $this->getData(self::ADSET_ID);
    }

    public function setAdsetId(?string $adsetId): self
    {
        return $this->setData(self::ADSET_ID, $adsetId);
    }

    public function getAdsetName(): ?string
    {
        return $this->getData(self::ADSET_NAME);
    }

    public function setAdsetName(?string $adsetName): self
    {
        return $this->setData(self::ADSET_NAME, $adsetName);
    }

    public function getAdId(): ?string
    {
        return $this->getData(self::AD_ID);
    }

    public function setAdId(?string $adId): self
    {
        return $this->setData(self::AD_ID, $adId);
    }

    public function getAdName(): ?string
    {
        return $this->getData(self::AD_NAME);
    }

    public function setAdName(?string $adName): self
    {
        return $this->setData(self::AD_NAME, $adName);
    }

    public function getDateStart(): ?string
    {
        return $this->getData(self::DATE_START);
    }

    public function setDateStart(string $dateStart): self
    {
        return $this->setData(self::DATE_START, $dateStart);
    }

    public function getDateStop(): ?string
    {
        return $this->getData(self::DATE_STOP);
    }

    public function setDateStop(string $dateStop): self
    {
        return $this->setData(self::DATE_STOP, $dateStop);
    }

    public function getSpend(): ?float
    {
        $value = $this->getData(self::SPEND);
        return $value !== null ? (float) $value : null;
    }

    public function setSpend(float $spend): self
    {
        return $this->setData(self::SPEND, $spend);
    }

    public function getImpressions(): ?int
    {
        $value = $this->getData(self::IMPRESSIONS);
        return $value !== null ? (int) $value : null;
    }

    public function setImpressions(int $impressions): self
    {
        return $this->setData(self::IMPRESSIONS, $impressions);
    }

    public function getClicks(): ?int
    {
        $value = $this->getData(self::CLICKS);
        return $value !== null ? (int) $value : null;
    }

    public function setClicks(int $clicks): self
    {
        return $this->setData(self::CLICKS, $clicks);
    }

    public function getReach(): ?int
    {
        $value = $this->getData(self::REACH);
        return $value !== null ? (int) $value : null;
    }

    public function setReach(int $reach): self
    {
        return $this->setData(self::REACH, $reach);
    }

    public function getActionsJson(): ?string
    {
        return $this->getData(self::ACTIONS_JSON);
    }

    public function setActionsJson(?string $actionsJson): self
    {
        return $this->setData(self::ACTIONS_JSON, $actionsJson);
    }

    public function getActionValuesJson(): ?string
    {
        return $this->getData(self::ACTION_VALUES_JSON);
    }

    public function setActionValuesJson(?string $actionValuesJson): self
    {
        return $this->setData(self::ACTION_VALUES_JSON, $actionValuesJson);
    }

    public function getPurchaseRoas(): ?float
    {
        $value = $this->getData(self::PURCHASE_ROAS);
        return $value !== null ? (float) $value : null;
    }

    public function setPurchaseRoas(?float $purchaseRoas): self
    {
        return $this->setData(self::PURCHASE_ROAS, $purchaseRoas);
    }

    public function getCpc(): ?float
    {
        $value = $this->getData(self::CPC);
        return $value !== null ? (float) $value : null;
    }

    public function setCpc(?float $cpc): self
    {
        return $this->setData(self::CPC, $cpc);
    }

    public function getCpm(): ?float
    {
        $value = $this->getData(self::CPM);
        return $value !== null ? (float) $value : null;
    }

    public function setCpm(?float $cpm): self
    {
        return $this->setData(self::CPM, $cpm);
    }

    public function getCtr(): ?float
    {
        $value = $this->getData(self::CTR);
        return $value !== null ? (float) $value : null;
    }

    public function setCtr(?float $ctr): self
    {
        return $this->setData(self::CTR, $ctr);
    }

    public function getClassification(): ?string
    {
        return $this->getData(self::CLASSIFICATION);
    }

    public function setClassification(?string $classification): self
    {
        return $this->setData(self::CLASSIFICATION, $classification);
    }

    public function getClassifiedBy(): ?string
    {
        return $this->getData(self::CLASSIFIED_BY);
    }

    public function setClassifiedBy(?string $classifiedBy): self
    {
        return $this->setData(self::CLASSIFIED_BY, $classifiedBy);
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
