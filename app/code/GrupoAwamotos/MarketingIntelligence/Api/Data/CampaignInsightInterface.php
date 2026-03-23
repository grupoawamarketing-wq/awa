<?php

declare(strict_types=1);

namespace GrupoAwamotos\MarketingIntelligence\Api\Data;

/**
 * @api
 */
interface CampaignInsightInterface
{
    public const INSIGHT_ID = 'insight_id';
    public const CAMPAIGN_ID = 'campaign_id';
    public const CAMPAIGN_NAME = 'campaign_name';
    public const ADSET_ID = 'adset_id';
    public const ADSET_NAME = 'adset_name';
    public const AD_ID = 'ad_id';
    public const AD_NAME = 'ad_name';
    public const DATE_START = 'date_start';
    public const DATE_STOP = 'date_stop';
    public const SPEND = 'spend';
    public const IMPRESSIONS = 'impressions';
    public const CLICKS = 'clicks';
    public const REACH = 'reach';
    public const ACTIONS_JSON = 'actions_json';
    public const ACTION_VALUES_JSON = 'action_values_json';
    public const PURCHASE_ROAS = 'purchase_roas';
    public const CPC = 'cpc';
    public const CPM = 'cpm';
    public const CTR = 'ctr';
    public const CLASSIFICATION = 'classification';
    public const CLASSIFIED_BY = 'classified_by';
    public const CREATED_AT = 'created_at';
    public const UPDATED_AT = 'updated_at';

    public function getInsightId(): ?int;

    public function setInsightId(int $insightId): self;

    public function getCampaignId(): ?string;

    public function setCampaignId(?string $campaignId): self;

    public function getCampaignName(): ?string;

    public function setCampaignName(?string $campaignName): self;

    public function getAdsetId(): ?string;

    public function setAdsetId(?string $adsetId): self;

    public function getAdsetName(): ?string;

    public function setAdsetName(?string $adsetName): self;

    public function getAdId(): ?string;

    public function setAdId(?string $adId): self;

    public function getAdName(): ?string;

    public function setAdName(?string $adName): self;

    public function getDateStart(): ?string;

    public function setDateStart(string $dateStart): self;

    public function getDateStop(): ?string;

    public function setDateStop(string $dateStop): self;

    public function getSpend(): ?float;

    public function setSpend(float $spend): self;

    public function getImpressions(): ?int;

    public function setImpressions(int $impressions): self;

    public function getClicks(): ?int;

    public function setClicks(int $clicks): self;

    public function getReach(): ?int;

    public function setReach(int $reach): self;

    public function getActionsJson(): ?string;

    public function setActionsJson(?string $actionsJson): self;

    public function getActionValuesJson(): ?string;

    public function setActionValuesJson(?string $actionValuesJson): self;

    public function getPurchaseRoas(): ?float;

    public function setPurchaseRoas(?float $purchaseRoas): self;

    public function getCpc(): ?float;

    public function setCpc(?float $cpc): self;

    public function getCpm(): ?float;

    public function setCpm(?float $cpm): self;

    public function getCtr(): ?float;

    public function setCtr(?float $ctr): self;

    public function getClassification(): ?string;

    public function setClassification(?string $classification): self;

    public function getClassifiedBy(): ?string;

    public function setClassifiedBy(?string $classifiedBy): self;

    public function getCreatedAt(): ?string;

    public function setCreatedAt(?string $createdAt): self;

    public function getUpdatedAt(): ?string;

    public function setUpdatedAt(?string $updatedAt): self;
}
