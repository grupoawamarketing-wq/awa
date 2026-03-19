<?php
/**
 * Reject Quote Request Controller
 *
 * Allows the customer to reject a quoted quote request.
 */
declare(strict_types=1);

namespace GrupoAwamotos\B2B\Controller\Quote;

use GrupoAwamotos\B2B\Api\Data\QuoteRequestInterface;
use GrupoAwamotos\B2B\Api\QuoteRequestRepositoryInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use Magento\Framework\Event\ManagerInterface as EventManagerInterface;
use Magento\Framework\Message\ManagerInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

class Reject implements HttpPostActionInterface
{
    private RequestInterface $request;
    private RedirectFactory $redirectFactory;
    private CustomerSession $customerSession;
    private QuoteRequestRepositoryInterface $quoteRequestRepository;
    private FormKeyValidator $formKeyValidator;
    private ManagerInterface $messageManager;
    private LoggerInterface $logger;
    private EventManagerInterface $eventManager;
    private StoreManagerInterface $storeManager;

    public function __construct(
        RequestInterface $request,
        RedirectFactory $redirectFactory,
        CustomerSession $customerSession,
        QuoteRequestRepositoryInterface $quoteRequestRepository,
        FormKeyValidator $formKeyValidator,
        ManagerInterface $messageManager,
        LoggerInterface $logger,
        EventManagerInterface $eventManager,
        StoreManagerInterface $storeManager
    ) {
        $this->request = $request;
        $this->redirectFactory = $redirectFactory;
        $this->customerSession = $customerSession;
        $this->quoteRequestRepository = $quoteRequestRepository;
        $this->formKeyValidator = $formKeyValidator;
        $this->messageManager = $messageManager;
        $this->logger = $logger;
        $this->eventManager = $eventManager;
        $this->storeManager = $storeManager;
    }

    public function execute()
    {
        $redirect = $this->redirectFactory->create();

        try {
            // Validate form key
            if (!$this->formKeyValidator->validate($this->request)) {
                $this->messageManager->addErrorMessage(__('Formulário inválido. Tente novamente.'));
                return $redirect->setPath('b2b/quote/history');
            }

            // Verify customer is logged in
            if (!$this->customerSession->isLoggedIn()) {
                $this->messageManager->addErrorMessage(__('Faça login para gerenciar suas cotações.'));
                return $redirect->setPath('b2b/account/login');
            }

            $requestId = (int) $this->request->getParam('id');
            if (!$requestId) {
                $this->messageManager->addErrorMessage(__('Cotação não encontrada.'));
                return $redirect->setPath('b2b/quote/history');
            }

            // Load quote request
            $quoteRequest = $this->quoteRequestRepository->getById($requestId);
            $previousStatus = $quoteRequest->getStatus();

            // Verify ownership
            $customerId = (int) $this->customerSession->getCustomerId();
            if ((int) $quoteRequest->getCustomerId() !== $customerId) {
                $this->messageManager->addErrorMessage(__('Você não tem permissão para acessar esta cotação.'));
                return $redirect->setPath('b2b/quote/history');
            }

            // Verify status is quoted
            if ($quoteRequest->getStatus() !== QuoteRequestInterface::STATUS_QUOTED) {
                $this->messageManager->addErrorMessage(__('Esta cotação não pode ser recusada no momento.'));
                return $redirect->setPath('b2b/quote/view', ['id' => $requestId]);
            }

            // Update status to rejected
            $quoteRequest->setStatus(QuoteRequestInterface::STATUS_REJECTED);
            $this->quoteRequestRepository->save($quoteRequest);

            $this->eventManager->dispatch('grupoawamotos_b2b_quote_customer_rejected', [
                'quote_request' => $quoteRequest,
                'previous_status' => $previousStatus,
                'lifecycle_event' => 'customer_rejected',
                'store_id' => (int) $this->storeManager->getStore()->getId(),
                'customer_id' => $customerId,
            ]);

            $this->messageManager->addSuccessMessage(
                __('Cotação #%1 foi recusada.', $requestId)
            );

            $this->logger->info(sprintf(
                '[B2B Quote Reject] Cotação #%d recusada pelo cliente #%d.',
                $requestId,
                $customerId
            ));

            return $redirect->setPath('b2b/quote/history');

        } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
            $this->messageManager->addErrorMessage(__('Cotação não encontrada.'));
            return $redirect->setPath('b2b/quote/history');
        } catch (\Exception $e) {
            $this->logger->error('[B2B Quote Reject] Error: ' . $e->getMessage());
            $this->messageManager->addErrorMessage(
                __('Erro ao processar recusa da cotação. Tente novamente.')
            );
            return $redirect->setPath('b2b/quote/history');
        }
    }
}
