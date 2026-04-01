<?php

declare(strict_types=1);

namespace GrupoAwamotos\SmartSuggestions\Test\Unit\Model\Whatsapp;

use GrupoAwamotos\SmartSuggestions\Model\Whatsapp\Sender;
use GrupoAwamotos\SmartSuggestions\Helper\Config;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Store\Api\Data\StoreInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Unit tests for WhatsApp Sender
 *
 * @covers \GrupoAwamotos\SmartSuggestions\Model\Whatsapp\Sender
 */
class SenderTest extends TestCase
{
    private Sender $subject;
    private Config&MockObject $configMock;
    private Curl&MockObject $curlMock;
    private StoreManagerInterface&MockObject $storeManagerMock;
    private LoggerInterface&MockObject $loggerMock;

    protected function setUp(): void
    {
        $this->configMock = $this->createMock(Config::class);
        $this->curlMock = $this->createMock(Curl::class);
        $this->storeManagerMock = $this->createMock(StoreManagerInterface::class);
        $this->loggerMock = $this->createMock(LoggerInterface::class);

        $storeMock = $this->createMock(StoreInterface::class);
        $storeMock->method('getName')->willReturn('AWA Motos');
        $this->storeManagerMock->method('getStore')->willReturn($storeMock);

        $this->subject = new Sender(
            $this->configMock,
            $this->curlMock,
            $this->storeManagerMock,
            $this->loggerMock
        );
    }

    // ============ sendSuggestion ============

    public function testSendSuggestionReturnsFailureWhenDisabled(): void
    {
        $this->configMock->method('isWhatsappEnabled')->willReturn(false);

        $result = $this->subject->sendSuggestion('16991234567', []);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('disabled', $result['message']);
    }

    public function testSendSuggestionFormatsAndSendsMessage(): void
    {
        $this->configMock->method('isWhatsappEnabled')->willReturn(true);
        $this->configMock->method('getWhatsappProvider')->willReturn('meta');
        $this->configMock->method('getWhatsappMessageTemplate')->willReturn('');
        $this->configMock->method('getWhatsappApiUrl')->willReturn('https://graph.facebook.com/v17.0');
        $this->configMock->method('getWhatsappPhoneNumberId')->willReturn('123456');
        $this->configMock->method('getWhatsappApiToken')->willReturn('token123');

        $this->curlMock->method('getBody')->willReturn(json_encode([
            'messages' => [['id' => 'msg_001']]
        ]));
        $this->curlMock->method('getStatus')->willReturn(200);

        $suggestionData = [
            'customer' => [
                'trade_name' => 'Moto Parts SA',
                'customer_name' => 'João Silva'
            ],
            'suggestions' => [
                'repurchase' => [
                    ['sku' => 'BAG-001', 'suggested_qty' => 5, 'suggested_value' => 150.00],
                ],
                'cross_sell' => []
            ],
            'cart_summary' => [
                'total_value' => 150.00
            ]
        ];

        $result = $this->subject->sendSuggestion('5516991234567', $suggestionData);

        $this->assertTrue($result['success']);
    }

    // ============ sendMessage ============

    public function testSendMessageReturnsFailureForEmptyPhone(): void
    {
        $result = $this->subject->sendMessage('', 'Teste');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Invalid phone', $result['message']);
    }

    public function testSendMessageViaMetaSuccess(): void
    {
        $this->configMock->method('getWhatsappProvider')->willReturn('meta');
        $this->configMock->method('getWhatsappApiUrl')->willReturn('https://graph.facebook.com/v17.0');
        $this->configMock->method('getWhatsappPhoneNumberId')->willReturn('123456');
        $this->configMock->method('getWhatsappApiToken')->willReturn('token123');

        $this->curlMock->method('getBody')->willReturn(json_encode([
            'messages' => [['id' => 'wamid.123']]
        ]));
        $this->curlMock->method('getStatus')->willReturn(200);

        $result = $this->subject->sendMessage('5516991234567', 'Olá, teste!');

        $this->assertTrue($result['success']);
        $this->assertEquals('wamid.123', $result['message_id']);
    }

