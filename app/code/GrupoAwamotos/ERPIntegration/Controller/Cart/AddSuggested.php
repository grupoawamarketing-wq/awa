<?php
declare(strict_types=1);

namespace GrupoAwamotos\ERPIntegration\Controller\Cart;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Checkout\Model\Cart;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use Magento\Customer\Model\Session as CustomerSession;
use GrupoAwamotos\ERPIntegration\Model\Cart\SuggestedCart;
use GrupoAwamotos\ERPIntegration\Model\PurchaseHistory;
use Psr\Log\LoggerInterface;

/**
 * Add Suggested Cart items to cart
 *
 * Endpoint: POST /erpintegration/cart/addSuggested
 *
 * This controller handles adding products from the ERP-generated
 * suggested cart to the customer's Magento shopping cart.
 */
class AddSuggested implements HttpPostActionInterface
{
    private RequestInterface $request;
    private JsonFactory $jsonFactory;
    private Cart $cart;
    private ProductRepositoryInterface $productRepository;
    private FormKeyValidator $formKeyValidator;
    private CustomerSession $customerSession;
    private SuggestedCart $suggestedCart;
    private PurchaseHistory $purchaseHistory;
    private LoggerInterface $logger;

    public function __construct(
        RequestInterface $request,
        JsonFactory $jsonFactory,
        Cart $cart,
        ProductRepositoryInterface $productRepository,
        FormKeyValidator $formKeyValidator,
        CustomerSession $customerSession,
        SuggestedCart $suggestedCart,
        PurchaseHistory $purchaseHistory,
        LoggerInterface $logger
    ) {
        $this->request = $request;
        $this->jsonFactory = $jsonFactory;
        $this->cart = $cart;
        $this->productRepository = $productRepository;
        $this->formKeyValidator = $formKeyValidator;
        $this->customerSession = $customerSession;
        $this->suggestedCart = $suggestedCart;
        $this->purchaseHistory = $purchaseHistory;
        $this->logger = $logger;
    }

