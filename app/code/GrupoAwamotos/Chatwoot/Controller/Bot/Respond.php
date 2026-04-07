<?php

declare(strict_types=1);

namespace GrupoAwamotos\Chatwoot\Controller\Bot;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\HTTP\Client\Curl as MagentoCurl;
use Psr\Log\LoggerInterface;

/**
 * Bot de Triagem — Chatwoot AgentBot Webhook
 *
 * Recebe eventos do AgentBot do Chatwoot e responde com menu de opções.
 * Ao receber a escolha do visitante, atribui label + team e entrega ao agente humano.
 */
class Respond implements HttpPostActionInterface, CsrfAwareActionInterface
{
    private const XML_PATH_BASE_URL = 'grupoawamotos_chatwoot/general/base_url';
    private const XML_PATH_BOT_API_URL = 'grupoawamotos_chatwoot/bot/api_url';
    private const XML_PATH_BOT_TOKEN = 'grupoawamotos_chatwoot/bot/api_token';
    private const XML_PATH_ADMIN_TOKEN = 'grupoawamotos_chatwoot/bot/admin_api_token';
    private const XML_PATH_BOT_WEBHOOK_TOKEN = 'grupoawamotos_chatwoot/bot/secret_token';

    /** @var array<string, array{label: string, team_id: int, message: string}> */
    private const MENU_OPTIONS = [
        '1' => [
            'label'   => 'vendas',
            'team_id' => 6,
            'message' => 'Conectando você com nossa equipe de vendas! 🛒 Um momento...',
        ],
        '2' => [
            'label'   => 'suporte',
            'team_id' => 7,
            'message' => 'Vou conectar você com o suporte para rastrear seu pedido! 📦',
        ],
        '3' => [
            'label'   => 'fitment',
            'team_id' => 6,
            'message' => 'Vou conectar você com um especialista em compatibilidade! 🔧',
        ],
        '4' => [
            'label'   => 'b2b',
            'team_id' => 8,
            'message' => 'Conectando com a equipe B2B! 🏢 Enquanto isso, você pode iniciar seu cadastro em: https://awamotos.com/b2b/register',
        ],
        '5' => [
            'label'   => 'troca',
            'team_id' => 7,
            'message' => 'Vou direcionar para o suporte de trocas e devoluções! 🔄',
        ],
        '6' => [
            'label'   => 'vendas',
            'team_id' => 6,
            'message' => 'Transferindo para um atendente! 💬 Aguarde um momento...',
        ],
    ];

    private const GREETING_MESSAGE = "Olá! 👋 Bem-vindo à **AWA Motos**!\n\nComo posso ajudar? Escolha uma opção:\n\n" .
        "1️⃣ 🛒 Comprar / Consultar preços\n" .
        "2️⃣ 📦 Rastrear meu pedido\n" .
        "3️⃣ 🔧 Verificar compatibilidade (fitment)\n" .
        "4️⃣ 🏢 Quero ser cliente B2B\n" .
        "5️⃣ 🔄 Troca ou devolução\n" .
        "6️⃣ 💬 Falar com um atendente\n\n" .
        "Digite o **número** da opção desejada.";

