<?php
/**
 * Submit Quote Request Controller
 */
declare(strict_types=1);

namespace GrupoAwamotos\B2B\Controller\Quote;

use GrupoAwamotos\B2B\Api\QuoteRequestRepositoryInterface;
use GrupoAwamotos\B2B\Helper\Config;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use Magento\Framework\Event\ManagerInterface as EventManagerInterface;
use Magento\Framework\Message\ManagerInterface;
use Psr\Log\LoggerInterface;

class Submit implements HttpPostActionInterface
{
    /**
     * @var RequestInterface
     */
    private $request;

    /**
     * @var JsonFactory
     */
    private $jsonFactory;

    /**
     * @var RedirectFactory
     */
    private $redirectFactory;

    /**
     * @var CustomerSession
     */
    private $customerSession;

    /**
     * @var CheckoutSession
     */
    private $checkoutSession;

    /**
     * @var QuoteRequestRepositoryInterface
     */
    private $quoteRequestRepository;

    /**
     * @var Config
     */
    private $config;

    /**
     * @var FormKeyValidator
     */
    private $formKeyValidator;

    /**
     * @var ManagerInterface
     */
    private $messageManager;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var CustomerRepositoryInterface
     */
    private $customerRepository;

    /**
     * @var EventManagerInterface
     */
    private $eventManager;

    public function __construct(
        RequestInterface $request,
        JsonFactory $jsonFactory,
        RedirectFactory $redirectFactory,
        CustomerSession $customerSession,
        CheckoutSession $checkoutSession,
        QuoteRequestRepositoryInterface $quoteRequestRepository,
        Config $config,
        FormKeyValidator $formKeyValidator,
        ManagerInterface $messageManager,
        LoggerInterface $logger,
        CustomerRepositoryInterface $customerRepository,
        EventManagerInterface $eventManager
    ) {
        $this->request = $request;
        $this->jsonFactory = $jsonFactory;
        $this->redirectFactory = $redirectFactory;
        $this->customerSession = $customerSession;
        $this->checkoutSession = $checkoutSession;
        $this->quoteRequestRepository = $quoteRequestRepository;
        $this->config = $config;
        $this->formKeyValidator = $formKeyValidator;
        $this->messageManager = $messageManager;
        $this->logger = $logger;
        $this->customerRepository = $customerRepository;
        $this->eventManager = $eventManager;
    }

    /**
     * Execute action
     *
     * @return \Magento\Framework\Controller\Result\Json|\Magento\Framework\Controller\Result\Redirect
     */
    public function execute()
    {
        $isAjax = $this->request->isAjax();

        try {
            // Validar form key
            if (!$this->formKeyValidator->validate($this->request)) {
                throw new \Exception('Formulário inválido. Por favor, tente novamente.');
            }

            // Verificar se o módulo está habilitado
            if (!$this->config->isQuoteEnabled()) {
                throw new \Exception('O sistema de cotação não está disponível no momento.');
            }

            // Verificar se visitantes podem solicitar cotação
            if (!$this->customerSession->isLoggedIn() && !$this->config->allowGuestsQuote()) {
                throw new \Exception('Faça login para solicitar uma cotação.');
            }

            // Obter dados do formulário
            $postData = $this->request->getPostValue();

            $customerData = [
                'email' => $postData['email'] ?? '',
                'name' => $postData['name'] ?? '',
                'company_name' => $postData['company_name'] ?? null,
                'cnpj' => $postData['cnpj'] ?? null,
                'phone' => $postData['phone'] ?? null,
            ];

            // Preencher dados do cliente logado (via repository para EAV attributes)
            if ($this->customerSession->isLoggedIn()) {
                $customer = $this->customerRepository->getById($this->customerSession->getCustomerId());
                $customerData['email'] = $customer->getEmail();
                $customerData['name'] = $customer->getFirstname() . ' ' . $customer->getLastname();

                $razaoAttr = $customer->getCustomAttribute('b2b_razao_social');
                $cnpjAttr = $customer->getCustomAttribute('b2b_cnpj');
                $phoneAttr = $customer->getCustomAttribute('b2b_phone');

                if ($razaoAttr && $razaoAttr->getValue()) {
                    $customerData['company_name'] = $razaoAttr->getValue();
                }
                if ($cnpjAttr && $cnpjAttr->getValue()) {
                    $customerData['cnpj'] = $cnpjAttr->getValue();
                }
                if ($phoneAttr && $phoneAttr->getValue()) {
                    $customerData['phone'] = $phoneAttr->getValue();
                }
            }

            // Validar dados obrigatórios
            if (empty($customerData['email']) || empty($customerData['name'])) {
                throw new \Exception('Por favor, preencha todos os campos obrigatórios.');
            }

            // Validar email
            if (!filter_var($customerData['email'], FILTER_VALIDATE_EMAIL)) {
                throw new \Exception('Por favor, informe um e-mail válido.');
            }

            // Criar solicitação de cotação
            $customerId = $this->customerSession->isLoggedIn() ? (int) $this->customerSession->getCustomerId() : null;
            $message = $postData['message'] ?? null;

            $quoteRequest = $this->quoteRequestRepository->createFromCart($customerId, $customerData, $message);

            $quote = $this->checkoutSession->getQuote();
            $this->eventManager->dispatch('grupoawamotos_b2b_quote_requested', [
                'quote_request' => $quoteRequest,
                'customer_id' => $customerId,
                'customer_data' => $customerData,
                'quote_items' => $quoteRequest->getItems(),
                'quote_estimated_value' => $quote ? (float) $quote->getGrandTotal() : 0.0,
                'quote_message' => $message,
                'store_id' => $quote ? (int) $quote->getStoreId() : null,
            ]);

            // Limpar carrinho após solicitação (opcional)
            // $this->checkoutSession->clearQuote();

            $successMessage = __(
                'Sua solicitação de cotação #%1 foi enviada com sucesso! Entraremos em contato em breve.',
                $quoteRequest->getRequestId()
            );

            if ($isAjax) {
                $json = $this->jsonFactory->create();
                return $json->setData([
                    'success' => true,
                    'message' => $successMessage,
                    'request_id' => $quoteRequest->getRequestId(),
                ]);
            }

            $this->messageManager->addSuccessMessage($successMessage);
            $redirect = $this->redirectFactory->create();
            return $redirect->setPath('b2b/quote/history');

        } catch (\Exception $e) {
            $this->logger->error('B2B Quote Submit error: ' . $e->getMessage());

            if ($isAjax) {
                $json = $this->jsonFactory->create();
                return $json->setData([
                    'success' => false,
                    'message' => $e->getMessage(),
                ]);
            }

            $this->messageManager->addErrorMessage($e->getMessage());
            $redirect = $this->redirectFactory->create();
            return $redirect->setPath('b2b/quote');
        }
    }
}
