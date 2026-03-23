<?php

declare(strict_types=1);

namespace GrupoAwamotos\MarketingIntelligence\Model\Service;

use Meta\BusinessExtension\Helper\FBEHelper;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;
use Psr\Log\LoggerInterface;

/**
 * Uploads offline conversion events (ERP orders) to Meta CAPI via Offline Event Sets.
 */
class OfflineConversionUploader
{
    private const XML_PATH_ENABLED = 'marketing_intelligence/offline_conversions/enabled';
    private const XML_PATH_EVENT_SET_ID = 'marketing_intelligence/offline_conversions/event_set_id';
    private const XML_PATH_INCLUDE_ERP = 'marketing_intelligence/offline_conversions/include_erp_orders';
    private const BATCH_SIZE = 100;

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly FBEHelper $fbeHelper,
        private readonly OrderCollectionFactory $orderCollectionFactory,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Upload recent completed orders as offline conversion events.
     *
     * @return int Number of events uploaded
     */
    public function uploadRecentOrders(): int
    {
        if (!$this->isEnabled()) {
            $this->logger->info('OfflineConversionUploader: disabled via config.');
            return 0;
        }

        $eventSetId = $this->getEventSetId();
        if (empty($eventSetId)) {
            $this->logger->error('OfflineConversionUploader: event_set_id not configured.');
            return 0;
        }

        $orders = $this->getRecentCompletedOrders();
        $events = [];
        $uploaded = 0;

        foreach ($orders as $order) {
            $event = $this->buildEvent($order);
            if ($event === null) {
                continue;
            }

            $events[] = $event;

            if (count($events) >= self::BATCH_SIZE) {
                $uploaded += $this->sendBatch($eventSetId, $events);
                $events = [];
            }
        }

        if (!empty($events)) {
            $uploaded += $this->sendBatch($eventSetId, $events);
        }

        $this->logger->info(sprintf('OfflineConversionUploader: %d events uploaded.', $uploaded));
        return $uploaded;
    }

    /**
     * @param \Magento\Sales\Model\Order $order
     * @return array<string, mixed>|null
     */
    private function buildEvent(\Magento\Sales\Model\Order $order): ?array
    {
        $email = $order->getCustomerEmail();
        if (empty($email)) {
            return null;
        }

        $billingAddress = $order->getBillingAddress();

        $matchKeys = [
            'em' => hash('sha256', mb_strtolower(trim($email))),
        ];

        if ($billingAddress !== null) {
            $phone = $billingAddress->getTelephone();
            if (!empty($phone)) {
                $digits = preg_replace('/\D/', '', $phone);
                if (!empty($digits)) {
                    $matchKeys['ph'] = hash('sha256', $digits);
                }
            }

            $firstName = $billingAddress->getFirstname();
            if (!empty($firstName)) {
                $matchKeys['fn'] = hash('sha256', mb_strtolower(trim($firstName)));
            }

            $lastName = $billingAddress->getLastname();
            if (!empty($lastName)) {
                $matchKeys['ln'] = hash('sha256', mb_strtolower(trim($lastName)));
            }

            $city = $billingAddress->getCity();
            if (!empty($city)) {
                $matchKeys['ct'] = hash('sha256', mb_strtolower(trim($city)));
            }

            $region = $billingAddress->getRegionCode();
            if (!empty($region)) {
                $matchKeys['st'] = hash('sha256', mb_strtolower(trim($region)));
            }

            $postcode = $billingAddress->getPostcode();
            if (!empty($postcode)) {
                $matchKeys['zp'] = hash('sha256', preg_replace('/\D/', '', $postcode));
            }

            $matchKeys['country'] = hash('sha256', 'br');
        }

        if ($order->getCustomerId()) {
            $matchKeys['external_id'] = hash('sha256', (string)$order->getCustomerId());
        }

        $createdAt = $order->getCreatedAt();
        $timestamp = $createdAt ? strtotime($createdAt) : time();

        return [
            'match_keys' => $matchKeys,
            'currency' => $order->getOrderCurrencyCode() ?: 'BRL',
            'value' => (float)$order->getGrandTotal(),
            'event_name' => 'Purchase',
            'event_time' => $timestamp,
            'order_id' => $order->getIncrementId(),
            'content_type' => 'product',
            'contents' => $this->buildContents($order),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildContents(\Magento\Sales\Model\Order $order): array
    {
        $contents = [];
        foreach ($order->getAllVisibleItems() as $item) {
            $contents[] = [
                'id' => $item->getSku(),
                'quantity' => (int)$item->getQtyOrdered(),
                'item_price' => (float)$item->getPrice(),
            ];
        }
        return $contents;
    }

    /**
     * @param array<int, array<string, mixed>> $events
     */
    private function sendBatch(string $eventSetId, array $events): int
    {
        try {
            $response = $this->fbeHelper->apiPost(
                sprintf('/%s/events', $eventSetId),
                [
                    'upload_tag' => 'awa_mktg_intelligence_' . date('Ymd_His'),
                    'data' => json_encode($events, JSON_THROW_ON_ERROR),
                ]
            );

            $numProcessed = (int)($response['num_processed_entries'] ?? count($events));
            return $numProcessed;
        } catch (\Exception $e) {
            $this->logger->error(sprintf(
                'OfflineConversionUploader: batch upload failed — %s',
                $e->getMessage()
            ));
            return 0;
        }
    }

    /**
     * Get orders completed in the last 6 hours that haven't been synced yet.
     * Uses a custom flag 'mktg_offline_synced' to avoid resending.
     */
    private function getRecentCompletedOrders(): \Magento\Sales\Model\ResourceModel\Order\Collection
    {
        $since = date('Y-m-d H:i:s', strtotime('-6 hours'));

        $collection = $this->orderCollectionFactory->create();
        $collection->addFieldToFilter('state', ['in' => ['complete', 'processing']]);
        $collection->addFieldToFilter('updated_at', ['gteq' => $since]);

        if ($this->includeErpOrders()) {
            $collection->addFieldToFilter(
                ['erp_synced', 'erp_synced'],
                [['eq' => 1], ['null' => true]]
            );
        }

        $collection->setPageSize(500);
        return $collection;
    }

    private function isEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_ENABLED);
    }

    private function getEventSetId(): string
    {
        return (string)$this->scopeConfig->getValue(self::XML_PATH_EVENT_SET_ID);
    }

    private function includeErpOrders(): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_INCLUDE_ERP);
    }
}