    public function testSendMessageViaMetaFailureWithoutCredentials(): void
    {
        $this->configMock->method('getWhatsappProvider')->willReturn('meta');
        $this->configMock->method('getWhatsappApiUrl')->willReturn('https://graph.facebook.com/v17.0');
        $this->configMock->method('getWhatsappPhoneNumberId')->willReturn('');
        $this->configMock->method('getWhatsappApiToken')->willReturn('');

        $result = $this->subject->sendMessage('5516991234567', 'Teste');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('credentials', $result['message']);
    }

    public function testSendMessageViaTwilioSuccess(): void
    {
        $this->configMock->method('getWhatsappProvider')->willReturn('twilio');
        $this->configMock->method('getTwilioSid')->willReturn('AC12345');
        $this->configMock->method('getWhatsappApiToken')->willReturn('token');
        $this->configMock->method('getTwilioFrom')->willReturn('whatsapp:+14155238886');

        $this->curlMock->method('getBody')->willReturn(json_encode([
            'sid' => 'SM12345'
        ]));
        $this->curlMock->method('getStatus')->willReturn(201);

        $result = $this->subject->sendMessage('5516991234567', 'Teste Twilio');

        $this->assertTrue($result['success']);
        $this->assertEquals('SM12345', $result['message_id']);
    }

    public function testSendMessageViaTwilioFailureWithoutCredentials(): void
    {
        $this->configMock->method('getWhatsappProvider')->willReturn('twilio');
        $this->configMock->method('getTwilioSid')->willReturn('');
        $this->configMock->method('getWhatsappApiToken')->willReturn('');
        $this->configMock->method('getTwilioFrom')->willReturn('');

        $result = $this->subject->sendMessage('5516991234567', 'Teste');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('credentials', $result['message']);
    }

    public function testSendMessageViaEvolutionSuccess(): void
    {
        $this->configMock->method('getWhatsappProvider')->willReturn('evolution');
        $this->configMock->method('getWhatsappApiUrl')->willReturn('https://evo.example.com');
        $this->configMock->method('getWhatsappApiToken')->willReturn('apikey123');

        $this->curlMock->method('getBody')->willReturn(json_encode([
            'key' => ['id' => 'evo_msg_001']
        ]));
        $this->curlMock->method('getStatus')->willReturn(200);

        $result = $this->subject->sendMessage('5516991234567', 'Teste Evolution');

        $this->assertTrue($result['success']);
        $this->assertEquals('evo_msg_001', $result['message_id']);
    }

    public function testSendMessageViaEvolutionFailureWithoutCredentials(): void
    {
        $this->configMock->method('getWhatsappProvider')->willReturn('evolution');
        $this->configMock->method('getWhatsappApiUrl')->willReturn('');
        $this->configMock->method('getWhatsappApiToken')->willReturn('');

        $result = $this->subject->sendMessage('5516991234567', 'Teste');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('credentials', $result['message']);
    }

    public function testSendMessageViaCustomApiSuccess(): void
    {
        $this->configMock->method('getWhatsappProvider')->willReturn('custom');
        $this->configMock->method('getWhatsappApiUrl')->willReturn('https://custom.api/send');
        $this->configMock->method('getWhatsappApiToken')->willReturn('bearer_token');

        $this->curlMock->method('getBody')->willReturn(json_encode([
            'status' => 'ok'
        ]));
        $this->curlMock->method('getStatus')->willReturn(200);

        $result = $this->subject->sendMessage('5516991234567', 'Teste Custom');

        $this->assertTrue($result['success']);
    }

    public function testSendMessageViaCustomApiFailureWithoutUrl(): void
    {
        $this->configMock->method('getWhatsappProvider')->willReturn('custom');
        $this->configMock->method('getWhatsappApiUrl')->willReturn('');

        $result = $this->subject->sendMessage('5516991234567', 'Teste');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('URL not configured', $result['message']);
    }

    public function testSendMessageReturnsFailureForUnknownProvider(): void
    {
        $this->configMock->method('getWhatsappProvider')->willReturn('unknown_provider');

        $result = $this->subject->sendMessage('5516991234567', 'Teste');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Unknown', $result['message']);
    }

