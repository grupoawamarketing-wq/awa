<?php

declare(strict_types=1);

namespace GrupoAwamotos\Chatwoot\Controller\Webhook;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Encryption\EncryptorInterface;
use Psr\Log\LoggerInterface;

class Receive implements HttpPostActionInterface, CsrfAwareActionInterface
{
    private const XML_PATH_WEBHOOK_TOKEN = 'grupoawamotos_chatwoot/webhook/secret_token';

    public function __construct(
        private readonly HttpRequest $request,
        private readonly JsonFactory $jsonFactory,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly EncryptorInterface $encryptor,
        private readonly LoggerInterface $logger,
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
            $this->logger->warning('Chatwoot webhook: assinatura inválida', [
                'ip' => $this->request->getClientIp(true),
            ]);

            return $result->setHttpResponseCode(403)->setData(['error' => 'Forbidden']);
        }

        $payload = json_decode($rawBody, true);
        if (!is_array($payload)) {
            return $result->setHttpResponseCode(400)->setData(['error' => 'Invalid payload']);
        }

        $this->processEvent($payload);

        return $result->setData(['status' => 'ok']);
    }

    private function validateSignature(string $body): bool
    {
        $secretToken = $this->getWebhookToken();
        if ($secretToken === '') {
            $this->logger->critical('Chatwoot webhook: secret_token não configurado — request bloqueado por segurança', [
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

    private function getWebhookToken(): string
    {
        $value = (string) $this->scopeConfig->getValue(self::XML_PATH_WEBHOOK_TOKEN);

        if ($value === '') {
            return '';
        }

        return $this->encryptor->decrypt($value);
    }

    private function processEvent(array $payload): void
    {
        $event = $payload['event'] ?? 'unknown';

        $logContext = [
            'event'           => $event,
            'conversation_id' => $payload['id'] ?? null,
        ];

        switch ($event) {
            case 'conversation_created':
                $this->onConversationCreated($payload, $logContext);
                break;
            case 'conversation_status_changed':
                $this->onConversationStatusChanged($payload, $logContext);
                break;
            case 'conversation_updated':
                $this->onConversationUpdated($payload, $logContext);
                break;
            case 'conversation_resolved':
                // Evento legado — pode ser enviado por versões antigas
                $this->onConversationResolved($payload, $logContext);
                break;
            case 'message_created':
                $this->onMessageCreated($payload, $logContext);
                break;
            default:
                $this->logger->debug('Chatwoot webhook: evento não tratado', $logContext);
        }
    }

    private function onConversationCreated(array $payload, array $logContext): void
    {
        $meta    = $payload['meta'] ?? [];
        $contact = $meta['sender'] ?? [];

        $logContext['contact_name']  = $contact['name'] ?? null;
        $logContext['contact_email'] = $contact['email'] ?? null;
        $logContext['channel']       = $payload['channel'] ?? null;
        $logContext['inbox_id']      = $payload['inbox_id'] ?? null;

        $this->logger->info('Chatwoot: nova conversa iniciada', $logContext);

        $this->logConversationEvent(
            'conversation_created',
            (int) ($payload['id'] ?? 0),
            $contact['email'] ?? null,
            null
        );
    }

    private function onConversationStatusChanged(array $payload, array $logContext): void
    {
        $status  = $payload['status'] ?? 'unknown';
        $meta    = $payload['meta'] ?? [];
        $contact = $meta['sender'] ?? [];

        $logContext['status']        = $status;
        $logContext['contact_email'] = $contact['email'] ?? null;

        $this->logger->info('Chatwoot: status de conversa alterado', $logContext);

        if ($status === 'resolved') {
            $this->onConversationResolved($payload, $logContext);
        }
    }

    private function onConversationUpdated(array $payload, array $logContext): void
    {
        $meta    = $payload['meta'] ?? [];
        $contact = $meta['sender'] ?? [];

        $logContext['status']         = $payload['status'] ?? null;
        $logContext['contact_email']  = $contact['email'] ?? null;
        $logContext['assignee']       = ($payload['meta']['assignee']['name'] ?? null);

        $this->logger->debug('Chatwoot: conversa atualizada', $logContext);
    }

    private function onConversationResolved(array $payload, array $logContext): void
    {
        $meta    = $payload['meta'] ?? [];
        $contact = $meta['sender'] ?? [];

        $logContext['contact_email'] = $contact['email'] ?? null;
        $logContext['updated_at']    = $payload['updated_at'] ?? null;

        $this->logger->info('Chatwoot: conversa resolvida', $logContext);

        $this->logConversationEvent(
            'resolved',
            (int) ($payload['id'] ?? 0),
            $contact['email'] ?? null,
            $payload['meta']['assignee']['name'] ?? null
        );
    }

    private function onMessageCreated(array $payload, array $logContext): void
    {
        // Registra apenas mensagens enviadas por agentes (outgoing = 1)
        $messageType = $payload['message_type'] ?? 'incoming';
        if ($messageType !== 'outgoing') {
            return;
        }

        $sender = $payload['sender'] ?? [];

        $logContext['agent']           = $sender['name'] ?? null;
        $logContext['message_preview'] = mb_substr((string) ($payload['content'] ?? ''), 0, 100);

        $this->logger->debug('Chatwoot: mensagem enviada por agente', $logContext);
    }

    /**
     * Registra evento de conversa no log de atendentes B2B.
     *
     * Vincula email do contato Chatwoot ao customer/attendant do Magento.
     */
    private function logConversationEvent(
        string $action,
        int $conversationId,
        ?string $contactEmail,
        ?string $agentName
    ): void {
        if ($conversationId === 0) {
            return;
        }

        try {
            $connection = $this->resource->getConnection();
            $logTable = $this->resource->getTableName('grupoawamotos_b2b_attendant_log');

            if (!$connection->isTableExists($logTable)) {
                return;
            }

            $attendantId = null;
            $customerId = null;

            if ($contactEmail !== null && $contactEmail !== '') {
                $customerId = (int) $connection->fetchOne(
                    $connection->select()
                        ->from($this->resource->getTableName('customer_entity'), ['entity_id'])
                        ->where('email = ?', $contactEmail)
                        ->limit(1)
                );

                if ($customerId > 0) {
                    $attendantId = (int) $connection->fetchOne(
                        $connection->select()
                            ->from(
                                $this->resource->getTableName('grupoawamotos_b2b_customer_attendant'),
                                ['attendant_id']
                            )
                            ->where('customer_id = ?', $customerId)
                            ->limit(1)
                    );
                    $attendantId = $attendantId > 0 ? $attendantId : null;
                } else {
                    $customerId = null;
                }
            }

            $connection->insert($logTable, [
                'attendant_id' => $attendantId,
                'customer_id'  => $customerId,
                'action'       => 'chatwoot_' . $action,
                'details'      => json_encode([
                    'conversation_id' => $conversationId,
                    'contact_email'   => $contactEmail,
                    'agent_name'      => $agentName,
                ]),
                'created_at'   => (new \DateTime())->format('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            $this->logger->warning('Chatwoot webhook: erro ao gravar log de atendente', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
