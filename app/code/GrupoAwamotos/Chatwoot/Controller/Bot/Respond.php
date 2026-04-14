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
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\HTTP\Client\Curl as MagentoCurl;
use Psr\Log\LoggerInterface;

/**
 * Bot de Triagem — Chatwoot AgentBot Webhook
 *
 * Recebe eventos do AgentBot do Chatwoot e responde com menu interativo.
 * Ao receber a escolha do visitante, atribui label + team e entrega ao agente humano.
 */
class Respond implements HttpPostActionInterface, CsrfAwareActionInterface
{
    private const XML_PATH_BASE_URL = 'grupoawamotos_chatwoot/general/base_url';
    private const XML_PATH_ACCOUNT_ID = 'grupoawamotos_chatwoot/general/account_id';
    private const XML_PATH_BOT_API_URL = 'grupoawamotos_chatwoot/bot/api_url';
    private const XML_PATH_BOT_TOKEN = 'grupoawamotos_chatwoot/bot/api_token';
    private const XML_PATH_ADMIN_TOKEN = 'grupoawamotos_chatwoot/bot/admin_api_token';
    private const XML_PATH_BOT_WEBHOOK_TOKEN = 'grupoawamotos_chatwoot/bot/secret_token';

    /** @var array<string, array{label: string, team_id: int, message: string}> */
    private const MENU_OPTIONS = [
        '1' => [
            'label'   => 'vendas',
            'team_id' => 6,
            'message' => 'Transferindo para um atendente! 💬 Aguarde um momento...',
        ],
        '2' => [
            'label'   => 'suporte',
            'team_id' => 7,
            'message' => 'Vou conectar você com o suporte para rastrear seu pedido! 📦',
        ],
        '3' => [
            'label'   => 'b2b',
            'team_id' => 8,
            'message' => "Conectando com a equipe B2B! 🏢\nCadastre-se em: https://awamotos.com/b2b/register",
        ],
        '4' => [
            'label'   => 'site',
            'team_id' => 0,
            'message' => "Acesse nosso site: https://awamotos.com 🌐\nNavegue pelo catálogo completo de peças e acessórios!",
        ],
        '5' => [
            'label'   => 'pagamento',
            'team_id' => 0,
            'message' => "Aceitamos: PIX, cartão de crédito (até 12x), boleto e transferência! 💳\nPara dúvidas específicas, escolha *1 - Falar com Atendente*.",
        ],
        '6' => [
            'label'   => 'suporte',
            'team_id' => 7,
            'message' => 'Conectando com o suporte para ajudar com seu cadastro ou conta! 👤',
        ],
        '7' => [
            'label'   => 'suporte',
            'team_id' => 7,
            'message' => 'Conectando com o suporte sobre frete e entrega! 🚚',
        ],
        '8' => [
            'label'   => 'vendas',
            'team_id' => 6,
            'message' => "Vou conectar você com a equipe para buscar peças! 🔍\nOu acesse: https://awamotos.com",
        ],
        '9' => [
            'label'   => 'fitment',
            'team_id' => 6,
            'message' => "Vou conectar com um especialista em compatibilidade! 🔧\nOu consulte: https://awamotos.com/fitment",
        ],
        '0' => [
            'label'   => 'notificacoes',
            'team_id' => 0,
            'message' => "Gerencie suas notificações na sua conta: https://awamotos.com/customer/account 📱\nPara dúvidas, escolha *1 - Falar com Atendente*.",
        ],
    ];

    private const GREETING_MESSAGE = "Olá! 👋 Bem-vindo à *AWA Motos*!\n" .
        "Escolha uma opção abaixo:\n\n" .
        "1️⃣ Falar com Atendente\n" .
        "2️⃣ Rastrear Pedido\n" .
        "3️⃣ Sou Lojista (B2B)\n" .
        "4️⃣ Acessar o Site\n" .
        "5️⃣ Formas de Pagamento\n" .
        "6️⃣ Cadastro e Conta\n" .
        "7️⃣ Frete e Entrega\n" .
        "8️⃣ Buscar Peças\n" .
        "9️⃣ Buscar por Moto (Fitment)\n" .
        "0️⃣ Notificações WhatsApp\n\n" .
        "Envie o *número* da opção desejada.";

