<?php

declare(strict_types=1);

namespace GrupoAwamotos\MarketingIntelligence\Block\Adminhtml;

use GrupoAwamotos\MarketingIntelligence\Model\ResourceModel\Prospect\CollectionFactory as ProspectCollectionFactory;
use GrupoAwamotos\MarketingIntelligence\Model\ResourceModel\Audience\CollectionFactory as AudienceCollectionFactory;
use GrupoAwamotos\MarketingIntelligence\Model\ResourceModel\Competitor\CollectionFactory as CompetitorCollectionFactory;
use GrupoAwamotos\MarketingIntelligence\Model\ResourceModel\CompetitorAd\CollectionFactory as CompetitorAdCollectionFactory;
use GrupoAwamotos\MarketingIntelligence\Model\ResourceModel\CampaignInsight\CollectionFactory as InsightCollectionFactory;
use GrupoAwamotos\MarketingIntelligence\Model\Service\BudgetAttributionService;
use GrupoAwamotos\MarketingIntelligence\Model\Service\AlertService;
use GrupoAwamotos\MarketingIntelligence\Model\Service\DatasetQualityService;
use GrupoAwamotos\MarketingIntelligence\Model\Service\GrowthExperimentsService;
use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\App\Config\ScopeConfigInterface;

class Dashboard extends Template
{
    /**
     * @var string
     */
    protected $_template = 'GrupoAwamotos_MarketingIntelligence::dashboard.phtml';