    public function __construct(
        private readonly HttpRequest $request,
        private readonly JsonFactory $jsonFactory,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly EncryptorInterface $encryptor,
        private readonly LoggerInterface $logger,
        private readonly MagentoCurl $curlClient
    ) {
    }

    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }

    public function execute(): Json
    {
        $result = $this->jsonFactory->create();
        $rawBody = (string) $this->request->getContent();

        if (!$this->validateSignature($rawBody)) {
            $this->logger->warning('Chatwoot Bot: assinatura inválida', [
                'ip' => $this->request->getClientIp(true),
            ]);
            return $result->setHttpResponseCode(403)->setData(['error' => 'Forbidden']);
        }

        $payload = json_decode($rawBody, true);
        if (!is_array($payload)) {
            return $result->setHttpResponseCode(400)->setData(['error' => 'Invalid payload']);
        }

        $event = $payload['event'] ?? '';
        $this->logger->debug('Chatwoot Bot: evento recebido', ['event' => $event]);

        try {
            $this->handleEvent($payload);
        } catch (\Throwable $e) {
            $this->logger->error('Chatwoot Bot: erro ao processar evento', [
                'event'   => $event,
                'error'   => $e->getMessage(),
            ]);
        }

        return $result->setData(['status' => 'ok']);
    }

    private function handleEvent(array $payload): void
    {
        $event = $payload['event'] ?? '';

        switch ($event) {
            case 'conversation_created':
                $this->onConversationCreated($payload);
                break;

            case 'message_created':
                $this->onMessageCreated($payload);
                break;

            default:
                // Ignora outros eventos (conversation_updated, etc.)
                break;
        }
    }

    private function onConversationCreated(array $payload): void
    {
        $conversationId = $payload['id'] ?? null;
        if ($conversationId === null) {
            return;
        }

        $this->sendMessage((int) $conversationId, self::GREETING_MESSAGE);

        $this->logger->info('Chatwoot Bot: menu de triagem enviado', [
            'conversation_id' => $conversationId,
        ]);
    }

    private function onMessageCreated(array $payload): void
    {
        // Ignora mensagens do próprio bot ou de agentes
        $messageType = $payload['message_type'] ?? '';
        if ($messageType !== 'incoming') {
            return;
        }

        // Ignora se a conversa já tem agente atribuído (bot já finalizou)
        $conversation = $payload['conversation'] ?? [];
        $assignee = $conversation['assignee'] ?? null;
        if ($assignee !== null) {
            return;
        }

        $content = trim((string) ($payload['content'] ?? ''));
        $conversationId = $conversation['id'] ?? ($payload['conversation_id'] ?? null);

        if ($conversationId === null) {
            return;
        }

        $conversationId = (int) $conversationId;

        // Verifica se é uma opção do menu
        $choice = $this->extractChoice($content);

        if ($choice === null) {
            // Não entendeu — repete o menu
            $this->sendMessage(
                $conversationId,
                "Não entendi sua escolha. 🤔\n\nPor favor, digite o **número** de 1 a 6:\n\n" .
                "1️⃣ Comprar / Preços\n" .
                "2️⃣ Rastrear pedido\n" .
                "3️⃣ Compatibilidade\n" .
                "4️⃣ Cliente B2B\n" .
                "5️⃣ Troca / Devolução\n" .
                "6️⃣ Falar com atendente"
            );
            return;
        }

        $option = self::MENU_OPTIONS[$choice];

        // 1. Envia mensagem de confirmação
        $this->sendMessage($conversationId, $option['message']);

        // 2. Atribui label
        $this->addLabel($conversationId, $option['label']);

        // 3. Atribui team
        $this->assignTeam($conversationId, $option['team_id']);

        // 4. Desativa o bot (handoff para agente humano)
        $this->toggleBotHandoff($conversationId);

        $this->logger->info('Chatwoot Bot: triagem concluída', [
            'conversation_id' => $conversationId,
            'choice'          => $choice,
            'label'           => $option['label'],
            'team_id'         => $option['team_id'],
        ]);
    }

    /**
     * Extrai a escolha do usuário do texto da mensagem.
     */
    private function extractChoice(string $content): ?string
    {
        // Remove emojis e espaços extras
        $clean = preg_replace('/[^\d]/', '', $content) ?? '';

        // Aceita apenas dígitos de 1 a 6
        if (strlen($clean) >= 1) {
            $digit = $clean[0];
            if (isset(self::MENU_OPTIONS[$digit])) {
                return $digit;
            }
        }

        // Tenta detectar por palavra-chave
        $keywords = [
            'comprar'         => '1',
            'preco'           => '1',
            'preço'           => '1',
            'precos'          => '1',
            'produto'         => '1',
            'rastrear'        => '2',
            'rastreio'        => '2',
            'rastreamento'    => '2',
            'pedido'          => '2',
            'entrega'         => '2',
            'compatib'        => '3',
            'fitment'         => '3',
            'serve'           => '3',
            'encaixa'         => '3',
            'cabe'            => '3',
            'b2b'             => '4',
            'cnpj'            => '4',
            'atacado'         => '4',
            'empresa'         => '4',
            'troca'           => '5',
            'devol'           => '5',
            'devolução'       => '5',
            'defeito'         => '5',
            'atendente'       => '6',
            'humano'          => '6',
            'pessoa'          => '6',
        ];

        $lower = mb_strtolower($content);
        foreach ($keywords as $keyword => $option) {
            if (str_contains($lower, $keyword)) {
                return $option;
            }
        }

        return null;
    }

    private function validateSignature(string $body): bool
    {
        $secretToken = $this->getBotWebhookToken();
        if ($secretToken === '') {
            // Token não configurado — modo permissivo
            return true;
        }

        $signatureHeader = (string) $this->request->getServer('HTTP_X_CHATWOOT_SIGNATURE', '');
        if ($signatureHeader === '') {
            return false;
        }

        $expected = 'sha256=' . hash_hmac('sha256', $body, $secretToken);

        return hash_equals($expected, $signatureHeader);
    }

    private function getBotWebhookToken(): string
    {
        $value = (string) $this->scopeConfig->getValue(self::XML_PATH_BOT_WEBHOOK_TOKEN);
        if ($value === '') {
            return '';
        }
        return $this->encryptor->decrypt($value);
    }

    /**
     * Envia uma mensagem na conversa via API do Chatwoot.
     */
    private function sendMessage(int $conversationId, string $content): void
    {
        $this->chatwootApi(
            "conversations/{$conversationId}/messages",
            'POST',
            [
                'content'      => $content,
                'message_type' => 'outgoing',
                'private'      => false,
            ]
        );
    }

    /**
     * Adiciona uma label à conversa (requer admin token).
     */
    private function addLabel(int $conversationId, string $label): void
    {
        // Primeiro busca labels existentes
        $response = $this->chatwootApi(
            "conversations/{$conversationId}/labels",
            'GET',
            [],
            true
        );

        $existing = [];
        if (is_array($response) && isset($response['payload'])) {
            $existing = $response['payload'];
        }

        $labels = array_unique(array_merge($existing, [$label]));

        $this->chatwootApi(
            "conversations/{$conversationId}/labels",
            'POST',
            ['labels' => $labels],
            true
        );
    }

    /**
     * Atribui a conversa a um team (requer admin token).
     */
    private function assignTeam(int $conversationId, int $teamId): void
    {
        $this->chatwootApi(
            "conversations/{$conversationId}/assignments",
            'POST',
            ['team_id' => $teamId],
            true
        );
    }

    /**
     * Faz o handoff do bot para um agente humano.
     * Muda o status da conversa para "open" e remove o bot.
     */
    private function toggleBotHandoff(int $conversationId): void
    {
        $this->chatwootApi(
            "conversations/{$conversationId}/toggle_status",
            'POST',
            ['status' => 'open'],
            true
        );
    }

    /**
     * Chamada genérica à API do Chatwoot via Magento HTTP Client.
     *
     * @param bool $useAdminToken Usar admin token em vez do bot token (para labels/assignments)
     * @return array<string, mixed>|null
     */
    private function chatwootApi(string $endpoint, string $method, array $data = [], bool $useAdminToken = false): ?array
    {
        $baseUrl = rtrim((string) $this->scopeConfig->getValue(self::XML_PATH_BOT_API_URL), '/');
        if ($baseUrl === '') {
            $baseUrl = rtrim((string) $this->scopeConfig->getValue(self::XML_PATH_BASE_URL), '/');
        }

        $token = $useAdminToken ? $this->getAdminApiToken() : $this->getBotApiToken();
        if ($token === '') {
            $token = $this->getBotApiToken();
        }

        if ($baseUrl === '' || $token === '') {
            $this->logger->error('Chatwoot Bot: base_url ou api_token não configurado');
            return null;
        }

        $url = "{$baseUrl}/api/v1/accounts/1/{$endpoint}";

        try {
            $this->curlClient->setHeaders([
                'Content-Type'     => 'application/json',
                'api_access_token' => $token,
            ]);
            $this->curlClient->setTimeout(10);

            if ($method === 'GET') {
                $this->curlClient->get($url);
            } else {
                $this->curlClient->post($url, json_encode($data));
            }

            $status = $this->curlClient->getStatus();
            $responseBody = $this->curlClient->getBody();
        } catch (\Exception $e) {
            $this->logger->error('Chatwoot Bot HTTP error', [
                'endpoint' => $endpoint,
                'error'    => $e->getMessage(),
            ]);
            return null;
        }

        if ($status >= 400) {
            $context = [
                'endpoint' => $endpoint,
                'status'   => $status,
                'body'     => mb_substr((string) $responseBody, 0, 200),
            ];

            if ($this->isExpectedApiRejection($endpoint, $status)) {
                $this->logger->warning('Chatwoot Bot API non-critical response', $context);
            } else {
                $this->logger->error('Chatwoot Bot API error', $context);
            }

            return null;
        }

        return json_decode((string) $responseBody, true) ?: [];
    }

    private function getBotApiToken(): string
    {
        $value = (string) $this->scopeConfig->getValue(self::XML_PATH_BOT_TOKEN);
        if ($value === '') {
            return '';
        }
        return $this->encryptor->decrypt($value);
    }

    private function getAdminApiToken(): string
    {
        $value = (string) $this->scopeConfig->getValue(self::XML_PATH_ADMIN_TOKEN);
        if ($value === '') {
            return '';
        }
        return $this->encryptor->decrypt($value);
    }

    private function isExpectedApiRejection(string $endpoint, int $status): bool
    {
        if (!in_array($status, [400, 401, 404], true)) {
            return false;
        }

        $expectedEndpointPatterns = [
            '/conversations/\d+/labels$',
            '/conversations/\d+/assignments$',
            '/conversations/\d+/toggle_status$',
            '/conversations/\d+/messages$',
        ];

        foreach ($expectedEndpointPatterns as $pattern) {
            if (preg_match('#' . $pattern . '#', $endpoint) === 1) {
                return true;
            }
        }

        return false;
    }
}