    public function __construct(
        private readonly HttpRequest $request,
        private readonly JsonFactory $jsonFactory,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly EncryptorInterface $encryptor,
        private readonly LoggerInterface $logger,
        private readonly MagentoCurl $curlClient,
        private readonly ResourceConnection $resource
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
            case 'message_created':
                $this->onMessageCreated($payload);
                break;

            default:
                break;
        }
    }

    private function onMessageCreated(array $payload): void
    {
        $messageType = $payload['message_type'] ?? '';
        if ($messageType !== 'incoming') {
            return;
        }

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

        $choice = $this->extractChoice($content);

        if ($choice === null) {
            // Mensagem não reconhecida: exibe menu de opções
            $this->sendMenuMessage($conversationId);
            return;
        }

        $option = self::MENU_OPTIONS[$choice];

        // 1. Envia mensagem de confirmação
        $this->sendMessage($conversationId, $option['message']);

        // Opções self-service (team_id = 0): envia info e reexibe o menu
        if ($option['team_id'] === 0) {
            $this->sendMenuMessage($conversationId);
            $this->logger->info('Chatwoot Bot: opção self-service', [
                'conversation_id' => $conversationId,
                'choice'          => $choice,
                'label'           => $option['label'],
            ]);
            return;
        }

        // 2. Atribui label
        $this->addLabel($conversationId, $option['label']);

        // 3. Atribui team e agente em uma única chamada à API
        $agentId = $this->resolveAgentForContact($payload, $option['label']);
        $this->assignTeamAndAgent($conversationId, $option['team_id'], $agentId);

        // 5. Desativa o bot (handoff para agente humano)
        $this->toggleBotHandoff($conversationId);

        $this->logger->info('Chatwoot Bot: triagem concluída', [
            'conversation_id' => $conversationId,
            'choice'          => $choice,
            'label'           => $option['label'],
            'team_id'         => $option['team_id'],
            'assignee_id'     => $agentId,
        ]);
    }

    /**
     * Extrai a escolha do usuário do texto da mensagem.
     */
    private function extractChoice(string $content): ?string
    {
        $clean = preg_replace('/[^\d]/', '', $content) ?? '';

        // Aceita dígitos 0-9
        if (strlen($clean) >= 1) {
            $digit = $clean[0];
            if (isset(self::MENU_OPTIONS[$digit])) {
                return $digit;
            }
        }

        // Tenta detectar por palavra-chave
        $keywords = [
            'atendente'       => '1',
            'humano'          => '1',
            'pessoa'          => '1',
            'rastrear'        => '2',
            'rastreio'        => '2',
            'rastreamento'    => '2',
            'pedido'          => '2',
            'b2b'             => '3',
            'cnpj'            => '3',
            'atacado'         => '3',
            'lojista'         => '3',
            'empresa'         => '3',
            'site'            => '4',
            'comprar'         => '4',
            'pagamento'       => '5',
            'pix'             => '5',
            'boleto'          => '5',
            'cartão'          => '5',
            'cartao'          => '5',
            'cadastro'        => '6',
            'conta'           => '6',
            'login'           => '6',
            'senha'           => '6',
            'frete'           => '7',
            'entrega'         => '7',
            'transportadora'  => '7',
            'envio'           => '7',
            'peça'            => '8',
            'peças'           => '8',
            'produto'         => '8',
            'preco'           => '8',
            'preço'           => '8',
            'compatib'        => '9',
            'fitment'         => '9',
            'serve'           => '9',
            'encaixa'         => '9',
            'cabe'            => '9',
            'moto'            => '9',
            'notifica'        => '0',
            'whatsapp'        => '0',
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
            $this->logger->critical('Chatwoot Bot: secret_token não configurado — request bloqueado por segurança', [
                'ip' => $this->request->getClientIp(true),
            ]);
            return false;
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
     * Envia uma mensagem de texto simples na conversa.
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
     * Envia o menu como mensagem de texto única formatada para WhatsApp.
     */
    private function sendMenuMessage(int $conversationId): void
    {
        $this->sendMessage($conversationId, self::GREETING_MESSAGE);
    }

    /**
     * Adiciona uma label à conversa (requer admin token).
     */
    private function addLabel(int $conversationId, string $label): void
    {
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
     * Atribui team e opcionalmente agente em uma única chamada à API (requer admin token).
     */
    private function assignTeamAndAgent(int $conversationId, int $teamId, ?int $agentId): void
    {
        $data = ['team_id' => $teamId];
        if ($agentId !== null) {
            $data['assignee_id'] = $agentId;
        }
        $this->chatwootApi(
            "conversations/{$conversationId}/assignments",
            'POST',
            $data,
            true
        );
    }

    /**
     * Resolve o chatwoot_agent_id do atendente responsável pelo contato.
     *
     * Fluxo: email do contato Chatwoot → customer Magento → attendant → chatwoot_agent_id.
     * Fallback: atendente com menor carga que tenha chatwoot_agent_id.
     */
    private function resolveAgentForContact(array $payload, string $label): ?int
    {
        try {
            $contactEmail = $this->extractContactEmail($payload);
            $connection = $this->resource->getConnection();

            if ($contactEmail !== '') {
                $customerId = (int) $connection->fetchOne(
                    $connection->select()
                        ->from($this->resource->getTableName('customer_entity'), ['entity_id'])
                        ->where('email = ?', $contactEmail)
                        ->limit(1)
                );

                if ($customerId > 0) {
                    $agentId = (int) $connection->fetchOne(
                        $connection->select()
                            ->from(
                                ['ca' => $this->resource->getTableName('grupoawamotos_b2b_customer_attendant')],
                                []
                            )
                            ->join(
                                ['a' => $this->resource->getTableName('grupoawamotos_b2b_attendants')],
                                'ca.attendant_id = a.attendant_id',
                                ['chatwoot_agent_id']
                            )
                            ->where('ca.customer_id = ?', $customerId)
                            ->where('a.is_active = ?', 1)
                            ->where('a.chatwoot_agent_id IS NOT NULL')
                            ->limit(1)
                    );

                    if ($agentId > 0) {
                        $this->logger->debug('Chatwoot Bot: agente resolvido via cliente', [
                            'email'       => $contactEmail,
                            'customer_id' => $customerId,
                            'agent_id'    => $agentId,
                        ]);
                        return $agentId;
                    }
                }
            }

            $department = in_array($label, ['vendas', 'fitment'], true) ? 'sales' : null;
            $select = $connection->select()
                ->from($this->resource->getTableName('grupoawamotos_b2b_attendants'), ['chatwoot_agent_id'])
                ->where('is_active = ?', 1)
                ->where('chatwoot_agent_id IS NOT NULL')
                ->where('customer_count < max_customers')
                ->order('customer_count ASC')
                ->limit(1);

            if ($department !== null) {
                $select->where('department = ?', $department);
            }

            $fallbackAgentId = (int) $connection->fetchOne($select);
            if ($fallbackAgentId > 0) {
                $this->logger->debug('Chatwoot Bot: agente fallback (menor carga)', [
                    'agent_id'   => $fallbackAgentId,
                    'department' => $department,
                ]);
                return $fallbackAgentId;
            }
        } catch (\Throwable $e) {
            $this->logger->warning('Chatwoot Bot: erro ao resolver agente', [
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    /**
     * Extrai o email do contato do payload do Chatwoot.
     */
    private function extractContactEmail(array $payload): string
    {
        $conversation = $payload['conversation'] ?? [];
        $meta = $conversation['meta'] ?? ($payload['meta'] ?? []);
        $contact = $meta['sender'] ?? ($conversation['contact'] ?? ($payload['sender'] ?? []));

        return trim((string) ($contact['email'] ?? ''));
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

        $accountId = max(1, (int) $this->scopeConfig->getValue(self::XML_PATH_ACCOUNT_ID));
        $url = "{$baseUrl}/api/v1/accounts/{$accountId}/{$endpoint}";

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
