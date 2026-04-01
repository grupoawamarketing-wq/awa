<?php

declare(strict_types=1);

namespace GrupoAwamotos\ERPIntegration\Model\WhatsApp;

use GrupoAwamotos\ERPIntegration\Helper\Data as Helper;
use Magento\Framework\HTTP\Client\Curl;
use Psr\Log\LoggerInterface;

/**
 * WhatsApp Business Cloud API Client
 *
 * Integrates with Meta's WhatsApp Business API for:
 * - Re-engagement notifications
 * - Order status updates
 * - Product suggestions
 * - Coupon delivery
 */
class Client
{
    private const API_BASE_URL = 'https://graph.facebook.com/v18.0';

    private Helper $helper;
    private Curl $curl;
    private LoggerInterface $logger;

    public function __construct(
        Helper $helper,
        Curl $curl,
        LoggerInterface $logger
    ) {
        $this->helper = $helper;
        $this->curl = $curl;
        $this->logger = $logger;
    }

    /**
     * Send a template message
     *
     * @param string $phoneNumber Phone number with country code (e.g., 5511999999999)
     * @param string $templateName Template name registered in WhatsApp Business
     * @param array $components Template components (header, body, buttons)
     * @param string $languageCode Language code (default: pt_BR)
     * @return array|null Response data or null on failure
     */
    public function sendTemplate(
        string $phoneNumber,
        string $templateName,
        array $components = [],
        string $languageCode = 'pt_BR'
    ): ?array {
        if (!$this->helper->isWhatsAppEnabled()) {
            return null;
        }

        $phoneNumber = $this->normalizePhoneNumber($phoneNumber);

        if (!$phoneNumber) {
            $this->logger->warning('[WhatsApp] Invalid phone number provided');
            return null;
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $phoneNumber,
            'type' => 'template',
            'template' => [
                'name' => $templateName,
                'language' => [
                    'code' => $languageCode,
                ],
            ],
        ];

        if (!empty($components)) {
            $payload['template']['components'] = $components;
        }

        return $this->sendRequest('messages', $payload);
    }

    /**
     * Send re-engagement message with coupon
     */
    public function sendReengagementMessage(
        string $phoneNumber,
        string $customerName,
        string $couponCode,
        int $discountPercent,
        int $validDays = 30
    ): ?array {
        $components = [
            [
                'type' => 'header',
                'parameters' => [
                    ['type' => 'text', 'text' => $customerName],
                ],
            ],
            [
                'type' => 'body',
                'parameters' => [
                    ['type' => 'text', 'text' => $customerName],
                    ['type' => 'text', 'text' => (string) $discountPercent],
                    ['type' => 'text', 'text' => $couponCode],
                    ['type' => 'text', 'text' => (string) $validDays],
                ],
            ],
            [
                'type' => 'button',
                'sub_type' => 'url',
                'index' => '0',
                'parameters' => [
                    ['type' => 'text', 'text' => $couponCode],
                ],
            ],
        ];

        $templateName = $this->helper->getWhatsAppReengagementTemplate();

        $result = $this->sendTemplate($phoneNumber, $templateName, $components);

        if ($result) {
            $this->logger->info(sprintf(
                '[WhatsApp] Re-engagement message sent to %s (coupon: %s)',
                $this->maskPhoneNumber($phoneNumber),
                $couponCode
            ));
        }

        return $result;
    }

    /**
     * Send product suggestion message
     */
    public function sendProductSuggestion(
        string $phoneNumber,
        string $customerName,
        array $products
    ): ?array {
        if (empty($products)) {
            return null;
        }

        // Build product list text
        $productList = '';
        foreach (array_slice($products, 0, 3) as $index => $product) {
            $productList .= sprintf(
                "%d. %s - R$ %s\n",
                $index + 1,
                $product['name'] ?? '',
                number_format($product['price'] ?? 0, 2, ',', '.')
            );
        }

        $components = [
            [
                'type' => 'body',
                'parameters' => [
                    ['type' => 'text', 'text' => $customerName],
                    ['type' => 'text', 'text' => trim($productList)],
                ],
            ],
        ];

        $templateName = $this->helper->getWhatsAppSuggestionTemplate();

        return $this->sendTemplate($phoneNumber, $templateName, $components);
    }