    public function testSendMessageLogsExceptionAndReturnsFailure(): void
    {
        $this->configMock->method('getWhatsappProvider')->willReturn('meta');
        $this->configMock->method('getWhatsappApiUrl')->willReturn('https://graph.facebook.com/v17.0');
        $this->configMock->method('getWhatsappPhoneNumberId')->willReturn('123');
        $this->configMock->method('getWhatsappApiToken')->willReturn('token');

        $this->curlMock->method('post')
            ->willThrowException(new \RuntimeException('Connection failed'));

        $this->loggerMock
            ->expects($this->once())
            ->method('error')
            ->with($this->stringContains('WhatsApp send error'));

        $result = $this->subject->sendMessage('5516991234567', 'Teste');

        $this->assertFalse($result['success']);
    }

    // ============ testConnection ============

    public function testTestConnectionMetaSuccess(): void
    {
        $this->configMock->method('getWhatsappProvider')->willReturn('meta');
        $this->configMock->method('getWhatsappApiUrl')->willReturn('https://graph.facebook.com/v17.0');
        $this->configMock->method('getWhatsappPhoneNumberId')->willReturn('123456');
        $this->configMock->method('getWhatsappApiToken')->willReturn('token');

        $this->curlMock->method('getBody')->willReturn(json_encode([
            'id' => '123456',
            'display_phone_number' => '+5516991234567'
        ]));
        $this->curlMock->method('getStatus')->willReturn(200);

        $result = $this->subject->testConnection();

        $this->assertTrue($result['success']);
    }

    public function testTestConnectionTwilioSuccess(): void
    {
        $this->configMock->method('getWhatsappProvider')->willReturn('twilio');
        $this->configMock->method('getTwilioSid')->willReturn('AC12345');
        $this->configMock->method('getWhatsappApiToken')->willReturn('token');

        $this->curlMock->method('getBody')->willReturn(json_encode([
            'sid' => 'AC12345',
            'friendly_name' => 'AWA Motos'
        ]));
        $this->curlMock->method('getStatus')->willReturn(200);

        $result = $this->subject->testConnection();

        $this->assertTrue($result['success']);
    }

    public function testTestConnectionEvolutionSuccess(): void
    {
        $this->configMock->method('getWhatsappProvider')->willReturn('evolution');
        $this->configMock->method('getWhatsappApiUrl')->willReturn('https://evo.example.com');
        $this->configMock->method('getWhatsappApiToken')->willReturn('apikey');

        $this->curlMock->method('getBody')->willReturn(json_encode([
            'state' => 'open'
        ]));
        $this->curlMock->method('getStatus')->willReturn(200);

        $result = $this->subject->testConnection();

        $this->assertTrue($result['success']);
    }

    public function testTestConnectionUnknownProvider(): void
    {
        $this->configMock->method('getWhatsappProvider')->willReturn('smoke_signal');

        $result = $this->subject->testConnection();

        $this->assertFalse($result['success']);
    }

    public function testTestConnectionHandlesException(): void
    {
        // Test with an unknown provider that gets through but has no valid test method
        $this->configMock->method('getWhatsappProvider')->willReturn('nonexistent');

        $result = $this->subject->testConnection();

        $this->assertFalse($result['success']);
    }

    // ============ formatSuggestionMessage ============

    public function testFormatSuggestionMessageUsesDefaultTemplate(): void
    {
        $this->configMock->method('getWhatsappMessageTemplate')->willReturn('');

        $data = [
            'customer' => [
                'trade_name' => 'Moto Parts',
                'customer_name' => 'João'
            ],
            'suggestions' => [
                'repurchase' => [
                    ['sku' => 'BAG-001', 'suggested_qty' => 3, 'suggested_value' => 150.00]
                ],
                'cross_sell' => []
            ],
            'cart_summary' => [
                'total_value' => 150.00
            ]
        ];

        $result = $this->subject->formatSuggestionMessage($data);

        $this->assertStringContainsString('Moto Parts', $result);
        $this->assertStringContainsString('BAG-001', $result);
        $this->assertStringContainsString('R$ 150,00', $result);
        $this->assertStringContainsString('AWA Motos', $result);
    }

