<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Controller\Adminhtml\Cnpj;

use GrupoAwamotos\B2B\Helper\CnpjValidator;
use GrupoAwamotos\B2B\Model\Cnpj\RequestRateLimiter;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\HTTP\PhpEnvironment\RemoteAddress;
use Psr\Log\LoggerInterface;

class Lookup extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'GrupoAwamotos_B2B::customer_approval';

    private JsonFactory $jsonFactory;
    private CnpjValidator $cnpjValidator;
    private RequestRateLimiter $requestRateLimiter;
    private RemoteAddress $remoteAddress;
    private LoggerInterface $logger;

    public function __construct(
        Context $context,
        JsonFactory $jsonFactory,
        CnpjValidator $cnpjValidator,
        RequestRateLimiter $requestRateLimiter,
        RemoteAddress $remoteAddress,
        LoggerInterface $logger
    ) {
        parent::__construct($context);
        $this->jsonFactory = $jsonFactory;
        $this->cnpjValidator = $cnpjValidator;
        $this->requestRateLimiter = $requestRateLimiter;
        $this->remoteAddress = $remoteAddress;
        $this->logger = $logger;
    }

    public function execute(): Json
    {
        $result = $this->jsonFactory->create();
        $clientIp = (string) (
            $this->remoteAddress->getRemoteAddress()
            ?: $this->getRequest()->getServer('REMOTE_ADDR')
            ?: 'unknown'
        );
        $rateLimit = $this->requestRateLimiter->consume('admin:' . $clientIp);

        if (!$rateLimit['allowed']) {
            $retryAfter = (int) ($rateLimit['retry_after'] ?? 60);

            return $result->setData([
                'success' => false,
                'rate_limited' => true,
                'retry_after' => $retryAfter,
                'message' => (string) __(
                    'Muitas consultas de CNPJ em pouco tempo. Aguarde %1 segundos e tente novamente.',
                    $retryAfter
                )
            ]);
        }

        $cnpj = preg_replace('/\D/', '', (string) $this->getRequest()->getParam('cnpj', ''));
        $forceRefresh = filter_var(
            $this->getRequest()->getParam('force_refresh', false),
            FILTER_VALIDATE_BOOLEAN
        );

        if (strlen($cnpj) !== 14) {
            return $result->setData([
                'success' => false,
                'message' => 'CNPJ deve ter 14 dígitos.'
            ]);
        }

        if (!$this->cnpjValidator->validateLocal($cnpj)) {
            return $result->setData([
                'success' => false,
                'message' => 'CNPJ inválido. Verifique os dígitos.'
            ]);
        }

        try {
            $apiData = $this->cnpjValidator->validateApi($cnpj, $forceRefresh);
        } catch (\Throwable $exception) {
            $this->logger->error(
                sprintf(
                    '[B2B][Admin][CNPJ Lookup] Erro para CNPJ %s: %s',
                    $cnpj,
                    $exception->getMessage()
                )
            );

            $apiData = null;
        }

        if ($apiData === null) {
            return $result->setData([
                'success' => false,
                'message' => 'CNPJ não encontrado na Receita Federal.'
            ]);
        }

        if (isset($apiData['valid']) && !$apiData['valid']) {
            return $result->setData([
                'success' => false,
                'message' => (string) ($apiData['message'] ?? 'CNPJ com situação irregular.'),
                'situacao' => $apiData['data']['situacao'] ?? ''
            ]);
        }

        if (isset($apiData['api_error']) && $apiData['api_error']) {
            return $result->setData([
                'success' => true,
                'source' => $apiData['source'] ?? 'fallback',
                'api_unavailable' => true,
                'message' => 'API indisponível. CNPJ validado localmente.'
            ]);
        }

        return $result->setData([
            'success' => true,
            'source' => $apiData['source'] ?? 'api',
            'razao_social' => $apiData['razao_social'] ?? '',
            'nome_fantasia' => $apiData['nome_fantasia'] ?? '',
            'situacao' => $apiData['situacao'] ?? '',
            'tipo' => $apiData['tipo'] ?? '',
            'porte' => $apiData['porte'] ?? '',
            'atividade_principal' => $apiData['atividade_principal'] ?? '',
            'logradouro' => $apiData['logradouro'] ?? '',
            'numero' => $apiData['numero'] ?? '',
            'complemento' => $apiData['complemento'] ?? '',
            'bairro' => $apiData['bairro'] ?? '',
            'municipio' => $apiData['municipio'] ?? '',
            'uf' => $apiData['uf'] ?? '',
            'cep' => $apiData['cep'] ?? '',
            'telefone' => $apiData['telefone'] ?? '',
            'email' => $apiData['email'] ?? ''
        ]);
    }

    protected function _isAllowed(): bool
    {
        return $this->_authorization->isAllowed('GrupoAwamotos_B2B::customer_approval')
            || $this->_authorization->isAllowed('Magento_Sales::create')
            || $this->_authorization->isAllowed('Magento_Customer::manage');
    }
}
