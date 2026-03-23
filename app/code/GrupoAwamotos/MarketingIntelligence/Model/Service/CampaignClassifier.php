<?php

declare(strict_types=1);

namespace GrupoAwamotos\MarketingIntelligence\Model\Service;

use GrupoAwamotos\MarketingIntelligence\Model\ResourceModel\CampaignInsight\CollectionFactory;
use GrupoAwamotos\MarketingIntelligence\Model\ResourceModel\CampaignInsight as CampaignInsightResource;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Psr\Log\LoggerInterface;

/**
 * Classifies campaigns as B2B, B2C, remarketing, or branding based on naming conventions.
 */
class CampaignClassifier
{
    private const XML_PATH_AUTO_CLASSIFY = 'marketing_intelligence/insights_api/auto_classify';

    /** @var array<string, list<string>> */
    private const CLASSIFICATION_RULES = [
        'b2b' => ['b2b', 'cnpj', 'empresa', 'atacado', 'revenda', 'distribuidor', 'lojista'],
        'remarketing' => ['retarget', 'remarketing', 'lookalike', 'remarket', 'rmkt'],
        'branding' => ['awareness', 'brand', 'alcance', 'reach', 'conhecimento'],
    ];

    public function __construct(
        private readonly CollectionFactory $collectionFactory,
        private readonly CampaignInsightResource $insightResource,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Classify all unclassified insights.
     *
     * @return int Number of insights classified
     */
    public function classifyAll(): int
    {
        if (!$this->scopeConfig->isSetFlag(self::XML_PATH_AUTO_CLASSIFY)) {
            $this->logger->info('CampaignClassifier: auto_classify disabled.');
            return 0;
        }

        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter('classification', [
            ['null' => true],
            ['eq' => ''],
        ]);

        $classified = 0;

        foreach ($collection as $insight) {
            $label = $this->classify(
                (string) $insight->getCampaignName(),
                (string) $insight->getAdsetName(),
                (string) $insight->getAdName()
            );

            $insight->setClassification($label);
            $insight->setClassifiedBy('auto_rules');

            try {
                $this->insightResource->save($insight);
                $classified++;
            } catch (\Exception $e) {
                $this->logger->error('CampaignClassifier: save failed.', [
                    'insight_id' => $insight->getInsightId(),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->logger->info("CampaignClassifier: classified {$classified} insights.");
        return $classified;
    }

    /**
     * Classify a single campaign based on name/adset/ad.
     */
    public function classify(string $campaignName, string $adsetName, string $adName): string
    {
        $haystack = mb_strtolower($campaignName . ' ' . $adsetName . ' ' . $adName);

        foreach (self::CLASSIFICATION_RULES as $label => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($haystack, $keyword)) {
                    return $label;
                }
            }
        }

        return 'b2c';
    }
}
