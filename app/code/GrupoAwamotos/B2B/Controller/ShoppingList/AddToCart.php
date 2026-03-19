<?php
/**
 * Add Shopping List to Cart Controller
 */
declare(strict_types=1);

namespace GrupoAwamotos\B2B\Controller\ShoppingList;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use Magento\Framework\Message\ManagerInterface;
use GrupoAwamotos\B2B\Model\ShoppingListService;

class AddToCart implements HttpPostActionInterface
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
     * @var RequestInterface
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
        RequestInterface $request,
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

        $listId = (int)$this->request->getParam('id');

        try {
            $result = $this->shoppingListService->addToCart($listId);

            if ($result['added'] > 0) {
                $message = __('%1 produto(s) adicionado(s) ao carrinho.', $result['added']);
                $this->messageManager->addSuccessMessage($message);
            }

            if (!empty($result['failed'])) {
                foreach ($result['failed'] as $failed) {
                    $this->messageManager->addWarningMessage(
                        __('Não foi possível adicionar SKU %1: %2', $failed['sku'], $failed['error'])
                    );
                }
            }

            if ($this->request->isAjax()) {
                $jsonResult = $this->jsonFactory->create();
                return $jsonResult->setData([
                    'success' => true,
                    'added' => $result['added'],
                    'failed' => $result['failed']
                ]);
            }

            $redirect = $this->redirectFactory->create();
            return $redirect->setPath('checkout/cart');

        } catch (\Exception $e) {
            if ($this->request->isAjax()) {
                $jsonResult = $this->jsonFactory->create();
                return $jsonResult->setData(['success' => false, 'message' => $e->getMessage()]);
            }

            $this->messageManager->addErrorMessage($e->getMessage());
            $redirect = $this->redirectFactory->create();
            return $redirect->setPath('b2b/shoppinglist/view', ['id' => $listId]);
        }
    }
}
