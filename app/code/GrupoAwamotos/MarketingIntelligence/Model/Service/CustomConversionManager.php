<?php

declare(strict_types=1);

namespace GrupoAwamotos\MarketingIntelligence\Model\Service;

use Meta\BusinessExtension\Helper\FBEHelper;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Psr\Log\LoggerInterface;

/**
 * Creates and manages Meta Custom Conversions for B2B events.
 */
class CustomConversionManager
{
    private const XML_PATH_AD_ACCOUNT_ID = 'marketing_intelligence/meta_audiences/ad_account_id';

    /**
     * Pre-defined B2B custom conversion rules.
     */
    private const B2B_CONVERSIONS = [
        'b2b_approved' => [
            'name' => 'AWA — B2B Approved',
            'event_name' => 'SubmitApplication',
            'rule' => '{"and":[{"event_name":{"eq":"SubmitApplication"}},{"custom_data.approval_status":{"eq":"approved"}}]}',
        ],
        'b2b_quote_submitted' => [
            'name' => 'AWA — B2B Quote Submitted',
            'event_name' => 'Lead',
            'rule' => '{"and":[{"event_name":{"eq":"Lead"}},{"custom_data.lead_type":{"eq":"b2b_quote_request"}}]}',
        ],
        'b2b_purchase' => [
            'name' => 'AWA — B2B Purchase',
            'event_name' => 'Purchase',
            'rule' => '{"and":[{"event_name":{"eq":"Purchase"}},{"custom_data.business_model":{"eq":"b2b"}}]}',
        ],
        'b2c_purchase' => [
            'name' => 'AWA — B2C Purchase',
            'event_name' => 'Purchase',
            'rule' => '{"and":[{"event_name":{"eq":"Purchase"}},{"custom_data.person_type":{"eq":"pf"}}]}',
        ],
    ];

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly FBEHelper $fbeHelper,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Create all pre-defined B2B custom conversions that don't exist yet.
     *
     * @return array<string, array{id: string, name: string}> Created conversions
     */
    public function syncConversions(): array
    {
        $adAccountId = $this->getAdAccountId();
        $existing = $this->listExisting($adAccountId);
        $created = [];

        foreach (self::B2B_CONVERSIONS as $key => $definition) {
            if (isset($existing[$definition['name']])) {
                $this->logger->info(sprintf(
                    'CustomConversionManager: "%s" already exists (ID: %s), skipping.',
                    $definition['name'],
                    $existing[$definition['name']]
                ));
                continue;
            }

            try {
                $id = $this->createConversion($adAccountId, $definition);
                $created[$key] = ['id' => $id, 'name' => $definition['name']];
                $this->logger->info(sprintf(
                    'CustomConversionManager: created "%s" (ID: %s).',
                    $definition['name'],
                    $id
                ));
            } catch (\Exception $e) {
                $this->logger->error(sprintf(
                    'CustomConversionManager: failed to create "%s" — %s',
                    $definition['name'],
                    $e->getMessage()
                ));
            }
        }

        return $created;
    }

    /**
     * List existing custom conversions on the ad account.
     *
     * @return array<string, string> name => id map
     */
    private function listExisting(string $adAccountId): array
    {
        $response = $this->fbeHelper->apiGet(
            sprintf('/%s/customconversions', $adAccountId),
            ['fields' => 'id,name', 'limit' => 100]
        );

        $map = [];
        foreach ($response['data'] ?? [] as $cc) {
            $map[$cc['name']] = $cc['id'];
        }

        return $map;
    }

    /**
     * @param array{name: string, event_name: string, rule: string} $definition
     */
    private function createConversion(string $adAccountId, array $definition): string
    {
        $response = $this->fbeHelper->apiPost(
            sprintf('/%s/customconversions', $adAccountId),
            [
                'name' => $definition['name'],
                'event_source_id' => $this->getPixelId(),
                'custom_event_type' => 'OTHER',
                'rule' => $definition['rule'],
            ]
        );

        $id = $response['id'] ?? null;
        if (empty($id)) {
            throw new \RuntimeException('Meta API did not return a custom conversion ID.');
        }

        return (string) $id;
    }

    private function getAdAccountId(): string
    {
        $id = (string) $this->scopeConfig->getValue(self::XML_PATH_AD_ACCOUNT_ID);
        if (empty($id)) {
            throw new \RuntimeException('Meta Ad Account ID not configured.');
        }

        return str_starts_with($id, 'act_') ? $id : 'act_' . $id;
    }

    private function getPixelId(): string
    {
        $pixelId = (string) $this->scopeConfig->getValue('marketing_intelligence/general/pixel_id');
        if (empty($pixelId)) {
            $pixelId = (string) $this->scopeConfig->getValue('facebook/business_extension/pixel_id');
        }

        if (empty($pixelId)) {
            throw new \RuntimeException('Meta Pixel ID not configured.');
        }

        return $pixelId;
    }
}
