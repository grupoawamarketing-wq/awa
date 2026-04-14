<?php

declare(strict_types=1);

namespace GrupoAwamotos\Chatwoot\Block\Adminhtml;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;

class Dashboard extends Template
{
    private const LOG_FILE = BP . '/var/log/chatwoot_webhook.log';
    private const XML_PATH_BASE_URL = 'grupoawamotos_chatwoot/general/base_url';
    private const XML_PATH_ENABLED = 'grupoawamotos_chatwoot/general/enabled';
    private const XML_PATH_BOT_ENABLED = 'grupoawamotos_chatwoot/bot/enabled';

    private ScopeConfigInterface $scopeConfig;
    private EncryptorInterface $encryptor;

    public function __construct(
        Context $context,
        ScopeConfigInterface $scopeConfig,
        EncryptorInterface $encryptor,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->scopeConfig = $scopeConfig;
        $this->encryptor = $encryptor;
    }

    /**
     * @return array{enabled: bool, bot_enabled: bool, base_url: string}
     */
    public function getConfig(): array
    {
        return [
            'enabled' => (bool) $this->scopeConfig->getValue(self::XML_PATH_ENABLED),
            'bot_enabled' => (bool) $this->scopeConfig->getValue(self::XML_PATH_BOT_ENABLED),
            'base_url' => (string) $this->scopeConfig->getValue(self::XML_PATH_BASE_URL),
        ];
    }

    /**
     * @return array{total: int, events: array<string, int>, first_date: string, last_date: string, conversations_unique: int, contacts_created: int, messages: int}
     */
    public function getLogMetrics(): array
    {
        $metrics = [
            'total' => 0,
            'events' => [],
            'first_date' => '',
            'last_date' => '',
            'conversations_unique' => 0,
            'contacts_created' => 0,
            'messages' => 0,
        ];

        if (!is_file(self::LOG_FILE) || !is_readable(self::LOG_FILE)) {
            return $metrics;
        }

        $handle = fopen(self::LOG_FILE, 'r');
        if ($handle === false) {
            return $metrics;
        }

        $conversationIds = [];
        $lineCount = 0;

        while (($line = fgets($handle)) !== false) {
            $lineCount++;

            if ($lineCount === 1 && preg_match('/^\[([^\]]+)\]/', $line, $m)) {
                $metrics['first_date'] = $m[1];
            }

            if (preg_match('/"event":"([^"]+)"/', $line, $m)) {
                $event = $m[1];
                $metrics['events'][$event] = ($metrics['events'][$event] ?? 0) + 1;
            }

            if (preg_match('/"conversation_id":(\d+)/', $line, $m)) {
                if (count($conversationIds) < 50000) {
                    $conversationIds[(int) $m[1]] = true;
                }
            }
        }

        fclose($handle);

        $metrics['total'] = $lineCount;
        $metrics['conversations_unique'] = count($conversationIds);
        $metrics['contacts_created'] = $metrics['events']['contact_created'] ?? 0;
        $metrics['messages'] = $metrics['events']['message_created'] ?? 0;

        $lastLines = $this->tailFile(self::LOG_FILE, 1);
        if (!empty($lastLines) && preg_match('/^\[([^\]]+)\]/', $lastLines[0], $m)) {
            $metrics['last_date'] = $m[1];
        }

        arsort($metrics['events']);

        return $metrics;
    }

    /**
     * @return array<int, array{date: string, event: string, conversation_id: string, detail: string}>
     */
    public function getRecentEvents(int $count = 20): array
    {
        $lines = $this->tailFile(self::LOG_FILE, $count * 2);
        $events = [];

        foreach (array_reverse($lines) as $line) {
            if (count($events) >= $count) {
                break;
            }

            $date = '';
            $event = '';
            $convId = '';
            $detail = '';

            if (preg_match('/^\[([^\]]+)\]/', $line, $m)) {
                $date = $m[1];
            }

            if (preg_match('/"event":"([^"]+)"/', $line, $m)) {
                $event = $m[1];
            }

            if (preg_match('/"conversation_id":(\d+)/', $line, $m)) {
                $convId = $m[1];
            }

            if (preg_match('/"message_preview":"([^"]{0,80})/', $line, $m)) {
                $detail = $m[1];
            } elseif (preg_match('/"status":"([^"]+)"/', $line, $m)) {
                $detail = 'Status: ' . $m[1];
            } elseif (preg_match('/"agent":"([^"]+)"/', $line, $m)) {
                $detail = 'Agente: ' . $m[1];
            }

            if ($event !== '' || $date !== '') {
                $events[] = [
                    'date' => $date,
                    'event' => $event,
                    'conversation_id' => $convId,
                    'detail' => $detail,
                ];
            }
        }

        return $events;
    }

    public function getChatwootUrl(): string
    {
        $baseUrl = rtrim((string) $this->scopeConfig->getValue(self::XML_PATH_BASE_URL), '/');
        return $baseUrl ?: 'https://chat.awamotos.com';
    }

    public function getSettingsUrl(): string
    {
        return $this->getUrl('adminhtml/system_config/edit', ['section' => 'grupoawamotos_chatwoot']);
    }

    public function formatEventName(string $event): string
    {
        $map = [
            'conversation_created' => 'Conversa Criada',
            'conversation_updated' => 'Conversa Atualizada',
            'conversation_status_changed' => 'Status Alterado',
            'conversation_opened' => 'Conversa Aberta',
            'message_created' => 'Mensagem',
            'message_updated' => 'Msg Editada',
            'contact_created' => 'Contato Criado',
            'contact_updated' => 'Contato Atualizado',
            'webwidget_triggered' => 'Widget Ativado',
        ];
        return $map[$event] ?? $event;
    }

    /**
     * @return string[]
     */
    private function tailFile(string $file, int $lines): array
    {
        if (!is_file($file) || !is_readable($file)) {
            return [];
        }
        $handle = fopen($file, 'r');
        if ($handle === false) {
            return [];
        }
        $buffer = [];
        while (($line = fgets($handle)) !== false) {
            $buffer[] = rtrim($line);
            if (count($buffer) > $lines) {
                array_shift($buffer);
            }
        }
        fclose($handle);
        return $buffer;
    }
}
