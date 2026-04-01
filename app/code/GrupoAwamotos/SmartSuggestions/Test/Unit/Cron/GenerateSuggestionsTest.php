<?php

declare(strict_types=1);

namespace GrupoAwamotos\SmartSuggestions\Test\Unit\Cron;

use GrupoAwamotos\SmartSuggestions\Api\SuggestionEngineInterface;
use GrupoAwamotos\SmartSuggestions\Api\WhatsappSenderInterface;
use GrupoAwamotos\SmartSuggestions\Cron\GenerateSuggestions;
use GrupoAwamotos\SmartSuggestions\Helper\Config;
use GrupoAwamotos\SmartSuggestions\Model\ResourceModel\SuggestionHistory as SuggestionHistoryResource;
use GrupoAwamotos\SmartSuggestions\Model\SuggestionHistory;
use GrupoAwamotos\SmartSuggestions\Model\SuggestionHistoryFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Unit tests for GenerateSuggestions Cron Job
 *
 * @covers \GrupoAwamotos\SmartSuggestions\Cron\GenerateSuggestions
 */
class GenerateSuggestionsTest extends TestCase
{
    private GenerateSuggestions $subject;
    private SuggestionEngineInterface&MockObject $suggestionEngine;
    private WhatsappSenderInterface&MockObject $whatsappSender;
    private Config&MockObject $config;
    private LoggerInterface&MockObject $logger;
    private SuggestionHistoryFactory&MockObject $historyFactory;
    private SuggestionHistoryResource&MockObject $historyResource;

    private static array $baseOpportunity = [
        'customer_id' => 42,
        'customer_name' => 'Cliente Teste',
    ];

    private static array $baseSuggestion = [
        'customer' => ['phone' => '16991230000'],
        'cart_summary' => ['total_value' => 350.0, 'total_products' => 3],
    ];

