<?php

declare(strict_types=1);

namespace GrupoAwamotos\MarketingIntelligence\Model\Service;

use GrupoAwamotos\MarketingIntelligence\Api\CompetitorRepositoryInterface;
use GrupoAwamotos\MarketingIntelligence\Model\CompetitorAdFactory;
use GrupoAwamotos\MarketingIntelligence\Model\ResourceModel\Competitor\CollectionFactory as CompetitorCollectionFactory;
use GrupoAwamotos\MarketingIntelligence\Model\ResourceModel\CompetitorAd as CompetitorAdResource;
use GrupoAwamotos\MarketingIntelligence\Model\ResourceModel\CompetitorAd\CollectionFactory as AdCollectionFactory;
use Meta\BusinessExtension\Helper\FBEHelper;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Psr\Log\LoggerInterface;

/**
 * Monitors competitor ads via Meta Ad Library API.
 */
class CompetitorMonitor
{
    private const XML_PATH_ENABLED = 'marketing_intelligence/competitors/enabled';
    private const XML_PATH_AD_LIBRARY_TOKEN = 'marketing_intelligence/competitors/ad_library_token';
    private const XML_PATH_SEARCH_TERMS = 'marketing_intelligence/competitors/search_terms';
    private const AD_LIBRARY_ENDPOINT = '/ads_archive';
    private const AD_FIELDS = 'id,page_id,page_name,ad_creative_bodies,ad_creative_link_titles,ad_creative_link_captions,ad_delivery_start_time,ad_delivery_stop_time,publisher_platforms,ad_snapshot_url';
    private const BATCH_LIMIT = 50;

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly FBEHelper $fbeHelper,
        private readonly CompetitorCollectionFactory $competitorCollectionFactory,
        private readonly CompetitorAdFactory $competitorAdFactory,
        private readonly CompetitorAdResource $competitorAdResource,
        private readonly AdCollectionFactory $adCollectionFactory,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Scan all active competitors for new ads.
     *
     * @return int Number of new ads discovered
     */
    public function scanAll(): int
    {
        if (!$this->isEnabled()) {
            $this->logger->info('CompetitorMonitor: disabled via config.');
            return 0;
        }

        $totalAds = 0;

        $competitors = $this->competitorCollectionFactory->create();
        $competitors->addFieldToFilter('is_active', 1);

        foreach ($competitors as $competitor) {
            $pageId = $competitor->getMetaPageId();
            if (empty($pageId)) {
                continue;
            }

            try {
                $ads = $this->fetchAdsForPage(
                    $competitor->getCompetitorId(),
                    $pageId,
                    $competitor->getName()
                );
                $totalAds += $ads;
            } catch (\Exception $e) {
                $this->logger->error(sprintf(
                    'CompetitorMonitor: error scanning competitor %d (%s) — %s',
                    $competitor->getCompetitorId(),
                    $competitor->getName(),
                    $e->getMessage()
                ));
            }
        }

        $searchTerms = $this->getSearchTerms();
        foreach ($searchTerms as $term) {
            try {
                $ads = $this->fetchAdsBySearchTerm($term);
                $totalAds += $ads;
            } catch (\Exception $e) {
                $this->logger->error(sprintf(
                    'CompetitorMonitor: error searching term "%s" — %s',
                    $term,
                    $e->getMessage()
                ));
            }
        }

        $this->logger->info(sprintf('CompetitorMonitor: %d new ads discovered.', $totalAds));
        return $totalAds;
    }

    private function fetchAdsForPage(int $competitorId, string $pageId, string $pageName): int
    {
        $params = [
            'search_page_ids' => json_encode([$pageId], JSON_THROW_ON_ERROR),
            'ad_reached_countries' => json_encode(['BR'], JSON_THROW_ON_ERROR),
            'ad_active_status' => 'ALL',
            'fields' => self::AD_FIELDS,
            'limit' => self::BATCH_LIMIT,
        ];

        $response = $this->fbeHelper->apiGet(self::AD_LIBRARY_ENDPOINT, $params);
        $ads = $response['data'] ?? [];
        $newCount = 0;

        foreach ($ads as $adData) {
            if ($this->saveAd($adData, $competitorId, $pageId, $pageName)) {
                $newCount++;
            }
        }

        return $newCount;
    }

