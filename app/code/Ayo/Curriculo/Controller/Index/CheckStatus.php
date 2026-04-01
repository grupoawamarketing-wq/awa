<?php

declare(strict_types=1);

namespace Ayo\Curriculo\Controller\Index;

use Ayo\Curriculo\Model\ResourceModel\Submission\CollectionFactory;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use Magento\Framework\HTTP\PhpEnvironment\RemoteAddress;

class CheckStatus extends Action implements HttpPostActionInterface
{
    private const RATE_LIMIT_ATTEMPTS = 20;
    private const RATE_LIMIT_WINDOW_SECONDS = 600;
    private const RATE_LIMIT_BLOCK_SECONDS = 900;
    private const CACHE_KEY_PREFIX = 'ayo_curriculo_status_';

    /**
     * @var JsonFactory
     */
    private $jsonFactory;

    /**
     * @var CollectionFactory
     */
    private $collectionFactory;

    /**
     * @var FormKeyValidator
     */
    private $formKeyValidator;

    /**
     * @var RemoteAddress
     */
    private $remoteAddress;

    /**
     * @var CacheInterface
     */
    private $cache;

    public function __construct(
        Context $context,
        JsonFactory $jsonFactory,
        CollectionFactory $collectionFactory,
        FormKeyValidator $formKeyValidator,
        RemoteAddress $remoteAddress,
        CacheInterface $cache
    ) {
        parent::__construct($context);
        $this->jsonFactory = $jsonFactory;
        $this->collectionFactory = $collectionFactory;
        $this->formKeyValidator = $formKeyValidator;
        $this->remoteAddress = $remoteAddress;
        $this->cache = $cache;
    }

    public function execute()
    {
        $result = $this->jsonFactory->create();
        $request = $this->getRequest();

        if (!$this->formKeyValidator->validate($request)) {
            return $result->setData([
                'success' => false,
                'message' => __('Formulário inválido. Atualize a página e tente novamente.')
            ]);
        }

        $trackingCode = strtoupper(trim((string)$request->getParam('tracking_code')));
        $trackingEmail = strtolower(trim((string)$request->getParam('tracking_email')));

        if ($trackingCode === '') {
            return $result->setData([
                'success' => false,
                'message' => __('Por favor, informe o código de acompanhamento.')
            ]);
        }

        if (!$this->isValidTrackingCode($trackingCode)) {
            $this->registerAttempt();
            return $result->setData([
                'success' => false,
                'message' => __('Código inválido. Verifique e tente novamente.')
            ]);
        }

        if ($this->isRateLimited()) {
            return $result->setData([
                'success' => false,
                'message' => __('Muitas tentativas. Aguarde alguns minutos e tente novamente.')
            ]);
        }

        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter('tracking_code', $trackingCode);
        $submission = $collection->getFirstItem();

        if (!$submission->getId()) {
            $this->registerAttempt();
            return $result->setData([
                'success' => false,
                'message' => __('Candidatura não encontrada. Verifique o código informado.')
            ]);
        }

        $this->clearAttempts();

        $statusLabels = [
            'pending' => __('Pendente - Aguardando análise'),
            'reviewing' => __('Em Análise - Seu currículo está sendo avaliado'),
            'interview' => __('Entrevista - Você será contatado para agendar'),
            'approved' => __('Aprovado - Parabéns! Entre em contato conosco'),
            'rejected' => __('Não selecionado para esta vaga'),
        ];

        $status = $submission->getData('status') ?: 'pending';

        $displayHint = '';
        if ($trackingEmail !== '') {
            $storedEmail = strtolower(trim((string)$submission->getData('email')));
            if ($storedEmail !== '' && hash_equals($storedEmail, $trackingEmail)) {
                $displayHint = (string)$submission->getData('name');
                $displayHint = trim(strip_tags($displayHint));
            }
        }

        return $result->setData([
            'success' => true,
            'data' => [
                'tracking_code' => $submission->getData('tracking_code'),
                'display_hint' => $displayHint,
                'position' => $submission->getData('position') ?: '-',
                'status' => $status,
                'status_label' => $statusLabels[$status] ?? $status,
                'submitted_at' => date('d/m/Y H:i', strtotime($submission->getData('created_at'))),
                'updated_at' => date('d/m/Y H:i', strtotime($submission->getData('updated_at'))),
            ]
        ]);
    }

    private function isValidTrackingCode(string $trackingCode): bool
    {
        // Formato gerado: AWA + yymmdd + 6 hex (A-F/0-9)
        return (bool)preg_match('/^AWA\d{6}[0-9A-F]{6}$/', $trackingCode);
    }

    private function getClientIp(): string
    {
        $ip = (string)$this->remoteAddress->getRemoteAddress();
        $ip = trim($ip);
        return $ip !== '' ? $ip : 'unknown';
    }

    private function getCacheKeyAttempts(): string
    {
        return self::CACHE_KEY_PREFIX . 'attempts_' . sha1($this->getClientIp());
    }

    private function getCacheKeyBlock(): string
    {
        return self::CACHE_KEY_PREFIX . 'block_' . sha1($this->getClientIp());
    }

    private function isRateLimited(): bool
    {
        $block = $this->cache->load($this->getCacheKeyBlock());
        return $block !== false && $block !== '';
    }

    private function registerAttempt(): void
    {
        if ($this->isRateLimited()) {
            return;
        }

        $key = $this->getCacheKeyAttempts();
        $raw = $this->cache->load($key);
        $now = time();

        $payload = [
            'ts' => $now,
            'count' => 1,
        ];

        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded) && isset($decoded['ts'], $decoded['count'])) {
                $ts = (int)$decoded['ts'];
                $count = (int)$decoded['count'];

                if (($now - $ts) <= self::RATE_LIMIT_WINDOW_SECONDS) {
                    $payload['ts'] = $ts;
                    $payload['count'] = $count + 1;
                }
            }
        }

        if ($payload['count'] >= self::RATE_LIMIT_ATTEMPTS) {
            $this->cache->save('1', $this->getCacheKeyBlock(), [], self::RATE_LIMIT_BLOCK_SECONDS);
            $this->cache->save('', $key, [], 1);
            return;
        }

        $this->cache->save(json_encode($payload), $key, [], self::RATE_LIMIT_WINDOW_SECONDS);
    }

    private function clearAttempts(): void
    {
        $this->cache->save('', $this->getCacheKeyAttempts(), [], 1);
        $this->cache->save('', $this->getCacheKeyBlock(), [], 1);
    }
}
