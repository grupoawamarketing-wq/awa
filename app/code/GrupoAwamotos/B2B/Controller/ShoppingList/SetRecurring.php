<?php
/**
 * Set Shopping List Recurring Controller
 */
declare(strict_types=1);

namespace GrupoAwamotos\B2B\Controller\ShoppingList;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use Magento\Framework\Message\ManagerInterface;
use GrupoAwamotos\B2B\Model\ShoppingListService;
use Psr\Log\LoggerInterface;

class SetRecurring implements HttpPostActionInterface
{
    private CustomerSession $customerSession;
    private RedirectFactory $redirectFactory;
    private RequestInterface $request;
    private FormKeyValidator $formKeyValidator;
    private ManagerInterface $messageManager;
    private ShoppingListService $shoppingListService;
    private LoggerInterface $logger;

    public function __construct(
        CustomerSession $customerSession,
        RedirectFactory $redirectFactory,
        RequestInterface $request,
        FormKeyValidator $formKeyValidator,
        ManagerInterface $messageManager,
        ShoppingListService $shoppingListService,
        LoggerInterface $logger
    ) {
        $this->customerSession = $customerSession;
        $this->redirectFactory = $redirectFactory;
        $this->request = $request;
        $this->formKeyValidator = $formKeyValidator;
        $this->messageManager = $messageManager;
        $this->shoppingListService = $shoppingListService;
        $this->logger = $logger;
    }

    public function execute()
    {
        $redirect = $this->redirectFactory->create();

        if (!$this->formKeyValidator->validate($this->request)) {
            $this->messageManager->addErrorMessage(__('Formulário inválido. Tente novamente.'));
            return $redirect->setPath('b2b/shoppinglist');
        }

        if (!$this->customerSession->isLoggedIn()) {
            return $redirect->setPath('b2b/account/login');
        }

        $listId = (int) $this->request->getParam('id');
        if (!$listId) {
            $this->messageManager->addErrorMessage(__('Lista não encontrada.'));
            return $redirect->setPath('b2b/shoppinglist');
        }

        try {
            $isRecurring = (int) $this->request->getParam('is_recurring', 0);

            if ($isRecurring) {
                $intervalDays = (int) $this->request->getParam('recurring_interval', 30);
                $intervalDays = max(7, min(365, $intervalDays));

                $this->shoppingListService->setRecurring($listId, $intervalDays);
                $this->messageManager->addSuccessMessage(
                    __('Lista configurada para pedido recorrente a cada %1 dias.', $intervalDays)
                );

                $this->logger->info(sprintf(
                    '[B2B Recurring] Lista #%d configurada: intervalo %d dias, cliente #%d',
                    $listId,
                    $intervalDays,
                    $this->customerSession->getCustomerId()
                ));
            } else {
                $this->shoppingListService->disableRecurring($listId);
                $this->messageManager->addSuccessMessage(__('Pedido recorrente desativado.'));

                $this->logger->info(sprintf(
                    '[B2B Recurring] Lista #%d desativada pelo cliente #%d',
                    $listId,
                    $this->customerSession->getCustomerId()
                ));
            }

            return $redirect->setPath('b2b/shoppinglist/view', ['id' => $listId]);

        } catch (\Exception $e) {
            $this->logger->error('[B2B SetRecurring] Error: ' . $e->getMessage());
            $this->messageManager->addErrorMessage(
                __('Erro ao configurar pedido recorrente. Tente novamente.')
            );
            return $redirect->setPath('b2b/shoppinglist/view', ['id' => $listId]);
        }
    }
}
