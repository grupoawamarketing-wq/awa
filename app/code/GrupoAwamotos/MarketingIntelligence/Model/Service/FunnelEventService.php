<?php

declare(strict_types=1);

namespace GrupoAwamotos\MarketingIntelligence\Model\Service;

use Meta\BusinessExtension\Helper\FBEHelper;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Psr\Log\LoggerInterface;

/**
 * Sends server-side B2B funnel events to Meta Conversions API.
 *
 * Covers intermediate funnel stages that are NOT purchase events:
 * QualifiedLead, ProposalSent, CreditApproved, InvoiceConfirmed, B2BRepurchase.
 */
class FunnelEventService
{
    private const XML_PATH_ENABLED = 'marketing_intelligence/funnel_events/enabled';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly FBEHelper $fbeHelper,
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Send a single server-side CAPI event for a B2B funnel stage.
     *
     * @param string $eventName e.g. QualifiedLead, ProposalSent, CreditApproved
     * @param int $customerId Magento customer ID
     * @param array<string, mixed> $customData Extra custom_data fields
     * @return bool Whether the event was sent successfully
     */
    public function sendFunnelEvent(string $eventName, int $customerId, array $customData = []): bool
    {
        if (!$this->isEnabled()) {
            return false;
        }

        $pixelId = $this->getPixelId();
        if (empty($pixelId)) {
            $this->logger->error('FunnelEventService: pixel_id not configured.');
            return false;
        }

        try {
            $userData = $this->buildUserData($customerId);
            if ($userData === null) {
                $this->logger->warning(sprintf(
                    'FunnelEventService: cannot build user_data for customer #%d — skipping %s.',
                    $customerId,
                    $eventName
                ));
                return false;
            }

            $eventId = $this->generateEventId($eventName, $customerId);

            $event = [
                'event_name' => $eventName,
                'event_time' => time(),
                'event_id' => $eventId,
                'action_source' => 'system_generated',
                'user_data' => $userData,
                'custom_data' => array_merge([
                    'currency' => 'BRL',
                    'business_model' => 'b2b',
                ], $customData),
            ];

            $response = $this->fbeHelper->apiPost(
                sprintf('/%s/events', $pixelId),
                [
                    'data' => json_encode([$event], JSON_THROW_ON_ERROR),
                ]
            );

            $eventsReceived = (int) ($response['events_received'] ?? 0);

            $this->logger->info(sprintf(
                'FunnelEventService: sent %s for customer #%d (event_id: %s, received: %d).',
                $eventName,
                $customerId,
                $eventId,
                $eventsReceived
            ));

            return $eventsReceived > 0;
        } catch (\Exception $e) {
            $this->logger->error(sprintf(
                'FunnelEventService: failed to send %s for customer #%d — %s',
                $eventName,
                $customerId,
                $e->getMessage()
            ));
            return false;
        }
    }

    /**
     * Build SHA256-hashed user_data from customer entity.
     *
     * @return array<string, string>|null
     */
    private function buildUserData(int $customerId): ?array
    {
        try {
            $customer = $this->customerRepository->getById($customerId);
        } catch (\Exception) {
            return null;
        }

        $email = $customer->getEmail();
        if (empty($email)) {
            return null;
        }

        $userData = [
            'em' => [hash('sha256', mb_strtolower(trim($email)))],
            'external_id' => [hash('sha256', (string) $customerId)],
            'country' => [hash('sha256', 'br')],
        ];

        $firstName = $customer->getFirstname();
        if (!empty($firstName)) {
            $userData['fn'] = [hash('sha256', mb_strtolower(trim($firstName)))];
        }

        $lastName = $customer->getLastname();
        if (!empty($lastName)) {
            $userData['ln'] = [hash('sha256', mb_strtolower(trim($lastName)))];
        }

        $phone = $customer->getCustomAttribute('telefone')?->getValue()
            ?: $customer->getCustomAttribute('telephone')?->getValue();
        if (!empty($phone)) {
            $digits = preg_replace('/\D/', '', (string) $phone);
            if (!empty($digits)) {
                $userData['ph'] = [hash('sha256', $digits)];
            }
        }

        $addresses = $customer->getAddresses();
        if (!empty($addresses)) {
            $address = reset($addresses);

            $city = $address->getCity();
            if (!empty($city)) {
                $userData['ct'] = [hash('sha256', mb_strtolower(trim($city)))];
            }

            $region = $address->getRegion();
            if ($region !== null) {
                $regionCode = $region->getRegionCode();
                if (!empty($regionCode)) {
                    $userData['st'] = [hash('sha256', mb_strtolower(trim($regionCode)))];
                }
            }

            $postcode = $address->getPostcode();
            if (!empty($postcode)) {
                $userData['zp'] = [hash('sha256', preg_replace('/\D/', '', $postcode))];
            }
        }

        return $userData;
    }

    /**
     * Generate deterministic event_id for deduplication.
     */
    private function generateEventId(string $eventName, int $customerId): string
    {
        return hash('sha256', sprintf(
            '%s_%d_%d_%s',
            $eventName,
            $customerId,
            time(),
            bin2hex(random_bytes(4))
        ));
    }

    private function isEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_ENABLED);
    }

    private function getPixelId(): string
    {
        $pixelId = (string) $this->scopeConfig->getValue('marketing_intelligence/general/pixel_id');
        if (empty($pixelId)) {
            $pixelId = (string) $this->scopeConfig->getValue('facebook/business_extension/pixel_id');
        }

        return $pixelId;
    }
}