    protected function setUp(): void
    {
        $this->suggestionEngine = $this->createMock(SuggestionEngineInterface::class);
        $this->whatsappSender = $this->createMock(WhatsappSenderInterface::class);
        $this->config = $this->createMock(Config::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->historyFactory = $this->createMock(SuggestionHistoryFactory::class);
        $this->historyResource = $this->createMock(SuggestionHistoryResource::class);

        $this->subject = new GenerateSuggestions(
            $this->suggestionEngine,
            $this->whatsappSender,
            $this->config,
            $this->logger,
            $this->historyFactory,
            $this->historyResource
        );
    }

    // ====================================================================
    // Early-exit guards
    // ====================================================================

    public function testSkipsWhenModuleDisabled(): void
    {
        $this->config->method('isEnabled')->willReturn(false);

        $this->suggestionEngine->expects($this->never())->method('getTopOpportunities');
        $this->historyResource->expects($this->never())->method('save');

        $this->subject->execute();
    }

    public function testSkipsWhenSuggestionsCronDisabled(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->config->method('isSuggestionsCronEnabled')->willReturn(false);

        $this->suggestionEngine->expects($this->never())->method('getTopOpportunities');
        $this->historyResource->expects($this->never())->method('save');

        $this->subject->execute();
    }

    // ====================================================================
    // Single-save: WhatsApp disabled
    // ====================================================================

    public function testSavesExactlyOnceWhenWhatsappDisabled(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->config->method('isSuggestionsCronEnabled')->willReturn(true);
        $this->config->method('isAutoSendWhatsappEnabled')->willReturn(false);

        $this->suggestionEngine
            ->method('getTopOpportunities')
            ->willReturn([self::$baseOpportunity]);

        $this->suggestionEngine
            ->method('generateCartSuggestion')
            ->with(42)
            ->willReturn(self::$baseSuggestion);

        $history = $this->createMock(SuggestionHistory::class);
        $this->historyFactory->method('create')->willReturn($history);

        // Exactly ONE save per customer when WA is off
        $this->historyResource
            ->expects($this->once())
            ->method('save')
            ->with($history);

        $this->whatsappSender->expects($this->never())->method('sendSuggestion');

        $this->subject->execute();
    }

    // ====================================================================
    // Single-save: WhatsApp enabled + send succeeds
    // ====================================================================

    public function testSavesExactlyOnceWhenWhatsappSucceeds(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->config->method('isSuggestionsCronEnabled')->willReturn(true);
        $this->config->method('isAutoSendWhatsappEnabled')->willReturn(true);
        $this->config->method('isWhatsappEnabled')->willReturn(true);

        $this->suggestionEngine
            ->method('getTopOpportunities')
            ->willReturn([self::$baseOpportunity]);

        $this->suggestionEngine
            ->method('generateCartSuggestion')
            ->willReturn(self::$baseSuggestion);

        $this->whatsappSender
            ->method('sendSuggestion')
            ->willReturn(['success' => true, 'message_id' => 'wamid.abc123']);

        $history = $this->createMock(SuggestionHistory::class);
        $this->historyFactory->method('create')->willReturn($history);

        // Exactly ONE save even when WA send is attempted
        $this->historyResource
            ->expects($this->once())
            ->method('save');

        $this->subject->execute();
    }

    // ====================================================================
    // Single-save: WhatsApp enabled + send fails
    // ====================================================================

    public function testSavesExactlyOnceWhenWhatsappFails(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->config->method('isSuggestionsCronEnabled')->willReturn(true);
        $this->config->method('isAutoSendWhatsappEnabled')->willReturn(true);
        $this->config->method('isWhatsappEnabled')->willReturn(true);

        $this->suggestionEngine
            ->method('getTopOpportunities')
            ->willReturn([self::$baseOpportunity]);

        $this->suggestionEngine
            ->method('generateCartSuggestion')
            ->willReturn(self::$baseSuggestion);

        $this->whatsappSender
            ->method('sendSuggestion')
            ->willReturn(['success' => false, 'message' => 'Rate limit exceeded']);

        $history = $this->createMock(SuggestionHistory::class);
        $this->historyFactory->method('create')->willReturn($history);

        $this->historyResource
            ->expects($this->once())
            ->method('save');

        $this->subject->execute();
    }

    // ====================================================================
    // Status values
    // ====================================================================

    public function testSavedStatusIsGeneratedWhenWhatsappOff(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->config->method('isSuggestionsCronEnabled')->willReturn(true);
        $this->config->method('isAutoSendWhatsappEnabled')->willReturn(false);

        $this->suggestionEngine
            ->method('getTopOpportunities')
            ->willReturn([self::$baseOpportunity]);
        $this->suggestionEngine
            ->method('generateCartSuggestion')
            ->willReturn(self::$baseSuggestion);

        $savedData = [];
        $history = $this->createMock(SuggestionHistory::class);
        $history->method('setData')->willReturnCallback(static function (array $d) use (&$savedData, $history) {
            $savedData = $d;
            return $history;
        });
        $this->historyFactory->method('create')->willReturn($history);

        $this->subject->execute();

        $this->assertSame('generated', $savedData['status'] ?? null);
        $this->assertNull($savedData['sent_at'] ?? null);
        $this->assertNull($savedData['whatsapp_message_id'] ?? null);
    }

    // ====================================================================
    // Suggestion with error — skip save, count as error
    // ====================================================================

    public function testSkipsSaveWhenSuggestionHasError(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->config->method('isSuggestionsCronEnabled')->willReturn(true);

        $this->suggestionEngine
            ->method('getTopOpportunities')
            ->willReturn([self::$baseOpportunity]);

        $this->suggestionEngine
            ->method('generateCartSuggestion')
            ->willReturn(['error' => 'No products found for customer']);

        $this->historyResource->expects($this->never())->method('save');

        $this->subject->execute();
    }

    // ====================================================================
    // Multiple customers: N customers → N saves total
    // ====================================================================

    public function testSavesOncePerCustomerForMultipleOpportunities(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->config->method('isSuggestionsCronEnabled')->willReturn(true);
        $this->config->method('isAutoSendWhatsappEnabled')->willReturn(false);

        $opportunities = array_map(
            static fn(int $id) => ['customer_id' => $id, 'customer_name' => "Cliente $id"],
            range(1, 5)
        );

        $this->suggestionEngine->method('getTopOpportunities')->willReturn($opportunities);
        $this->suggestionEngine->method('generateCartSuggestion')->willReturn(self::$baseSuggestion);

        $history = $this->createMock(SuggestionHistory::class);
        $this->historyFactory->method('create')->willReturn($history);

        // 5 customers → exactly 5 saves (1 per customer, no double-save)
        $this->historyResource
            ->expects($this->exactly(5))
            ->method('save');

        $this->subject->execute();
    }
}
