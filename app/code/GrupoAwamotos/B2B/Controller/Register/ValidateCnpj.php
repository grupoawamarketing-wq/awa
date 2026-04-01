<?php

/**
 * AJAX Controller para validação de CNPJ e consulta ReceitaWS + ERP
 */

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Controller\Register;

use GrupoAwamotos\B2B\Model\CnaeClassifier;
use GrupoAwamotos\B2B\Model\Cnpj\RequestRateLimiter;
use GrupoAwamotos\B2B\Model\ErpIntegration;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\HTTP\PhpEnvironment\RemoteAddress;
use GrupoAwamotos\B2B\Helper\CnpjValidator;
use Magento\Customer\Model\ResourceModel\Customer\CollectionFactory as CustomerCollectionFactory;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use Psr\Log\LoggerInterface;

class ValidateCnpj implements HttpPostActionInterface
{
    private RequestInterface $request;
    private JsonFactory $jsonFactory;
    private CnpjValidator $cnpjValidator;
    private RequestRateLimiter $requestRateLimiter;
    private RemoteAddress $remoteAddress;
    private CustomerCollectionFactory $customerCollectionFactory;
    private ErpIntegration $erpIntegration;
    private CnaeClassifier $cnaeClassifier;
    private FormKeyValidator $formKeyValidator;
    private LoggerInterface $logger;

    public function __construct(
        RequestInterface $request,
        JsonFactory $jsonFactory,
        CnpjValidator $cnpjValidator,
        RequestRateLimiter $requestRateLimiter,
        RemoteAddress $remoteAddress,
        CustomerCollectionFactory $customerCollectionFactory,
        ErpIntegration $erpIntegration,
        CnaeClassifier $cnaeClassifier,
        FormKeyValidator $formKeyValidator,
        LoggerInterface $logger
    ) {
        $this->request = $request;
        $this->jsonFactory = $jsonFactory;
        $this->cnpjValidator = $cnpjValidator;
        $this->requestRateLimiter = $requestRateLimiter;
        $this->remoteAddress = $remoteAddress;
        $this->customerCollectionFactory = $customerCollectionFactory;
        $this->erpIntegration = $erpIntegration;
        $this->cnaeClassifier = $cnaeClassifier;
        $this->formKeyValidator = $formKeyValidator;
        $this->logger = $logger;
    }

