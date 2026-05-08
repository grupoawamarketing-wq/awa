<?php

declare(strict_types=1);

namespace GrupoAwamotos\TawkIntegration\Controller\Webhook;

use GrupoAwamotos\TawkIntegration\Helper\Config;
use GrupoAwamotos\TawkIntegration\Model\AttendantService;
use GrupoAwamotos\TawkIntegration\Model\ChatLogFactory;
use GrupoAwamotos\TawkIntegration\Model\ResourceModel\ChatLog as ChatLogResource;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Psr\Log\LoggerInterface;

class Receive implements HttpPostActionInterface, CsrfAwareActionInterface
{
    private const ALLOWED_EVENTS = ['chat:start', 'chat:end', 'ticket:create'];

    private RequestInterface $request;
    private JsonFactory $jsonFactory;
    private Config $config;
    private AttendantService $attendantService;
    private ChatLogFactory $chatLogFactory;
    private ChatLogResource $chatLogResource;
    private CustomerRepositoryInterface $customerRepository;
    private SearchCriteriaBuilder $searchCriteriaBuilder;
    private LoggerInterface $logger;

    public function __construct(
        RequestInterface $request,
        JsonFactory $jsonFactory,
        Config $config,
        AttendantService $attendantService,
        ChatLogFactory $chatLogFactory,
        ChatLogResource $chatLogResource,
        CustomerRepositoryInterface $customerRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        LoggerInterface $logger
    ) {
        $this->request = $request;
        $this->jsonFactory = $jsonFactory;
        $this->config = $config;
        $this->attendantService = $attendantService;
        $this->chatLogFactory = $chatLogFactory;
        $this->chatLogResource = $chatLogResource;
        $this->customerRepository = $customerRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->logger = $logger;
    }

    /**
     * @inheritDoc
     */
    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    /**
     * @inheritDoc
     */
    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }

    /**
     * @return \Magento\Framework\Controller\Result\Json
     */
    public function execute()
    {
        $result = $this->jsonFactory->create();

        if (!$this->config->isWebhookEnabled()) {
            return $result->setHttpResponseCode(404)->setData(['error' => 'Not found']);
        }

        $body = (string) $this->request->getContent();
        $signature = (string) $this->request->getHeader('X-Tawk-Signature');

        if (!$this->verifySignature($body, $signature)) {
            $this->logger->warning('[TawkIntegration] Webhook signature mismatch');
            return $result->setHttpResponseCode(401)->setData(['error' => 'Invalid signature']);
        }

        $payload = json_decode($body, true);
        if (!is_array($payload)) {
            return $result->setHttpResponseCode(400)->setData(['error' => 'Invalid payload']);
        }

        $event = $payload['event'] ?? '';
        if (!in_array($event, self::ALLOWED_EVENTS, true)) {
            return $result->setHttpResponseCode(400)->setData(['error' => 'Unknown event']);
        }

        try {
            switch ($event) {
                case 'chat:start':
                    $this->processChatStart($payload);
                    break;
                case 'chat:end':
                    $this->processChatEnd($payload);
                    break;
                case 'ticket:create':
                    $this->processTicketCreate($payload);
                    break;
            }
            return $result->setData(['status' => 'ok']);
        } catch (\Exception $e) {
            $this->logger->error('[TawkIntegration] Webhook error: ' . $e->getMessage());
            return $result->setHttpResponseCode(500)->setData(['error' => 'Internal error']);
        }
    }

    private function verifySignature(string $body, string $signature): bool
    {
        $secret = $this->config->getWebhookSecret();
        if ($secret === '') {
            return false;
        }
        $expected = hash_hmac('sha1', $body, $secret);
        return hash_equals($expected, $signature);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function processChatStart(array $payload): void
    {
        $chatId = $payload['chatId'] ?? $payload['id'] ?? '';
        $visitor = $payload['visitor'] ?? [];
        $email = $visitor['email'] ?? null;

        $chatLog = $this->chatLogFactory->create();
        $chatLog->setChatId((string) $chatId);
        $chatLog->setEvent('chat:start');
        $chatLog->setVisitorName($visitor['name'] ?? null);
        $chatLog->setVisitorEmail($email);
        $chatLog->setVisitorCity($visitor['city'] ?? null);
        $chatLog->setVisitorCountry($visitor['country'] ?? null);
        $chatLog->setStartedAt(date('Y-m-d H:i:s'));
        $chatLog->setPayload(json_encode($payload));

        if ($email !== null && $email !== '') {
            $customerId = $this->lookupCustomerByEmail($email);
            $chatLog->setCustomerId($customerId);
        }

        $this->chatLogResource->save($chatLog);

        if ($customerId !== null) {
            $customerData = [
                'name'  => $visitor['name'] ?? '',
                'email' => $email ?? '',
                'cnpj'  => '',
            ];
            $this->attendantService->sendChatNotification($customerId, $customerData, (string) $chatId);
        }

        $this->logger->info('[TawkIntegration] Chat started: ' . $chatId);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function processChatEnd(array $payload): void
    {
        $chatId = $payload['chatId'] ?? $payload['id'] ?? '';
        $visitor = $payload['visitor'] ?? [];
        $email = $visitor['email'] ?? null;

        $chatLog = $this->chatLogFactory->create();
        $chatLog->setChatId((string) $chatId);
        $chatLog->setEvent('chat:end');
        $chatLog->setVisitorName($visitor['name'] ?? null);
        $chatLog->setVisitorEmail($email);
        $chatLog->setVisitorCity($visitor['city'] ?? null);
        $chatLog->setVisitorCountry($visitor['country'] ?? null);
        $chatLog->setEndedAt(date('Y-m-d H:i:s'));
        $chatLog->setPayload(json_encode($payload));

        if ($email !== null && $email !== '') {
            $customerId = $this->lookupCustomerByEmail($email);
            $chatLog->setCustomerId($customerId);
        }

        $this->chatLogResource->save($chatLog);
        $this->logger->info('[TawkIntegration] Chat ended: ' . $chatId);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function processTicketCreate(array $payload): void
    {
        $chatId = $payload['ticketId'] ?? $payload['id'] ?? '';
        $visitor = $payload['visitor'] ?? $payload['requester'] ?? [];
        $email = $visitor['email'] ?? null;

        $chatLog = $this->chatLogFactory->create();
        $chatLog->setChatId((string) $chatId);
        $chatLog->setEvent('ticket:create');
        $chatLog->setVisitorName($visitor['name'] ?? null);
        $chatLog->setVisitorEmail($email);
        $chatLog->setPayload(json_encode($payload));

        if ($email !== null && $email !== '') {
            $customerId = $this->lookupCustomerByEmail($email);
            $chatLog->setCustomerId($customerId);
        }

        $this->chatLogResource->save($chatLog);
        $this->logger->info('[TawkIntegration] Ticket created: ' . $chatId);
    }

    private function lookupCustomerByEmail(string $email): ?int
    {
        try {
            $criteria = $this->searchCriteriaBuilder
                ->addFilter('email', $email)
                ->setPageSize(1)
                ->create();
            $result = $this->customerRepository->getList($criteria);
            $items = $result->getItems();
            if (count($items) > 0) {
                $customer = reset($items);
                return (int) $customer->getId();
            }
        } catch (\Exception $e) {
            $this->logger->error('[TawkIntegration] Customer lookup error: ' . $e->getMessage());
        }
        return null;
    }
}
