<?php

/**
 * Serviço de notificações via WhatsApp
 * Suporta múltiplos providers: Z-API, Evolution API, Twilio, Meta Cloud API
 */

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Model\Notification;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Store\Model\ScopeInterface;
use Psr\Log\LoggerInterface;

class WhatsAppService
{
    private const CONFIG_PATH_ENABLED = 'grupoawamotos_b2b/whatsapp/enabled';
    private const CONFIG_PATH_PROVIDER = 'grupoawamotos_b2b/whatsapp/provider';
    private const CONFIG_PATH_API_URL = 'grupoawamotos_b2b/whatsapp/api_url';
    private const CONFIG_PATH_API_KEY = 'grupoawamotos_b2b/whatsapp/api_key';
    private const CONFIG_PATH_CLIENT_TOKEN = 'grupoawamotos_b2b/whatsapp/client_token';
    private const CONFIG_PATH_INSTANCE_ID = 'grupoawamotos_b2b/whatsapp/instance_id';
    private const CONFIG_PATH_DEFAULT_NUMBER = 'grupoawamotos_b2b/whatsapp/default_number';

    private ScopeConfigInterface $scopeConfig;
    private Curl $curl;
    private LoggerInterface $logger;

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        Curl $curl,
        LoggerInterface $logger
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->curl = $curl;
        $this->logger = $logger;
    }

    /**
     * Verifica se WhatsApp está habilitado
     */
    public function isEnabled(): bool
    {
        return (bool) $this->scopeConfig->getValue(self::CONFIG_PATH_ENABLED, ScopeInterface::SCOPE_STORE);
    }

    /**
     * Envia mensagem de texto simples
     */
    public function sendText(string $phone, string $message): array
    {
        if (!$this->isEnabled()) {
            return ['success' => false, 'message' => 'WhatsApp desabilitado'];
        }

        $phone = $this->formatPhone($phone);
        $provider = $this->getProvider();

        // Timeout curto para não bloquear o request do usuário
        $this->curl->setOption(CURLOPT_TIMEOUT, 5);
        $this->curl->setOption(CURLOPT_CONNECTTIMEOUT, 3);

        try {
            switch ($provider) {
                case 'zapi':
                    return $this->sendViaZApi($phone, $message);
                case 'evolution':
                    return $this->sendViaEvolution($phone, $message);
                case 'twilio':
                    return $this->sendViaTwilio($phone, $message);
                case 'meta':
                    return $this->sendViaMeta($phone, $message);
                default:
                    return $this->sendViaZApi($phone, $message);
            }
        } catch (\Exception $e) {
            $this->logger->error('WhatsApp Send Error: ' . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Envia mensagem com template (para Meta Cloud API)
     */
    public function sendTemplate(string $phone, string $templateName, array $params = []): array
    {
        if (!$this->isEnabled()) {
            return ['success' => false, 'message' => 'WhatsApp desabilitado'];
        }

        $phone = $this->formatPhone($phone);

        // Para Meta Cloud API com templates aprovados
        if ($this->getProvider() === 'meta') {
            return $this->sendMetaTemplate($phone, $templateName, $params);
        }

        // Para outros providers, monta a mensagem a partir do template
        $message = $this->buildTemplateMessage($templateName, $params);
        return $this->sendText($phone, $message);
    }

    /**
     * Envia notificação para equipe de atendimento
     */
    public function notifyTeam(string $message, ?string $department = null): array
    {
        $results = [];
        $numbers = $this->getTeamNumbers($department);

        foreach ($numbers as $number) {
            $results[] = $this->sendText($number, $message);
        }

        return $results;
    }

    /**
     * Notifica novo cadastro B2B
     */
    public function notifyNewB2BRegistration(array $customerData): array
    {
        $message = $this->buildMessage('new_registration', $customerData);
        return $this->notifyTeam($message, 'b2b');
    }

    /**
     * Notifica nova cotação
     */
    public function notifyNewQuote(array $quoteData): array
    {
        $message = $this->buildMessage('new_quote', $quoteData);
        return $this->notifyTeam($message, 'sales');
    }

    /**
     * Notifica novo pedido B2B
     */
    public function notifyNewOrder(array $orderData): array
    {
        $message = $this->buildMessage('new_order', $orderData);

        // Notifica equipe
        $teamResult = $this->notifyTeam($message, 'sales');

        // Notifica cliente se tiver telefone
        $customerResult = [];
        if (!empty($orderData['customer_phone'])) {
            $customerMessage = $this->buildMessage('order_confirmation', $orderData);
            $customerResult = $this->sendText($orderData['customer_phone'], $customerMessage);
        }

        return ['team' => $teamResult, 'customer' => $customerResult];
    }

    /**
     * Notifica aprovação de cliente B2B
     */
    public function notifyCustomerApproval(array $customerData): array
    {
        if (empty($customerData['phone'])) {
            return ['success' => false, 'message' => 'Telefone não informado'];
        }

        $message = $this->buildMessage('customer_approved', $customerData);
        return $this->sendText($customerData['phone'], $message);
    }

    /**
     * Notifica rejeição de cliente B2B
     */
    public function notifyCustomerRejection(array $customerData): array
    {
        if (empty($customerData['phone'])) {
            return ['success' => false, 'message' => 'Telefone não informado'];
        }

        $message = $this->buildMessage('customer_rejected', $customerData);
        return $this->sendText($customerData['phone'], $message);
    }

    /**
     * Notifica resposta de cotação
     */
    public function notifyQuoteResponse(array $quoteData): array
    {
        if (empty($quoteData['customer_phone'])) {
            return ['success' => false, 'message' => 'Telefone não informado'];
        }

        $message = $this->buildMessage('quote_response', $quoteData);
        return $this->sendText($quoteData['customer_phone'], $message);
    }

    /**
     * Notifica atualização de status do pedido
     */
    public function notifyOrderStatusUpdate(array $orderData): array
    {
        if (empty($orderData['customer_phone'])) {
            return ['success' => false, 'message' => 'Telefone não informado'];
        }

        $message = $this->buildMessage('order_status', $orderData);
        return $this->sendText($orderData['customer_phone'], $message);
    }

    // ==================== PROVIDERS ====================

    /**
     * Envia via Z-API
     */
    private function sendViaZApi(string $phone, string $message): array
    {
        $apiUrl = $this->getConfigValue(self::CONFIG_PATH_API_URL) ?: 'https://api.z-api.io';
        $instanceId = $this->getConfigValue(self::CONFIG_PATH_INSTANCE_ID);
        $apiKey = $this->getConfigValue(self::CONFIG_PATH_API_KEY);

        // Formato correto da Z-API: /instances/{instanceId}/token/{token}/send-text
        $url = rtrim($apiUrl, '/') . "/instances/{$instanceId}/token/{$apiKey}/send-text";

        $headers = ['Content-Type' => 'application/json'];
        $clientToken = $this->getConfigValue(self::CONFIG_PATH_CLIENT_TOKEN);
        if ($clientToken) {
            $headers['Client-Token'] = $clientToken;
        }
        $this->curl->setHeaders($headers);

        // Z-API espera o campo 'phone' com código do país
        $payload = json_encode([
            'phone' => $phone,
            'message' => $message
        ]);

        $this->curl->post($url, $payload);
        $responseBody = $this->curl->getBody();
        $response = json_decode($responseBody, true);

        // Z-API retorna zapiMessageId em caso de sucesso
        $success = isset($response['zapiMessageId']) ||
                   (isset($response['status']) && $response['status'] === 'success');

        return [
            'success' => $success,
            'message_id' => $response['zapiMessageId'] ?? $response['messageId'] ?? null,
            'response' => $response
        ];
    }

    /**
     * Envia via Evolution API
     */
    private function sendViaEvolution(string $phone, string $message): array
    {
        $apiUrl = $this->getConfigValue(self::CONFIG_PATH_API_URL);
        $instanceId = $this->getConfigValue(self::CONFIG_PATH_INSTANCE_ID);
        $apiKey = $this->getConfigValue(self::CONFIG_PATH_API_KEY);

        $url = rtrim($apiUrl, '/') . "/message/sendText/{$instanceId}";

        $this->curl->setHeaders([
            'Content-Type' => 'application/json',
            'apikey' => $apiKey
        ]);

        $payload = json_encode([
            'number' => $phone,
            'text' => $message
        ]);

        $this->curl->post($url, $payload);
        $response = json_decode($this->curl->getBody(), true);

        return [
            'success' => isset($response['key']['id']),
            'message_id' => $response['key']['id'] ?? null,
            'response' => $response
        ];
    }

    /**
     * Envia via Twilio
     */
    private function sendViaTwilio(string $phone, string $message): array
    {
        $accountSid = $this->getConfigValue(self::CONFIG_PATH_INSTANCE_ID);
        $authToken = $this->getConfigValue(self::CONFIG_PATH_API_KEY);
        $fromNumber = $this->getConfigValue(self::CONFIG_PATH_DEFAULT_NUMBER);

        $url = "https://api.twilio.com/2010-04-01/Accounts/{$accountSid}/Messages.json";

        $this->curl->setCredentials($accountSid, $authToken);
        $this->curl->setHeaders(['Content-Type' => 'application/x-www-form-urlencoded']);

        $payload = http_build_query([
            'To' => "whatsapp:+{$phone}",
            'From' => "whatsapp:+{$fromNumber}",
            'Body' => $message
        ]);

        $this->curl->post($url, $payload);
        $response = json_decode($this->curl->getBody(), true);

        return [
            'success' => isset($response['sid']),
            'message_id' => $response['sid'] ?? null,
            'response' => $response
        ];
    }

    /**
     * Envia via Meta Cloud API
     */
    private function sendViaMeta(string $phone, string $message): array
    {
        $phoneNumberId = $this->getConfigValue(self::CONFIG_PATH_INSTANCE_ID);
        $accessToken = $this->getConfigValue(self::CONFIG_PATH_API_KEY);

        $url = "https://graph.facebook.com/v18.0/{$phoneNumberId}/messages";

        $this->curl->setHeaders([
            'Content-Type' => 'application/json',
            'Authorization' => "Bearer {$accessToken}"
        ]);

        $payload = json_encode([
            'messaging_product' => 'whatsapp',
            'to' => $phone,
            'type' => 'text',
            'text' => ['body' => $message]
        ]);

        $this->curl->post($url, $payload);
        $response = json_decode($this->curl->getBody(), true);

        return [
            'success' => isset($response['messages'][0]['id']),
            'message_id' => $response['messages'][0]['id'] ?? null,
            'response' => $response
        ];
    }

    /**
     * Envia template via Meta Cloud API
     */
    private function sendMetaTemplate(string $phone, string $templateName, array $params): array
    {
        $phoneNumberId = $this->getConfigValue(self::CONFIG_PATH_INSTANCE_ID);
        $accessToken = $this->getConfigValue(self::CONFIG_PATH_API_KEY);

        $url = "https://graph.facebook.com/v18.0/{$phoneNumberId}/messages";

        $this->curl->setHeaders([
            'Content-Type' => 'application/json',
            'Authorization' => "Bearer {$accessToken}"
        ]);

        $components = [];
        if (!empty($params)) {
            $parameters = array_map(fn($value) => ['type' => 'text', 'text' => $value], array_values($params));
            $components[] = ['type' => 'body', 'parameters' => $parameters];
        }

        $payload = json_encode([
            'messaging_product' => 'whatsapp',
            'to' => $phone,
            'type' => 'template',
            'template' => [
                'name' => $templateName,
                'language' => ['code' => 'pt_BR'],
                'components' => $components
            ]
        ]);

        $this->curl->post($url, $payload);
        $response = json_decode($this->curl->getBody(), true);

        return [
            'success' => isset($response['messages'][0]['id']),
            'message_id' => $response['messages'][0]['id'] ?? null,
            'response' => $response
        ];
    }

    // ==================== HELPERS ====================

    /**
     * Formata número de telefone para padrão internacional
     */
    private function formatPhone(string $phone): string
    {
        // Remove caracteres não numéricos
        $phone = preg_replace('/\D/', '', $phone);

        // Adiciona código do Brasil se não tiver
        if (strlen($phone) === 11 || strlen($phone) === 10) {
            $phone = '55' . $phone;
        }

        return $phone;
    }

    /**
     * Obtém provider configurado
     */
    private function getProvider(): string
    {
        return $this->getConfigValue(self::CONFIG_PATH_PROVIDER) ?: 'zapi';
    }

    /**
     * Obtém valor de configuração
     */
    private function getConfigValue(string $path): ?string
    {
        return $this->scopeConfig->getValue($path, ScopeInterface::SCOPE_STORE);
    }

    /**
     * Obtém números da equipe por departamento
     */
    private function getTeamNumbers(?string $department = null): array
    {
        $path = 'grupoawamotos_b2b/whatsapp/team_numbers';
        if ($department) {
            $path = "grupoawamotos_b2b/whatsapp/team_{$department}_numbers";
        }

        $numbers = $this->getConfigValue($path);
        if (!$numbers) {
            $numbers = $this->getConfigValue('grupoawamotos_b2b/whatsapp/team_numbers');
        }

        return array_filter(array_map('trim', explode(',', $numbers ?? '')));
    }

    /**
     * Constrói mensagem a partir de template
     */
    private function buildMessage(string $type, array $data): string
    {
        $templates = [
            'new_registration' => "🆕 *Novo Cadastro B2B*\n\n" .
                "👤 *Nome:* {customer_name}\n" .
                "🏢 *Empresa:* {razao_social}\n" .
                "📋 *CNPJ:* {cnpj}\n" .
                "📧 *Email:* {email}\n" .
                "📱 *Telefone:* {phone}\n\n" .
                "⏳ Aguardando aprovação no painel admin.",

            'new_quote' => "📋 *Nova Solicitação de Cotação*\n\n" .
                "👤 *Cliente:* {customer_name}\n" .
                "🏢 *Empresa:* {company}\n" .
                "📦 *Itens:* {items_count}\n" .
                "💰 *Valor estimado:* {estimated_total}\n\n" .
                "🔗 Acesse o painel para responder.",

            'new_order' => "🛒 *Novo Pedido B2B*\n\n" .
                "📦 *Pedido:* #{order_id}\n" .
                "👤 *Cliente:* {customer_name}\n" .
                "🏢 *Empresa:* {company}\n" .
                "💰 *Total:* {total}\n" .
                "📍 *Cidade:* {city}\n\n" .
                "✅ Pedido registrado com sucesso!",

            'order_confirmation' => "✅ *Pedido Confirmado!*\n\n" .
                "Olá {customer_name}!\n\n" .
                "Seu pedido *#{order_id}* foi recebido com sucesso.\n\n" .
                "💰 *Total:* {total}\n" .
                "📦 *Itens:* {items_count}\n\n" .
                "Acompanhe o status do seu pedido em nossa loja.\n\n" .
                "Obrigado pela preferência! 🙏",

            'customer_approved' => "✅ *Cadastro B2B Aprovado!*\n\n" .
                "Olá {customer_name}!\n\n" .
                "Seu cadastro B2B foi *aprovado*! 🎉\n\n" .
                "Agora você tem acesso a:\n" .
                "• Preços exclusivos\n" .
                "• Descontos especiais\n" .
                "• Condições de pagamento diferenciadas\n\n" .
                "Acesse nossa loja e aproveite!",

            'customer_rejected' => "❌ *Cadastro B2B Não Aprovado*\n\n" .
                "Olá {customer_name},\n\n" .
                "Infelizmente seu cadastro B2B não foi aprovado no momento.\n\n" .
                "Se tiver dúvidas, entre em contato conosco.",

            'quote_response' => "📋 *Cotação Respondida!*\n\n" .
                "Olá {customer_name}!\n\n" .
                "Sua cotação *#{quote_id}* foi respondida.\n\n" .
                "💰 *Valor:* {quoted_total}\n" .
                "📅 *Válido até:* {expires_at}\n\n" .
                "Acesse sua conta para ver os detalhes e aprovar.",

            'order_status' => "📦 *Atualização do Pedido*\n\n" .
                "Olá {customer_name}!\n\n" .
                "O pedido *#{order_id}* teve uma atualização:\n\n" .
                "📌 *Status:* {status}\n" .
                "{tracking_info}\n\n" .
                "Acompanhe em nossa loja."
        ];

        $message = $templates[$type] ?? $templates['new_registration'];

        foreach ($data as $key => $value) {
            $message = str_replace("{{$key}}", (string) $value, $message);
        }

        // Remove placeholders não preenchidos
        $message = preg_replace('/\{[a-z_]+\}/', '', $message);

        return $message;
    }

    /**
     * Constrói mensagem de template
     */
    private function buildTemplateMessage(string $templateName, array $params): string
    {
        return $this->buildMessage($templateName, $params);
    }
}