    public function testFormatSuggestionMessageUsesCustomTemplate(): void
    {
        $this->configMock->method('getWhatsappMessageTemplate')
            ->willReturn('Oi {{customer_name}}, veja: {{products_list}} Total: {{total_value}} - {{store_name}}');

        $data = [
            'customer' => ['customer_name' => 'Maria'],
            'suggestions' => ['repurchase' => [], 'cross_sell' => []],
            'cart_summary' => ['total_value' => 0]
        ];

        $result = $this->subject->formatSuggestionMessage($data);

        $this->assertStringContainsString('Oi Maria', $result);
        $this->assertStringContainsString('AWA Motos', $result);
    }

    public function testFormatSuggestionMessageUsesTradeNameOverCustomerName(): void
    {
        $this->configMock->method('getWhatsappMessageTemplate')->willReturn('');

        $data = [
            'customer' => [
                'trade_name' => 'Nome Fantasia',
                'customer_name' => 'Razão Social'
            ],
            'suggestions' => ['repurchase' => [], 'cross_sell' => []],
            'cart_summary' => ['total_value' => 0]
        ];

        $result = $this->subject->formatSuggestionMessage($data);

        $this->assertStringContainsString('Nome Fantasia', $result);
    }

    public function testFormatSuggestionMessageDefaultsToCliente(): void
    {
        $this->configMock->method('getWhatsappMessageTemplate')->willReturn('');

        $data = [
            'customer' => [],
            'suggestions' => ['repurchase' => [], 'cross_sell' => []],
            'cart_summary' => ['total_value' => 0]
        ];

        $result = $this->subject->formatSuggestionMessage($data);

        $this->assertStringContainsString('Cliente', $result);
    }

    public function testFormatSuggestionMessageIncludesCrossSellingProducts(): void
    {
        $this->configMock->method('getWhatsappMessageTemplate')->willReturn('');

        $data = [
            'customer' => ['customer_name' => 'Test'],
            'suggestions' => [
                'repurchase' => [],
                'cross_sell' => [
                    ['sku' => 'RET-100', 'unit_price' => 89.90]
                ]
            ],
            'cart_summary' => ['total_value' => 89.90]
        ];

        $result = $this->subject->formatSuggestionMessage($data);

        $this->assertStringContainsString('RET-100', $result);
        $this->assertStringContainsString('R$ 89,90', $result);
    }

    // ============ Phone number normalization ============

    public function testSendMessageNormalizesPhoneWithoutCountryCode(): void
    {
        $this->configMock->method('getWhatsappProvider')->willReturn('custom');
        $this->configMock->method('getWhatsappApiUrl')->willReturn('https://api.example.com');
        $this->configMock->method('getWhatsappApiToken')->willReturn('token');

        $this->curlMock->method('getBody')->willReturn('{"success":true}');
        $this->curlMock->method('getStatus')->willReturn(200);

        // 11 digits (mobile with DDD) should get 55 prefix
        $result = $this->subject->sendMessage('16991234567', 'Teste');

        $this->assertTrue($result['success']);
    }

    public function testSendMessageNormalizesPhoneWithSpecialChars(): void
    {
        $this->configMock->method('getWhatsappProvider')->willReturn('custom');
        $this->configMock->method('getWhatsappApiUrl')->willReturn('https://api.example.com');
        $this->configMock->method('getWhatsappApiToken')->willReturn('token');

        $this->curlMock->method('getBody')->willReturn('{"success":true}');
        $this->curlMock->method('getStatus')->willReturn(200);

        // Phone with special chars should be normalized
        $result = $this->subject->sendMessage('(16) 99123-4567', 'Teste');

        $this->assertTrue($result['success']);
    }

    public function testSendMessageMetaApiError(): void
    {
        $this->configMock->method('getWhatsappProvider')->willReturn('meta');
        $this->configMock->method('getWhatsappApiUrl')->willReturn('https://graph.facebook.com/v17.0');
        $this->configMock->method('getWhatsappPhoneNumberId')->willReturn('123');
        $this->configMock->method('getWhatsappApiToken')->willReturn('token');

        $this->curlMock->method('getBody')->willReturn(json_encode([
            'error' => ['message' => 'Invalid phone number format']
        ]));
        $this->curlMock->method('getStatus')->willReturn(400);

        $result = $this->subject->sendMessage('5516991234567', 'Teste');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Invalid phone number format', $result['message']);
    }
}
