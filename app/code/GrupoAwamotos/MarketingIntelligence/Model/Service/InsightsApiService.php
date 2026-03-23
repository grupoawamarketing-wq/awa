<?php

declare(strict_types=1);

namespace GrupoAwamotos\MarketingIntelligence\Model\Service;

use GrupoAwamotos\MarketingIntelligence\Model\CampaignInsightFactory;
use GrupoAwamotos\MarketingIntelligence\Model\ResourceModel\CampaignInsight as CampaignInsightResource;
use GrupoAwamotos\MarketingIntelligence\Model\ResourceModel\CampaignInsight\CollectionFactory;
use Meta\BusinessExtension\Helper\FBEHelper;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Psr\Log\LoggerInterface;

/**
 * Fetches campaign insights from Meta Marketing API and persists them locally.
 */
class InsightsApiService
{
    private const XML_PATH_ENABLED = 'marketing_intelligence/insights_api/enabled';
    private const XML_PATH_DATE_RANGE = 'marketing_intelligence/insights_api/date_range_days';
    private const XML_PATH_AD_ACCOUNT_ID = 'marketing_intelligence/meta_audiences/ad_account_id';

    private const INSIGHT_FIELDS = 'campaign_id,campaign_name,adset_id,adset_name,ad_id,ad_name,'
        . 'spend,impressions,clicks,reach,actions,action_values,purchase_roas,cpc,cpm,ctr';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly FBEHelper $fbeHelper,
        private readonly CampaignInsightFactory $insightFactory,
        private readonly CampaignInsightResource $insightResource,
        private readonly CollectionFactory $collectionFactory,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Fetch insights from Meta API and upsert into local table.
     *
     * @return int Number of rows synced
     */
    public function fetchAndStore(): int
    {
        if (!$this->scopeConfig->isSetFlag(self::XML_PATH_ENABLED)) {
            $this->logger->info('InsightsApiService: disabled via config.');
            return 0;
        }

        $adAccountId = $this->getAdAccountId();
        if ($adAccountId === null) {
            $this->logger->warning('InsightsApiService: ad_account_id not configured.');
            return 0;
        }

        $dateRange = (int) ($this->scopeConfig->getValue(self::XML_PATH_DATE_RANGE) ?: 30);
        $dateRange = max(1, min($dateRange, 90));

        $since = date('Y-m-d', strtotime("-{$dateRange} days"));
        $until = date('Y-m-d', strtotime('-1 day'));

        $synced = 0;
        $after = null;

        do {
            $params = [
                'fields' => self::INSIGHT_FIELDS,
                'level' => 'ad',
                'time_range' => json_encode(['since' => $since, 'until' => $until]),
                'time_increment' => 1,
                'limit' => 500,
            ];

            if ($after !== null) {
                $params['after'] = $after;
            }

            $response = $this->fbeHelper->apiGet("/{$adAccountId}/insights", $params);

            if (!is_array($response) || !isset($response['data'])) {
                $this->logger->error('InsightsApiService: unexpected API response.', [
                    'response' => is_array($response) ? json_encode($response) : (string) $response,
                ]);
                break;
            }

            foreach ($response['data'] as $row) {
                $this->upsertInsight($row);
                $synced++;
            }

            $after = $response['paging']['cursors']['after'] ?? null;
            $hasNextPage = !empty($response['paging']['next']);
        } while ($hasNextPage);

        $this->logger->info("InsightsApiService: synced {$synced} insight rows.");
        return $synced;
    }

    /**
     * Upsert a single insight row. Match on campaign_id + adset_id + ad_id + date_start.
     *
     * @param array<string, mixed> $row
     */
    private function upsertInsight(array $row): void
    {
        $campaignId = $row['campaign_id'] ?? null;
        $adsetId = $row['adset_id'] ?? null;
        $adId = $row['ad_id'] ?? null;
        $dateStart = $row['date_start'] ?? null;

        if ($campaignId === null || $dateStart === null) {
            return;
        }

        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter('campaign_id', $campaignId);
        $collection->addFieldToFilter('adset_id', $adsetId ?: ['null' => true]);
        $collection->addFieldToFilter('ad_id', $adId ?: ['null' => true]);
        $collection->addFieldToFilter('date_start', $dateStart);
        $collection->setPageSize(1);

        $existing = $collection->getFirstItem();

        $insight = $existing->getInsightId()
            ? $existing
            : $this->insightFactory->create();

        $insight->setCampaignId($campaignId);
        $insight->setCampaignName($row['campaign_name'] ?? null);
        $insight->setAdsetId($adsetId);
        $insight->setAdsetName($row['adset_name'] ?? null);
        $insight->setAdId($adId);
        $insight->setAdName($row['ad_name'] ?? null);
        $insight->setDateStart($dateStart);
        $insight->setDateStop($row['date_stop'] ?? $dateStart);
        $insight->setSpend((float) ($row['spend'] ?? 0));
        $insight->setImpressions((int) ($row['impressions'] ?? 0));
        $insight->setClicks((int) ($row['clicks'] ?? 0));
        $insight->setReach((int) ($row['reach'] ?? 0));
        $insight->setCpc(isset($row['cpc']) ? (float) $row['cpc'] : null);
        $insight->setCpm(isset($row['cpm']) ? (float) $row['cpm'] : null);
        $insight->setCtr(isset($row['ctr']) ? (float) $row['ctr'] : null);
        $insight->setPurchaseRoas(isset($row['purchase_roas']) ? (float) $row['purchase_roas'] : null);

        if (isset($row['actions'])) {
            $insight->setActionsJson(json_encode($row['actions']));
        }
        if (isset($row['action_values'])) {
            $insight->setActionValuesJson(json_encode($row['action_values']));
        }

        try {
            $this->insightResource->save($insight);
        } catch (\Exception $e) {
            $this->logger->error('InsightsApiService: save failed.', [
                'campaign_id' => $campaignId,
                'date' => $dateStart,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function getAdAccountId(): ?string
    {
        $id = $this->scopeConfig->getValue(self::XML_PATH_AD_ACCOUNT_ID);
        if (empty($id)) {
            return null;
        }
        return str_starts_with($id, 'act_') ? $id : 'act_' . $id;
    }
}