    public function execute()
    {
        $result = $this->jsonFactory->create();

        // Validação CSRF — protege contra requisições cross-site
        if (!$this->formKeyValidator->validate($this->request)) {
            $this->logger->warning('[B2B] CSRF attempt on CNPJ validation', [
                'ip' => $this->remoteAddress->getRemoteAddress()
            ]);
            return $result->setData([
                'success' => false,
                'message' => (string) __('Requisição inválida. Recarregue a página e tente novamente.')
            ]);
        }
        $clientIp = (string) (
            $this->remoteAddress->getRemoteAddress()
            ?: $this->request->getServer('REMOTE_ADDR')
            ?: 'unknown'
        );
        $rateLimit = $this->requestRateLimiter->consume($clientIp);

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

        $cnpj = preg_replace('/\D/', '', (string) $this->request->getParam('cnpj', ''));
        $forceRefresh = filter_var(
            $this->request->getParam('force_refresh', false),
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

        $duplicateEmail = $this->findExistingCnpjOwner($cnpj);
        if ($duplicateEmail !== null) {
            return $result->setData([
                'success' => false,
                'cnpj_duplicate' => true,
                'message' => (string) __(
                    'Este CNPJ já está vinculado a uma conta existente (%1). Faça login ou use a opção "Vincular minha conta".',
                    $duplicateEmail
                )
            ]);
        }

        $apiData = $this->cnpjValidator->validateApi($cnpj, $forceRefresh);

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

        // Consulta ERP para verificar email cadastrado
        $erpData = $this->getErpData($cnpj);

        // Classificação CNAE
        $cnaeData = $this->getCnaeData($apiData);

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
            'email' => $apiData['email'] ?? '',
            'erp_found' => $erpData['found'],
            'erp_email' => $erpData['email_masked'],
            'erp_email_full' => $erpData['email_full'],
            'erp_codigo' => $erpData['codigo'],
            'erp_razao' => $erpData['razao'],
            'cnae_code' => $cnaeData['code'],
            'cnae_profile' => $cnaeData['profile'],
            'cnae_profile_label' => $cnaeData['label'],
        ]);
    }

    /**
     * Classify CNAE from API data
     */
    private function getCnaeData(array $apiData): array
    {
        $default = ['code' => '', 'profile' => '', 'label' => ''];

        if (!$this->cnaeClassifier->isEnabled() || !isset($apiData['data'])) {
            return $default;
        }

        $rawData = $apiData['data'];
        $cnaeCode = $this->cnaeClassifier->extractCnaeCode($rawData);

        if (empty($cnaeCode)) {
            return $default;
        }

        $profile = $this->cnaeClassifier->classify($cnaeCode);

        return [
            'code' => $cnaeCode,
            'profile' => $profile,
            'label' => $this->cnaeClassifier->getProfileLabel($profile),
        ];
    }

    /**
     * Query ERP for customer data by CNPJ
     */
    private function getErpData(string $cnpj): array
    {
        $default = [
            'found' => false,
            'email_masked' => null,
            'email_full' => null,
            'codigo' => null,
            'razao' => null,
        ];

        try {
            $erpCustomer = $this->erpIntegration->findErpCustomerByCnpj($cnpj);

            if (!$erpCustomer) {
                return $default;
            }

            $email = trim($erpCustomer['EMAIL'] ?? '');
            return [
                'found' => true,
                'email_masked' => $email !== '' ? $this->maskEmail($email) : null,
                'email_full' => strtolower($email),
                'codigo' => $erpCustomer['CODIGO'] ?? null,
                'razao' => $erpCustomer['RAZAO'] ?? null,
            ];
        } catch (\Exception $e) {
            return $default;
        }
    }

    /**
     * Mask email for display: j***@d***.com
     * Shows only first char of local part and first char of domain for privacy
     */
    private function maskEmail(string $email): string
    {
        $email = strtolower(trim($email));

        if ($email === '' || strpos($email, '@') === false) {
            return '';
        }

        [$local, $domain] = explode('@', $email, 2);

        // Mask local part: show only first char
        $maskedLocal = substr($local, 0, 1) . str_repeat('*', max(4, strlen($local) - 1));

        // Mask domain: show first char + *** + TLD
        $domainParts = explode('.', $domain);
        if (count($domainParts) >= 2) {
            $tld = array_pop($domainParts);
            $domainName = implode('.', $domainParts);
            $maskedDomain = substr($domainName, 0, 1) . '***.' . $tld;
        } else {
            $maskedDomain = substr($domain, 0, 1) . '***';
        }

        return $maskedLocal . '@' . $maskedDomain;
    }

    /**
     * Check if a CNPJ is already registered to another customer.
     * Returns masked email of the existing owner, or null if no duplicate.
     */
    private function findExistingCnpjOwner(string $cnpjDigits): ?string
    {
        $formattedCnpj = $this->cnpjValidator->format($cnpjDigits);

        $collection = $this->customerCollectionFactory->create();
        $collection->addAttributeToSelect(['email', 'b2b_cnpj']);
        $collection->addAttributeToFilter(
            [
                ['attribute' => 'b2b_cnpj', 'eq' => $formattedCnpj],
                ['attribute' => 'b2b_cnpj', 'eq' => $cnpjDigits]
            ]
        );
        $collection->setPageSize(1);

        $existing = $collection->getFirstItem();
        if (!$existing || !$existing->getId()) {
            return null;
        }

        $email = trim((string) $existing->getData('email'));
        if ($email === '' || strpos($email, '@') === false) {
            return (string) __('outro cliente');
        }

        [$local, $domain] = explode('@', $email, 2);
        if (strlen($local) <= 1) {
            return $local . '***@' . $domain;
        }

        return substr($local, 0, 1) . str_repeat('*', max(2, strlen($local) - 1)) . '@' . $domain;
    }
}
