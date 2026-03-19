<?php
/**
 * Create Shopping List Controller
 */
declare(strict_types=1);

namespace GrupoAwamotos\B2B\Controller\ShoppingList;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use Magento\Framework\Message\ManagerInterface;
use GrupoAwamotos\B2B\Model\ShoppingListService;

class Create implements HttpPostActionInterface
{
    /**
     * @var CustomerSession
     */
    private $customerSession;

    /**
     * @var RedirectFactory
     */
    private $redirectFactory;

    /**
     * @var JsonFactory
     */
    private $jsonFactory;

    /**
    * @var Http
     */
    private $request;

    /**
     * @var FormKeyValidator
     */
    private $formKeyValidator;

    /**
     * @var ManagerInterface
     */
    private $messageManager;

    /**
     * @var ShoppingListService
     */
    private $shoppingListService;

    public function __construct(
        CustomerSession $customerSession,
        RedirectFactory $redirectFactory,
        JsonFactory $jsonFactory,
        Http $request,
        FormKeyValidator $formKeyValidator,
        ManagerInterface $messageManager,
        ShoppingListService $shoppingListService
    ) {
        $this->customerSession = $customerSession;
        $this->redirectFactory = $redirectFactory;
        $this->jsonFactory = $jsonFactory;
        $this->request = $request;
        $this->formKeyValidator = $formKeyValidator;
        $this->messageManager = $messageManager;
        $this->shoppingListService = $shoppingListService;
    }

    /**
     * @inheritDoc
     */
    public function execute()
    {
        if (!$this->formKeyValidator->validate($this->request)) {
            if ($this->request->isAjax()) {
                $result = $this->jsonFactory->create();
                return $result->setData(['success' => false, 'message' => __('Formulário inválido. Tente novamente.')]);
            }
            $this->messageManager->addErrorMessage(__('Formulário inválido. Tente novamente.'));
            $redirect = $this->redirectFactory->create();
            return $redirect->setPath('b2b/shoppinglist');
        }

        if (!$this->customerSession->isLoggedIn()) {
            if ($this->request->isAjax()) {
                $result = $this->jsonFactory->create();
                return $result->setData(['success' => false, 'message' => __('Por favor, faça login.')]);
            }
            $redirect = $this->redirectFactory->create();
            return $redirect->setPath('b2b/account/login');
        }

        $name = trim($this->request->getParam('name', ''));
        $description = trim($this->request->getParam('description', ''));
        $fromCart = (bool)$this->request->getParam('from_cart', false);

        try {
            if ($fromCart) {
                $list = $this->shoppingListService->createFromCart($name, $description);
                $message = __('Lista "%1" criada a partir do carrinho.', $name);
            } else {
                $list = $this->shoppingListService->createList($name, $description);
                $message = __('Lista "%1" criada com sucesso.', $name);
            }

            if ($this->request->isAjax()) {
                $result = $this->jsonFactory->create();
                return $result->setData([
                    'success' => true,
                    'message' => $message,
                    'list_id' => $list->getId(),
                    'list_name' => $list->getName()
                ]);
            }

            $this->messageManager->addSuccessMessage($message);
            $redirect = $this->redirectFactory->create();
            return $redirect->setPath('b2b/shoppinglist/view', ['id' => $list->getId()]);

        } catch (\Exception $e) {
            if ($this->request->isAjax()) {
                $result = $this->jsonFactory->create();
                return $result->setData(['success' => false, 'message' => $e->getMessage()]);
            }

            $this->messageManager->addErrorMessage($e->getMessage());
            $redirect = $this->redirectFactory->create();
            return $redirect->setPath('b2b/shoppinglist');
        }
    }
}
