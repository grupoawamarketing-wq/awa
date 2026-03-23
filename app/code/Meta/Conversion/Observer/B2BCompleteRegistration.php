<?php

declare(strict_types=1);

namespace Meta\Conversion\Observer;

use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Meta\BusinessExtension\Api\SystemConfigInterface;
use Meta\BusinessExtension\Helper\GraphAPIAdapter;
use Meta\Conversion\Helper\B2BSignalBuilder;
use Meta\Conversion\Helper\UserDataBuilder;
use Psr\Log\LoggerInterface;

/**
 * Sends a CompleteRegistration event to Meta CAPI after a successful B2B PJ submission.
 */
class B2BCompleteRegistration implements ObserverInterface
{
    public function __construct(
        private readonly SystemConfigInterface $config,
        private readonly GraphAPIAdapter $graphApi,
        private readonly LoggerInterface $logger,
        private readonly B2BSignalBuilder $b2bSignalBuilder,
        private readonly ?UserDataBuilder $userDataBuilder = null
    ) {
    }

    public function execute(Observer $observer): void
    {
        try {
            $customer = $observer->getEvent()->getData('customer');
            if (!$customer instanceof CustomerInterface || !$customer->getId()) {
                return;
            }

            $context = $observer->getEvent()->getData('registration_context');
            if (!is_array($context)) {
                $context = [];
            }

            $personType = strtolower((string) ($context['person_type'] ?? $this->getCustomAttributeValue($customer, 'b2b_person_type')));
            if ($personType !== 'pj') {
                return;
            }

            $storeId = $customer->getStoreId() !== null ? (int) $customer->getStoreId() : null;
            if (!$this->config->isActive($storeId)) {
                return;
            }

            $pixelId = $this->config->getPixelId($storeId);
            if ($pixelId === null) {
                return;
            }

            $customerId = (string) $customer->getId();
            $phone = (string) ($this->getCustomAttributeValue($customer, 'b2b_phone') ?? '');
            $userData = $this->userDataBuilder
                ? $this->userDataBuilder->build((string) $customer->getEmail(), $phone, $customerId)
                : [];
            $eventSourceUrl = $this->userDataBuilder?->getEventSourceUrl();

            $event = [
                'event_name' => 'CompleteRegistration',
                'event_time' => time(),
                'event_id' => sprintf('cr-b2b-%s', $customerId),
                'action_source' => 'website',
                'user_data' => $userData,
                'custom_data' => array_merge($this->b2bSignalBuilder->build($context, $customer), [
                    'cnpj_validated' => (bool) ($context['cnpj_validated'] ?? true)
                ])
            ];

            if ($eventSourceUrl !== null) {
                $event['event_source_url'] = $eventSourceUrl;
            }

            $result = $this->graphApi->sendEvents($pixelId, [$event], $storeId);
            if (isset($result['error'])) {
                $this->logger->warning('[Meta CAPI] CompleteRegistration API error', [
                    'store_id' => $storeId,
                    'customer_id' => $customerId,
                    'http_status' => $result['http_status'] ?? null,
                    'error' => $result['error']
                ]);
            }
        } catch (\Throwable $e) {
            $this->logger->error('[Meta CAPI] CompleteRegistration event failed', [
                'error' => $e->getMessage()
            ]);
        }
    }

    private function getCustomAttributeValue(CustomerInterface $customer, string $attributeCode): ?string
    {
        $attribute = $customer->getCustomAttribute($attributeCode);
        if ($attribute === null) {
            return null;
        }

        $value = trim((string) $attribute->getValue());

        return $value === '' ? null : $value;
    }
}