    private function fetchAdsBySearchTerm(string $term): int
    {
        $params = [
            'search_terms' => $term,
            'ad_reached_countries' => json_encode(['BR'], JSON_THROW_ON_ERROR),
            'ad_type' => 'ALL',
            'fields' => self::AD_FIELDS,
            'limit' => self::BATCH_LIMIT,
        ];

        $response = $this->fbeHelper->apiGet(self::AD_LIBRARY_ENDPOINT, $params);
        $ads = $response['data'] ?? [];
        $newCount = 0;

        foreach ($ads as $adData) {
            $pageId = (string)($adData['page_id'] ?? '');
            $pageName = (string)($adData['page_name'] ?? '');
            if ($this->saveAd($adData, null, $pageId, $pageName)) {
                $newCount++;
            }
        }

        return $newCount;
    }

    /**
     * @param array<string, mixed> $adData
     */
    private function saveAd(array $adData, ?int $competitorId, string $pageId, string $pageName): bool
    {
        $metaAdId = (string)($adData['id'] ?? '');
        if (empty($metaAdId)) {
            return false;
        }

        $existing = $this->adCollectionFactory->create();
        $existing->addFieldToFilter('meta_ad_id', $metaAdId);
        if ($existing->getSize() > 0) {
            /** @var \GrupoAwamotos\MarketingIntelligence\Model\CompetitorAd $existingAd */
            $existingAd = $existing->getFirstItem();
            $existingAd->setLastSeenAt(date('Y-m-d H:i:s'));
            $existingAd->setIsActive(!empty($adData['ad_delivery_stop_time']) ? false : true);
            $this->competitorAdResource->save($existingAd);
            return false;
        }

        $ad = $this->competitorAdFactory->create();
        $ad->setCompetitorId($competitorId);
        $ad->setMetaAdId($metaAdId);
        $ad->setPageId($pageId);
        $ad->setPageName($pageName);

        $bodies = $adData['ad_creative_bodies'] ?? [];
        $ad->setAdCreativeBody(is_array($bodies) ? implode("\n", $bodies) : (string)$bodies);

        $titles = $adData['ad_creative_link_titles'] ?? [];
        $ad->setAdCreativeTitle(is_array($titles) ? ($titles[0] ?? '') : (string)$titles);

        $captions = $adData['ad_creative_link_captions'] ?? [];
        $ad->setAdCreativeLink(is_array($captions) ? ($captions[0] ?? '') : (string)$captions);

        $ad->setAdDeliveryStart($adData['ad_delivery_start_time'] ?? null);
        $ad->setAdDeliveryStop($adData['ad_delivery_stop_time'] ?? null);

        $platforms = $adData['publisher_platforms'] ?? [];
        $ad->setPlatforms(is_array($platforms) ? implode(',', $platforms) : (string)$platforms);

        $ad->setIsActive(empty($adData['ad_delivery_stop_time']));
        $ad->setFirstSeenAt(date('Y-m-d H:i:s'));
        $ad->setLastSeenAt(date('Y-m-d H:i:s'));

        try {
            $this->competitorAdResource->save($ad);
            return true;
        } catch (\Exception $e) {
            $this->logger->error(sprintf(
                'CompetitorMonitor: failed saving ad %s — %s',
                $metaAdId,
                $e->getMessage()
            ));
            return false;
        }
    }

    private function isEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_ENABLED);
    }

    /**
     * @return string[]
     */
    private function getSearchTerms(): array
    {
        $value = (string)$this->scopeConfig->getValue(self::XML_PATH_SEARCH_TERMS);
        if (empty($value)) {
            return [];
        }

        return array_filter(
            array_map('trim', explode("\n", $value))
        );
    }
}
