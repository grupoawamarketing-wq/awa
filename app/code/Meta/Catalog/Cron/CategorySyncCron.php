<?php

declare(strict_types=1);

namespace Meta\Catalog\Cron;

use JsonException;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory;
use Magento\Store\Model\StoreManagerInterface;
use Meta\BusinessExtension\Api\SystemConfigInterface;
use Meta\BusinessExtension\Helper\FBEHelper;
use Psr\Log\LoggerInterface;

/**
 * Cron job for daily category sync to Meta catalog (2am)
 */
class CategorySyncCron
{
    public function __construct(
        private readonly SystemConfigInterface $config,
        private readonly FBEHelper $fbeHelper,
        private readonly CollectionFactory $collectionFactory,
        private readonly LoggerInterface $logger,
        private readonly ?StoreManagerInterface $storeManager = null
    ) {
    }

    public function execute(): void
    {
        $storeId = $this->resolveStoreId();
        if (!$this->config->isActive($storeId)) {
            return;
        }

        $catalogId = $this->config->getCatalogId($storeId);
        if ($catalogId === null) {
            return;
        }

        $this->logger->info('[Meta Cron] Starting category sync', [
            'store_id' => $storeId
        ]);

        try {
            $collection = $this->collectionFactory->create();
            if ($storeId !== null) {
                $collection->setStoreId($storeId);
            }
            $collection->addAttributeToSelect(['name', 'url_path', 'image', 'is_active'])
                ->addFieldToFilter('is_active', 1)
                ->addFieldToFilter('level', ['gt' => 1]);

            $categories = [];
            foreach ($collection as $category) {
                $categoryId = (int) $category->getId();
                if ($categoryId <= 0) {
                    continue;
                }

                $name = trim((string) $category->getName());
                if ($name === '') {
                    continue;
                }

                $url = trim((string) $category->getUrl());
                if ($url === '') {
                    continue;
                }

                $categories[] = [
                    'name' => $name,
                    'categorization_criteria' => 'CATEGORY',
                    'criteria_value' => $name
                ];
            }

            if (!empty($categories)) {
                $endpoint = $catalogId . '/categories';
                foreach (array_chunk($categories, 50) as $index => $batch) {
                    $result = $this->fbeHelper->apiPost($endpoint, [
                        'data' => json_encode(
                            $batch,
                            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                        )
                    ], $storeId);

                    if ($this->isPermissionDenied($result)) {
                        $this->logger->info('[Meta Cron] Category sync skipped: app has no permission for /categories', [
                            'store_id' => $storeId,
                            'batch_index' => $index + 1,
                            'http_status' => $result['http_status'] ?? null,
                            'error' => $result['error'] ?? null
                        ]);
                        break;
                    }

                    if (isset($result['error'])) {
                        $this->logger->warning('[Meta Cron] Category batch API error', [
                            'store_id' => $storeId,
                            'batch_index' => $index + 1,
                            'batch_size' => count($batch),
                            'http_status' => $result['http_status'] ?? null,
                            'error' => $result['error']
                        ]);
                    }
                }
            }

            $this->logger->info('[Meta Cron] Category sync completed', [
                'store_id' => $storeId,
                'count' => count($categories)
            ]);
        } catch (JsonException $e) {
            $this->logger->error('[Meta Cron] Category payload encode failed', [
                'store_id' => $storeId,
                'error' => $e->getMessage()
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('[Meta Cron] Category sync failed', [
                'store_id' => $storeId,
                'error' => $e->getMessage()
            ]);
        }
    }

    private function resolveStoreId(): ?int
    {
        if ($this->storeManager === null) {
            return null;
        }

        try {
            foreach ($this->storeManager->getStores(true) as $store) {
                $storeId = (int) $store->getId();
                if ($storeId > 0) {
                    return $storeId;
                }
            }
        } catch (\Throwable) {
            return null;
        }

        return null;
    }

    /**
     * @param array<string, mixed> $result
     */
    private function isPermissionDenied(array $result): bool
    {
        $error = $result['error'] ?? null;
        if (!is_array($error)) {
            return false;
        }

        return (int) ($error['code'] ?? 0) === 10;
    }
}