    /**
     * Send order status update
     */
    public function sendOrderStatus(
        string $phoneNumber,
        string $orderNumber,
        string $status,
        ?string $trackingCode = null
    ): ?array {
        $statusLabels = [
            'processing' => 'Em Processamento',
            'shipped' => 'Enviado',
            'delivered' => 'Entregue',
            'canceled' => 'Cancelado',
        ];

        $statusLabel = $statusLabels[$status] ?? $status;

        $components = [
            [
                'type' => 'body',
                'parameters' => [
                    ['type' => 'text', 'text' => $orderNumber],
                    ['type' => 'text', 'text' => $statusLabel],
                ],
            ],
        ];

        if ($trackingCode && $status === 'shipped') {
            $components[0]['parameters'][] = ['type' => 'text', 'text' => $trackingCode];
        }

        $templateName = $this->helper->getWhatsAppOrderStatusTemplate();

        return $this->sendTemplate($phoneNumber, $templateName, $components);
    }

    /**
     * Send a simple text message (for testing/support)
     */
    public function sendTextMessage(string $phoneNumber, string $message): ?array
    {
        if (!$this->helper->isWhatsAppEnabled()) {
            return null;
        }

        $phoneNumber = $this->normalizePhoneNumber($phoneNumber);

        if (!$phoneNumber) {
            return null;
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $phoneNumber,
            'type' => 'text',
            'text' => [
                'preview_url' => true,
                'body' => $message,
            ],
        ];

        return $this->sendRequest('messages', $payload);
    }

    /**
     * Send API request
     */
    private function sendRequest(string $endpoint, array $payload): ?array
    {
        $phoneNumberId = $this->helper->getWhatsAppPhoneNumberId();
        $accessToken = $this->helper->getWhatsAppAccessToken();

        if (!$phoneNumberId || !$accessToken) {
            $this->logger->error('[WhatsApp] Missing API configuration');
            return null;
        }

        $url = sprintf('%s/%s/%s', self::API_BASE_URL, $phoneNumberId, $endpoint);

        try {
            $this->curl->setHeaders([
                'Authorization' => 'Bearer ' . $accessToken,
                'Content-Type' => 'application/json',
            ]);

            $this->curl->post($url, json_encode($payload));

            $response = $this->curl->getBody();
            $httpCode = $this->curl->getStatus();

            $responseData = json_decode($response, true);

            if ($httpCode >= 200 && $httpCode < 300) {
                return $responseData;
            }

            $errorMessage = $responseData['error']['message'] ?? 'Unknown error';
            $this->logger->error(sprintf(
                '[WhatsApp] API error (%d): %s',
                $httpCode,
                $errorMessage
            ));

            return null;
        } catch (\Exception $e) {
            $this->logger->error('[WhatsApp] Request error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Normalize phone number to WhatsApp format
     */
    private function normalizePhoneNumber(string $phone): ?string
    {
        // Remove all non-numeric characters
        $phone = preg_replace('/[^0-9]/', '', $phone);

        if (empty($phone)) {
            return null;
        }

        // Add Brazil country code if not present
        if (strlen($phone) === 11 || strlen($phone) === 10) {
            $phone = '55' . $phone;
        }

        // Validate length (Brazil: 13 digits with country code)
        if (strlen($phone) < 12 || strlen($phone) > 15) {
            return null;
        }

        return $phone;
    }

    /**
     * Mask phone number for logging
     */
    private function maskPhoneNumber(string $phone): string
    {
        if (strlen($phone) <= 6) {
            return '***';
        }

        return substr($phone, 0, 4) . '****' . substr($phone, -4);
    }

    /**
     * Check if WhatsApp API is properly configured
     */
    public function isConfigured(): bool
    {
        return $this->helper->isWhatsAppEnabled()
            && !empty($this->helper->getWhatsAppPhoneNumberId())
            && !empty($this->helper->getWhatsAppAccessToken());
    }

    /**
     * Test API connection
     */
    public function testConnection(): array
    {
        if (!$this->isConfigured()) {
            return [
                'success' => false,
                'message' => 'WhatsApp API not configured',
            ];
        }

        $phoneNumberId = $this->helper->getWhatsAppPhoneNumberId();
        $accessToken = $this->helper->getWhatsAppAccessToken();

        $url = sprintf('%s/%s', self::API_BASE_URL, $phoneNumberId);

        try {
            $this->curl->setHeaders([
                'Authorization' => 'Bearer ' . $accessToken,
            ]);

            $this->curl->get($url);

            $httpCode = $this->curl->getStatus();
            $response = json_decode($this->curl->getBody(), true);

            if ($httpCode === 200) {
                return [
                    'success' => true,
                    'message' => 'Connection successful',
                    'phone_number' => $response['display_phone_number'] ?? 'Unknown',
                    'verified_name' => $response['verified_name'] ?? 'Unknown',
                ];
            }

            return [
                'success' => false,
                'message' => $response['error']['message'] ?? 'Unknown error',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }
}