    public function __construct(
        Context $context,
        private readonly ProspectCollectionFactory $prospectCollectionFactory,
        private readonly AudienceCollectionFactory $audienceCollectionFactory,
        private readonly CompetitorCollectionFactory $competitorCollectionFactory,
        private readonly CompetitorAdCollectionFactory $competitorAdCollectionFactory,
        private readonly InsightCollectionFactory $insightCollectionFactory,
        private readonly BudgetAttributionService $budgetAttribution,
        private readonly AlertService $alertService,
        private readonly DatasetQualityService $datasetQualityService,
        private readonly GrowthExperimentsService $growthExperimentsService,
        private readonly ScopeConfigInterface $scopeConfig,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Get KPI summary for dashboard cards
     *
     * @return array<string, array{label: string, value: int|string, icon: string, color: string}>
     */
    public function getKpis(): array
    {
        $prospects = $this->prospectCollectionFactory->create();
        $audiences = $this->audienceCollectionFactory->create();
        $competitors = $this->competitorCollectionFactory->create();
        $ads = $this->competitorAdCollectionFactory->create();

        $totalProspects = $prospects->getSize();
        $hotProspects = $this->prospectCollectionFactory->create()
            ->addFieldToFilter('prospect_score', ['gteq' => 70])
            ->addFieldToFilter('prospect_status', 'new')
            ->getSize();
        $totalAudiences = $audiences->getSize();
        $totalAds = $ads->getSize();
        $activeCompetitors = $this->competitorCollectionFactory->create()
            ->addFieldToFilter('is_active', 1)
            ->getSize();
        $convertedProspects = $this->prospectCollectionFactory->create()
            ->addFieldToFilter('prospect_status', 'converted')
            ->getSize();

        $conversionRate = $totalProspects > 0
            ? round(($convertedProspects / $totalProspects) * 100, 1)
            : 0;

        return [
            'total_prospects' => [
                'label' => 'Total Prospects',
                'value' => $totalProspects,
                'icon' => '🏢',
                'color' => '#2563eb',
            ],
            'hot_prospects' => [
                'label' => 'Prospects Quentes (70+)',
                'value' => $hotProspects,
                'icon' => '🔥',
                'color' => '#dc2626',
            ],
            'audiences' => [
                'label' => 'Audiências Meta',
                'value' => $totalAudiences,
                'icon' => '👥',
                'color' => '#7c3aed',
            ],
            'competitor_ads' => [
                'label' => 'Anúncios Concorrentes',
                'value' => $totalAds,
                'icon' => '📊',
                'color' => '#d97706',
            ],
            'active_competitors' => [
                'label' => 'Concorrentes Monitorados',
                'value' => $activeCompetitors,
                'icon' => '👁️',
                'color' => '#059669',
            ],
            'conversion_rate' => [
                'label' => 'Taxa de Conversão',
                'value' => $conversionRate . '%',
                'icon' => '📈',
                'color' => '#0891b2',
            ],
        ];
    }

    /**
     * Get prospect score distribution for chart
     *
     * @return array<string, int>
     */
    public function getScoreDistribution(): array
    {
        $ranges = [
            '0-20' => [0, 20],
            '21-40' => [21, 40],
            '41-60' => [41, 60],
            '61-80' => [61, 80],
            '81-100' => [81, 100],
        ];

        $distribution = [];
        foreach ($ranges as $label => [$min, $max]) {
            $collection = $this->prospectCollectionFactory->create();
            $collection->addFieldToFilter('prospect_score', ['gteq' => $min]);
            $collection->addFieldToFilter('prospect_score', ['lteq' => $max]);
            $distribution[$label] = $collection->getSize();
        }

        return $distribution;
    }

    /**
     * Get prospect status breakdown
     *
     * @return array<string, int>
     */
    public function getStatusBreakdown(): array
    {
        $statuses = ['new', 'contacted', 'interested', 'converted', 'rejected'];
        $breakdown = [];

        foreach ($statuses as $status) {
            $collection = $this->prospectCollectionFactory->create();
            $collection->addFieldToFilter('prospect_status', $status);
            $breakdown[$status] = $collection->getSize();
        }

        return $breakdown;
    }

    /**
     * Get top prospects by score
     *
     * @param int $limit
     * @return array<int, array{cnpj: string, nome_fantasia: string, uf: string, score: int, status: string}>
     */
    public function getTopProspects(int $limit = 10): array
    {
        $collection = $this->prospectCollectionFactory->create();
        $collection->addFieldToFilter('prospect_status', ['neq' => 'rejected']);
        $collection->setOrder('prospect_score', 'DESC');
        $collection->setPageSize($limit);

        $prospects = [];
        foreach ($collection as $prospect) {
            $prospects[] = [
                'cnpj' => $prospect->getData('cnpj'),
                'nome_fantasia' => $prospect->getData('nome_fantasia') ?: $prospect->getData('razao_social'),
                'uf' => $prospect->getData('uf'),
                'score' => (int) $prospect->getData('prospect_score'),
                'status' => $prospect->getData('prospect_status'),
                'cnae_profile' => $prospect->getData('cnae_profile'),
            ];
        }

        return $prospects;
    }

    /**
     * Get top UFs by prospect count
     *
     * @return array<string, int>
     */
    public function getProspectsByUf(): array
    {
        $collection = $this->prospectCollectionFactory->create();
        $collection->getSelect()
            ->reset(\Magento\Framework\DB\Select::COLUMNS)
            ->columns(['uf', 'cnt' => new \Zend_Db_Expr('COUNT(*)')])
            ->group('uf')
            ->order('cnt DESC')
            ->limit(10);

        $result = [];
        foreach ($collection as $row) {
            $uf = $row->getData('uf');
            if ($uf) {
                $result[$uf] = (int) $row->getData('cnt');
            }
        }

        return $result;
    }

    /**
     * Get recent competitor ads
     *
     * @param int $limit
     * @return array<int, array{page_name: string, title: string, start: string|null, active: bool}>
     */
    public function getRecentAds(int $limit = 5): array
    {
        $collection = $this->competitorAdCollectionFactory->create();
        $collection->setOrder('first_seen_at', 'DESC');
        $collection->setPageSize($limit);

        $ads = [];
        foreach ($collection as $ad) {
            $ads[] = [
                'page_name' => $ad->getData('page_name'),
                'title' => $ad->getData('ad_creative_title'),
                'start' => $ad->getData('ad_delivery_start'),
                'active' => (bool) $ad->getData('is_active'),
            ];
        }

        return $ads;
    }

    /**
     * Get chart data as JSON for JavaScript
     *
     * @return string
     */
    public function getScoreChartJson(): string
    {
        $distribution = $this->getScoreDistribution();

        $data = [
            'labels' => array_keys($distribution),
            'datasets' => [
                [
                    'label' => 'Prospects por Score',
                    'data' => array_values($distribution),
                    'backgroundColor' => ['#ef4444', '#f97316', '#eab308', '#22c55e', '#059669'],
                ],
            ],
        ];

        return (string) json_encode($data, JSON_THROW_ON_ERROR);
    }

    /**
     * Get UF chart data as JSON
     *
     * @return string
     */
    public function getUfChartJson(): string
    {
        $byUf = $this->getProspectsByUf();

        $data = [
            'labels' => array_keys($byUf),
            'datasets' => [
                [
                    'label' => 'Prospects por UF',
                    'data' => array_values($byUf),
                    'backgroundColor' => '#2563eb',
                ],
            ],
        ];

        return (string) json_encode($data, JSON_THROW_ON_ERROR);
    }

    /**
     * Check if module is enabled
     *
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag('marketing_intelligence/general/enabled');
    }

    /**
     * Get fetch prospects URL
     *
     * @return string
     */
    public function getFetchProspectsUrl(): string
    {
        return $this->getUrl('marketingintelligence/prospects/fetch');
    }

    /**
     * Get sync audiences URL
     *
     * @return string
     */
    public function getSyncAudiencesUrl(): string
    {
        return $this->getUrl('marketingintelligence/audiences/sync');
    }

    /**
     * Get scan competitors URL
     *
     * @return string
     */
    public function getScanCompetitorsUrl(): string
    {
        return $this->getUrl('marketingintelligence/competitors/scan');
    }

    /**
     * Get status label with color
     *
     * @param string $status
     * @return array{label: string, color: string}
     */
    public function getStatusDisplay(string $status): array
    {
        $map = [
            'new' => ['label' => 'Novo', 'color' => '#2563eb'],
            'contacted' => ['label' => 'Contatado', 'color' => '#d97706'],
            'interested' => ['label' => 'Interessado', 'color' => '#7c3aed'],
            'converted' => ['label' => 'Convertido', 'color' => '#059669'],
            'rejected' => ['label' => 'Rejeitado', 'color' => '#dc2626'],
        ];

        return $map[$status] ?? ['label' => ucfirst($status), 'color' => '#6b7280'];
    }

    /**
     * Get budget attribution breakdown for last 30 days.
     *
     * @return array<string, array{spend: float, impressions: int, clicks: int, roas: float|null, pct: float}>
     */
    public function getBudgetAttribution(): array
    {
        $since = date('Y-m-d', strtotime('-30 days'));
        $until = date('Y-m-d');

        return $this->budgetAttribution->getAttribution($since, $until);
    }

    /**
     * Get budget attribution chart JSON.
     *
     * @return string
     */
    public function getBudgetChartJson(): string
    {
        $attribution = $this->getBudgetAttribution();

        $labels = [];
        $values = [];
        $colors = [
            'b2b' => '#2563eb',
            'remarketing' => '#7c3aed',
            'branding' => '#d97706',
            'b2c' => '#059669',
            'unclassified' => '#9ca3af',
        ];

        foreach ($attribution as $classification => $data) {
            $labels[] = ucfirst($classification);
            $values[] = $data['spend'];
        }

        $bgColors = [];
        foreach ($attribution as $classification => $data) {
            $bgColors[] = $colors[$classification] ?? '#6b7280';
        }

        $chartData = [
            'labels' => $labels,
            'datasets' => [
                [
                    'data' => $values,
                    'backgroundColor' => $bgColors,
                ],
            ],
        ];

        return (string) json_encode($chartData, JSON_THROW_ON_ERROR);
    }

    /**
     * Get B2B funnel data.
     *
     * @return array<string, array{label: string, count: int, color: string}>
     */
    public function getB2BFunnel(): array
    {
        $prospectCollection = $this->prospectCollectionFactory->create();
        $totalProspects = $prospectCollection->getSize();

        $contacted = $this->prospectCollectionFactory->create()
            ->addFieldToFilter('prospect_status', ['in' => ['contacted', 'interested', 'converted']])
            ->getSize();

        $interested = $this->prospectCollectionFactory->create()
            ->addFieldToFilter('prospect_status', ['in' => ['interested', 'converted']])
            ->getSize();

        $converted = $this->prospectCollectionFactory->create()
            ->addFieldToFilter('prospect_status', 'converted')
            ->getSize();

        return [
            'prospects' => ['label' => 'Prospects Captados', 'count' => $totalProspects, 'color' => '#2563eb'],
            'contacted' => ['label' => 'Contatados', 'count' => $contacted, 'color' => '#d97706'],
            'interested' => ['label' => 'Interessados', 'count' => $interested, 'color' => '#7c3aed'],
            'converted' => ['label' => 'Convertidos', 'count' => $converted, 'color' => '#059669'],
        ];
    }

    /**
     * Get active alerts.
     *
     * @return array<int, array{type: string, severity: string, message: string, value: float|int|string, threshold: float|int|string}>
     */
    public function getAlerts(): array
    {
        return $this->alertService->checkAlerts();
    }

    /**
     * Get insights summary by classification for last 30 days.
     *
     * @return array<string, array{campaigns: int, spend: float, impressions: int, clicks: int}>
     */
    public function getInsightsSummary(): array
    {
        $since = date('Y-m-d', strtotime('-30 days'));

        $collection = $this->insightCollectionFactory->create();
        $collection->addFieldToFilter('date_start', ['gteq' => $since]);
        $select = $collection->getSelect();
        $select->reset(\Magento\Framework\DB\Select::COLUMNS);
        $select->columns([
            'classification' => 'COALESCE(classification, \'unclassified\')',
            'campaigns' => 'COUNT(DISTINCT campaign_id)',
            'total_spend' => 'SUM(spend)',
            'total_impressions' => 'SUM(impressions)',
            'total_clicks' => 'SUM(clicks)',
        ]);
        $select->group('classification');

        $result = [];
        foreach ($collection->getConnection()->fetchAll($select) as $row) {
            $cls = (string) $row['classification'];
            $result[$cls] = [
                'campaigns' => (int) $row['campaigns'],
                'spend' => (float) $row['total_spend'],
                'impressions' => (int) $row['total_impressions'],
                'clicks' => (int) $row['total_clicks'],
            ];
        }

        return $result;
    }

    /**
     * Get create B2B segments URL.
     *
     * @return string
     */
    public function getCreateSegmentsUrl(): string
    {
        return $this->getUrl('marketingintelligence/audiences/createsegments');
    }

    /**
     * Get CNAE profile label
     *
     * @param string $profile
     * @return array{label: string, color: string}
     */
    public function getProfileDisplay(string $profile): array
    {
        $map = [
            'direct' => ['label' => 'Direto', 'color' => '#059669'],
            'adjacent' => ['label' => 'Adjacente', 'color' => '#d97706'],
            'off_profile' => ['label' => 'Fora do Perfil', 'color' => '#6b7280'],
        ];

        return $map[$profile] ?? ['label' => ucfirst($profile), 'color' => '#6b7280'];
    }

    /**
     * Get signal quality dashboard summary from Meta API.
     *
     * @return array{score: float, status: string, events: array, freshness: string}|null
     */
    public function getSignalQuality(): ?array
    {
        if (!$this->scopeConfig->isSetFlag('marketing_intelligence/signal_quality/enabled')) {
            return null;
        }

        try {
            return $this->datasetQualityService->getDashboardSummary();
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get growth experiment suggestions for B2B campaigns.
     *
     * @return array<int, array{id: string, name: string, description: string, segment: string, audience_size: int, priority: string, meta_objective: string, status: string}>
     */
    public function getGrowthExperiments(): array
    {
        try {
            return $this->growthExperimentsService->getExperiments();
        } catch (\Exception $e) {
            return [];
        }
    }
}
