<?php

/**
 * GrupoAwamotos_MaintenanceMode
 * Secret code validation controller
 */

declare(strict_types=1);

namespace GrupoAwamotos\MaintenanceMode\Controller\Access;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Stdlib\CookieManagerInterface;
use Magento\Framework\Stdlib\Cookie\CookieMetadataFactory;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use Magento\Store\Model\ScopeInterface;
use Psr\Log\LoggerInterface;

class Validate implements HttpPostActionInterface
{
    private const BYPASS_COOKIE_NAME = 'awa_maintenance_access';
    private const COOKIE_DURATION = 259200; // 72 hours (default)

    /**
     * @var RequestInterface
     */
    private RequestInterface $request;

    /**
     * @var JsonFactory
     */
    private JsonFactory $resultJsonFactory;

    /**
     * @var ScopeConfigInterface
     */
    private ScopeConfigInterface $scopeConfig;

    /**
     * @var CookieManagerInterface
     */
    private CookieManagerInterface $cookieManager;

    /**
     * @var CookieMetadataFactory
     */
    private CookieMetadataFactory $cookieMetadataFactory;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @var FormKeyValidator
     */
    private FormKeyValidator $formKeyValidator;

    /**
     * @param RequestInterface $request
     * @param JsonFactory $resultJsonFactory
     * @param ScopeConfigInterface $scopeConfig
     * @param CookieManagerInterface $cookieManager
     * @param CookieMetadataFactory $cookieMetadataFactory
     * @param LoggerInterface $logger
     */
    public function __construct(
        RequestInterface $request,
        JsonFactory $resultJsonFactory,
        ScopeConfigInterface $scopeConfig,
        CookieManagerInterface $cookieManager,
        CookieMetadataFactory $cookieMetadataFactory,
        LoggerInterface $logger,
        FormKeyValidator $formKeyValidator
    ) {
        $this->request = $request;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->scopeConfig = $scopeConfig;
        $this->cookieManager = $cookieManager;
        $this->cookieMetadataFactory = $cookieMetadataFactory;
        $this->logger = $logger;
        $this->formKeyValidator = $formKeyValidator;
    }

    /**
     * Validate secret code and set bypass cookie
     *
     * @return \Magento\Framework\Controller\Result\Json
     */
    public function execute()
    {
        $resultJson = $this->resultJsonFactory->create();

        if (!$this->formKeyValidator->validate($this->request)) {
            return $resultJson->setData([
                'success' => false,
                'message' => __('Formulário inválido. Tente novamente.')
            ]);
        }

        $submittedCode = trim((string)$this->request->getParam('code'));

        if (empty($submittedCode)) {
            return $resultJson->setData([
                'success' => false,
                'message' => __('Por favor, informe o código de acesso.')
            ]);
        }

        // Get secret code from config
        $secretCode = $this->scopeConfig->getValue(
            'grupoawamotos_maintenance/general/secret_key',
            ScopeInterface::SCOPE_STORE
        );

        if (empty($secretCode)) {
            return $resultJson->setData([
                'success' => false,
                'message' => __('Acesso por código não está configurado.')
            ]);
        }

        // Validate the code
        if ($submittedCode === $secretCode) {
            try {
                // Get cookie duration from config (in hours)
                $cookieDuration = (int) $this->scopeConfig->getValue(
                    'grupoawamotos_maintenance/general/cookie_duration',
                    ScopeInterface::SCOPE_STORE
                ) ?: 72;

                // Set bypass cookie (same format as Observer)
                $cookieMetadata = $this->cookieMetadataFactory
                    ->createPublicCookieMetadata()
                    ->setDuration($cookieDuration * 3600)
                    ->setPath('/')
                    ->setHttpOnly(true);

                $this->cookieManager->setPublicCookie(
                    self::BYPASS_COOKIE_NAME,
                    hash('sha256', $secretCode . '_awamotos_access'),
                    $cookieMetadata
                );

                $this->logger->info('MaintenanceMode: Bypass access granted via secret code');

                return $resultJson->setData([
                    'success' => true,
                    'message' => __('Acesso concedido! Redirecionando...'),
                    'redirect' => '/'
                ]);
            } catch (\Exception $e) {
                $this->logger->error('MaintenanceMode Cookie Error: ' . $e->getMessage());
                return $resultJson->setData([
                    'success' => false,
                    'message' => __('Erro ao processar o acesso. Tente novamente.')
                ]);
            }
        } else {
            $this->logger->warning('MaintenanceMode: Invalid access code attempt');
            return $resultJson->setData([
                'success' => false,
                'message' => __('Código de acesso inválido.')
            ]);
        }
    }
}
