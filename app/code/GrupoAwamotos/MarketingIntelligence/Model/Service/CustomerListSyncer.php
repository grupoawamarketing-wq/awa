<?php

declare(strict_types=1);

namespace GrupoAwamotos\MarketingIntelligence\Model\Service;

use GrupoAwamotos\MarketingIntelligence\Api\AudienceRepositoryInterface;
use GrupoAwamotos\MarketingIntelligence\Model\ResourceModel\Audience\CollectionFactory as AudienceCollectionFactory;
use Meta\BusinessExtension\Helper\FBEHelper;
use Magento\Customer\Model\ResourceModel\Customer\CollectionFactory as CustomerCollectionFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Psr\Log\LoggerInterface;

/**
 * Syncs approved B2B customers to a Meta Custom Audience.
 *
 * Uploads EXTERN_ID + SHA256-hashed email + SHA256-hashed phone
 * for all customers in B2B groups (4, 5, 6) with active status.
 *
 * Designed to run periodically via cron or manually from admin.
 */
class CustomerListSyncer
{
    private const XML_PATH_ENABLED = 'marketing_intelligence/meta_audiences/customer_list_sync';
    private const XML_PATH_AD_ACCOUNT_ID = 'marketing_intelligence/meta_audiences/ad_account_id';

    /** @var int[] B2B customer group IDs */
    private const B2B_GROUP_IDS = [4, 5, 6];
    private const BATCH_SIZE = 500;
    private const AUDIENCE_NAME = 'AWA B2B Approved Customers';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly FBEHelper $fbeHelper,
        private readonly CustomerCollectionFactory $customerCollectionFactory,
        private readonly AudienceCollectionFactory $audienceCollectionFactory,
        private readonly AudienceRepositoryInterface $audienceRepository,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Sync all approved B2B customers to Meta Custom Audience.
     *
     * @return array{audience_id: string, customers_uploaded: int, error: string|null}
     */
    public function sync(): array
    {
        $result = [
            'audience_id' => '',
            'customers_uploaded' => 0,
            'error' => null,
        ];

        if (!$this->scopeConfig->isSetFlag(self::XML_PATH_ENABLED)) {
            $result['error'] = 'Customer list sync is disabled';
            return $result;
        }

        try {
            $metaAudienceId = $this->getOrCreateAudience();
            $result['audience_id'] = $metaAudienceId;

            $uploaded = $this->uploadB2BCustomers($metaAudienceId);
            $result['customers_uploaded'] = $uploaded;

            $this->logger->info('CustomerListSyncer: sync complete', [
                'audience_id' => $metaAudienceId,
                'customers_uploaded' => $uploaded,
            ]);
        } catch (\Exception $e) {
            $this->logger->error('CustomerListSyncer: sync failed', [
                'error' => $e->getMessage(),
            ]);
            $result['error'] = $e->getMessage();
        }

        return $result;
    }

    /**
     * Get existing or create the B2B Approved Customers audience.
     */
    private function getOrCreateAudience(): string
    {
        // Check if audience already exists in local DB
        $collection = $this->audienceCollectionFactory->create();
        $collection->addFieldToFilter('name', self::AUDIENCE_NAME);
        $collection->addFieldToFilter('status', 'active');
        $collection->setPageSize(1);

        $existing = $collection->getFirstItem();
        if ($existing && $existing->getMetaAudienceId()) {
            return $existing->getMetaAudienceId();
        }

        // Create new audience on Meta
        $adAccountId = (string) $this->scopeConfig->getValue(self::XML_PATH_AD_ACCOUNT_ID);
        if (empty($adAccountId)) {
            throw new \RuntimeException('Ad Account ID not configured');
        }

        $response = $this->fbeHelper->apiPost(
            sprintf('/%s/customaudiences', $adAccountId),
            [
                'name' => self::AUDIENCE_NAME,
                'subtype' => 'CUSTOM',
                'description' => 'AWA Motos — All approved B2B customers (auto-synced)',
                'customer_file_source' => 'USER_PROVIDED_ONLY',
            ]
        );

        $metaAudienceId = $response['id'] ?? null;
        if (empty($metaAudienceId)) {
            throw new \RuntimeException('Meta API did not return an audience ID');
        }

        $this->logger->info('CustomerListSyncer: created new audience', [
            'meta_audience_id' => $metaAudienceId,
        ]);

        return (string) $metaAudienceId;
    }

    /**
     * Upload hashed B2B customer data to Meta Custom Audience.
     *
     * @return int Number of customers uploaded
     */
    private function uploadB2BCustomers(string $metaAudienceId): int
    {
        $collection = $this->customerCollectionFactory->create();
        $collection->addFieldToFilter('group_id', ['in' => self::B2B_GROUP_IDS]);
        $collection->addAttributeToSelect(['email', 'firstname', 'lastname', 'telefone', 'telephone']);

        $batch = [];
        $totalUploaded = 0;

        foreach ($collection as $customer) {
            $email = $customer->getEmail();
            if (empty($email)) {
                continue;
            }

            $row = [
                (string) $customer->getId(), // EXTERN_ID (plain text)
                hash('sha256', mb_strtolower(trim($email))),
            ];

            // Add phone hash if available
            $phone = $customer->getData('telefone') ?: $customer->getData('telephone');
            if (!empty($phone)) {
                $digits = preg_replace('/\D/', '', (string) $phone);
                if (!empty($digits)) {
                    $row[] = hash('sha256', $digits);
                } else {
                    $row[] = '';
                }
            } else {
                $row[] = '';
            }

            // Add first name hash
            $firstName = $customer->getFirstname();
            if (!empty($firstName)) {
                $row[] = hash('sha256', mb_strtolower(trim($firstName)));
            } else {
                $row[] = '';
            }

            // Add last name hash
            $lastName = $customer->getLastname();
            if (!empty($lastName)) {
                $row[] = hash('sha256', mb_strtolower(trim($lastName)));
            } else {
                $row[] = '';
            }

            $batch[] = $row;
            $totalUploaded++;

            if (count($batch) >= self::BATCH_SIZE) {
                $this->sendBatch($metaAudienceId, $batch);
                $batch = [];
            }
        }

        if (!empty($batch)) {
            $this->sendBatch($metaAudienceId, $batch);
        }

        return $totalUploaded;
    }

    /**
     * Send a batch of hashed customer data to Meta.
     *
     * @param array<int, array<int, string>> $batch
     */
    private function sendBatch(string $metaAudienceId, array $batch): void
    {
        $payload = [
            'payload' => json_encode([
                'schema' => ['EXTERN_ID', 'EMAIL', 'PHONE', 'FN', 'LN'],
                'data' => $batch,
            ], JSON_THROW_ON_ERROR),
        ];

        $response = $this->fbeHelper->apiPost(
            sprintf('/%s/users', $metaAudienceId),
            $payload
        );

        $this->logger->debug('CustomerListSyncer: batch uploaded', [
            'audience_id' => $metaAudienceId,
            'batch_size' => count($batch),
            'num_received' => $response['num_received'] ?? 0,
        ]);
    }
}