    /**
     * Execute action
     *
     * Expected POST data:
     * {
     *   "items": [
     *     {"sku": "ABC123", "qty": 2},
     *     {"sku": "DEF456", "qty": 1}
     *   ],
     *   "add_all": false  // Optional: if true, adds all suggested cart items
     * }
     */
    public function execute()
    {
        $result = $this->jsonFactory->create();

        // Validate form key
        if (!$this->formKeyValidator->validate($this->request)) {
            return $result->setData([
                'success' => false,
                'message' => __('Invalid form key. Please refresh the page and try again.')
            ]);
        }

        // Check if customer is logged in
        if (!$this->customerSession->isLoggedIn()) {
            return $result->setData([
                'success' => false,
                'message' => __('Please log in to use this feature.')
            ]);
        }

        try {
            $items = $this->getItemsToAdd();

            if (empty($items)) {
                return $result->setData([
                    'success' => false,
                    'message' => __('No items to add to cart.')
                ]);
            }

            $addedProducts = [];
            $failedProducts = [];
            $totalQty = 0;
            $totalValue = 0;

            foreach ($items as $item) {
                $sku = $item['sku'] ?? '';
                $qty = max(1, (int)($item['qty'] ?? 1));

                if (empty($sku)) {
                    continue;
                }

                try {
                    $product = $this->productRepository->get($sku);

                    if (!$product->isAvailable()) {
                        $failedProducts[] = [
                            'sku' => $sku,
                            'name' => $product->getName(),
                            'reason' => __('Product is out of stock')
                        ];
                        continue;
                    }

                    $this->cart->addProduct($product, ['qty' => $qty]);

                    $price = (float)$product->getFinalPrice();
                    $addedProducts[] = [
                        'sku' => $product->getSku(),
                        'name' => $product->getName(),
                        'qty' => $qty,
                        'price' => $price,
                        'line_total' => $price * $qty
                    ];
                    $totalQty += $qty;
                    $totalValue += $price * $qty;

                } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
                    $failedProducts[] = [
                        'sku' => $sku,
                        'reason' => __('Product not found in catalog')
                    ];
                } catch (\Exception $e) {
                    $failedProducts[] = [
                        'sku' => $sku,
                        'reason' => $e->getMessage()
                    ];
                }
            }

            // Save cart if products were added
            if (!empty($addedProducts)) {
                $this->cart->save();

                $this->logger->info(sprintf(
                    '[ERP Suggested Cart] Customer %s added %d products (%d items) worth R$ %.2f',
                    $this->customerSession->getCustomer()->getEmail(),
                    count($addedProducts),
                    $totalQty,
                    $totalValue
                ));
            }

            $success = !empty($addedProducts);

            return $result->setData([
                'success' => $success,
                'message' => $this->buildResultMessage($addedProducts, $failedProducts),
                'redirect' => $success ? $this->getCartUrl() : null,
                'added' => $addedProducts,
                'failed' => $failedProducts,
                'summary' => [
                    'products_added' => count($addedProducts),
                    'products_failed' => count($failedProducts),
                    'total_qty' => $totalQty,
                    'total_value' => round($totalValue, 2),
                    'cart_subtotal' => (float)$this->cart->getQuote()->getSubtotal(),
                    'cart_items_count' => $this->cart->getQuote()->getItemsCount()
                ]
            ]);

        } catch (\Exception $e) {
            $this->logger->error('[ERP Suggested Cart] Error: ' . $e->getMessage());

            return $result->setData([
                'success' => false,
                'message' => __('An error occurred while adding products to cart. Please try again.')
            ]);
        }
    }

    /**
     * Get items to add from request or suggested cart
     */
    private function getItemsToAdd(): array
    {
        // Check if adding all suggested items
        $addAll = (bool)$this->request->getParam('add_all', false);

        if ($addAll) {
            return $this->getAllSuggestedItems();
        }

        // Get specific items from request
        $itemsParam = $this->request->getParam('items');

        if (empty($itemsParam)) {
            return [];
        }

        // Parse items (can be JSON string or array)
        if (is_string($itemsParam)) {
            $items = json_decode($itemsParam, true);
        } else {
            $items = $itemsParam;
        }

        return is_array($items) ? $items : [];
    }

    /**
     * Get all items from the suggested cart
     */
    private function getAllSuggestedItems(): array
    {
        $customer = $this->customerSession->getCustomer();
        $cnpj = $customer->getData('b2b_cnpj') ?: $customer->getTaxvat();

        if (empty($cnpj)) {
            return [];
        }

        $customerCode = $this->purchaseHistory->getCustomerCodeByCnpj($cnpj);

        if (!$customerCode) {
            return [];
        }

        $suggestedCartData = $this->suggestedCart->buildSuggestedCart($customerCode);

        if (isset($suggestedCartData['error']) || empty($suggestedCartData['sections'])) {
            return [];
        }

        $items = [];
        foreach ($suggestedCartData['sections'] as $section) {
            foreach ($section['items'] ?? [] as $item) {
                $items[] = [
                    'sku' => $item['sku'],
                    'qty' => $item['suggested_quantity'] ?? 1
                ];
            }
        }

        return $items;
    }

    /**
     * Build result message
     */
    private function buildResultMessage(array $added, array $failed): string
    {
        $addedCount = count($added);
        $failedCount = count($failed);

        if ($addedCount > 0 && $failedCount === 0) {
            if ($addedCount === 1) {
                return __('Product added to cart successfully!')->render();
            }
            return __('%1 products added to cart successfully!', $addedCount)->render();
        }

        if ($addedCount > 0 && $failedCount > 0) {
            return __('%1 product(s) added. %2 product(s) could not be added.', $addedCount, $failedCount)->render();
        }

        if ($failedCount > 0) {
            return __('Products could not be added to cart. Please try again.')->render();
        }

        return __('No products to add.')->render();
    }

    /**
     * Get cart page URL
     */
    private function getCartUrl(): string
    {
        return '/checkout/cart';
    }
}
