<?php

declare(strict_types=1);

namespace GrupoAwamotos\ERPIntegration\Controller\Cart;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Checkout\Model\Cart;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use Psr\Log\LoggerInterface;

/**
 * Add multiple products to cart at once
 *
 * Used by the Suggested Cart feature for bulk add
 */
class AddMultiple implements HttpPostActionInterface
{
    private RequestInterface $request;
    private JsonFactory $jsonFactory;
    private Cart $cart;
    private ProductRepositoryInterface $productRepository;
    private FormKeyValidator $formKeyValidator;
    private LoggerInterface $logger;

    public function __construct(
        RequestInterface $request,
        JsonFactory $jsonFactory,
        Cart $cart,
        ProductRepositoryInterface $productRepository,
        FormKeyValidator $formKeyValidator,
        LoggerInterface $logger
    ) {
        $this->request = $request;
        $this->jsonFactory = $jsonFactory;
        $this->cart = $cart;
        $this->productRepository = $productRepository;
        $this->formKeyValidator = $formKeyValidator;
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
     *   ]
     * }
     */
    public function execute()
    {
        $result = $this->jsonFactory->create();

        // Validate form key
        if (!$this->formKeyValidator->validate($this->request)) {
            return $result->setData([
                'success' => false,
                'message' => __('Invalid form key')
            ]);
        }

        $itemsJson = $this->request->getParam('items');

        if (empty($itemsJson)) {
            return $result->setData([
                'success' => false,
                'message' => __('No items provided')
            ]);
        }

        // Parse items (can be JSON string or array)
        $items = is_string($itemsJson) ? json_decode($itemsJson, true) : $itemsJson;

        if (!is_array($items) || empty($items)) {
            return $result->setData([
                'success' => false,
                'message' => __('Invalid items format')
            ]);
        }

        $addedProducts = [];
        $failedProducts = [];
        $totalQty = 0;

        try {
            foreach ($items as $item) {
                $sku = $item['sku'] ?? '';
                $qty = (int) ($item['qty'] ?? 1);

                if (empty($sku)) {
                    continue;
                }

                if ($qty < 1) {
                    $qty = 1;
                }

                try {
                    // Get product by SKU
                    $product = $this->productRepository->get($sku);

                    // Check if product is available
                    if (!$product->isAvailable()) {
                        $failedProducts[] = [
                            'sku' => $sku,
                            'reason' => __('Product is not available')
                        ];
                        continue;
                    }

                    // Add to cart
                    $this->cart->addProduct($product, ['qty' => $qty]);

                    $addedProducts[] = [
                        'sku' => $product->getSku(),
                        'name' => $product->getName(),
                        'qty' => $qty,
                        'price' => (float) $product->getFinalPrice()
                    ];
                    $totalQty += $qty;
                } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
                    $failedProducts[] = [
                        'sku' => $sku,
                        'reason' => __('Product not found')
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
                    '[ERP Cart] Bulk add: %d products (%d items) added to cart',
                    count($addedProducts),
                    $totalQty
                ));
            }

            // Build response
            $success = !empty($addedProducts);
            $message = $this->buildResultMessage($addedProducts, $failedProducts);

            return $result->setData([
                'success' => $success,
                'message' => $message,
                'added' => $addedProducts,
                'failed' => $failedProducts,
                'summary' => [
                    'products_added' => count($addedProducts),
                    'products_failed' => count($failedProducts),
                    'total_qty' => $totalQty,
                    'cart_subtotal' => $this->cart->getQuote()->getSubtotal()
                ]
            ]);
        } catch (\Exception $e) {
            $this->logger->error('[ERP Cart] Error in bulk add: ' . $e->getMessage());

            return $result->setData([
                'success' => false,
                'message' => __('Error adding products to cart: %1', $e->getMessage()),
                'added' => $addedProducts,
                'failed' => $failedProducts
            ]);
        }
    }

    /**
     * Build result message
     */
    private function buildResultMessage(array $added, array $failed): string
    {
        $addedCount = count($added);
        $failedCount = count($failed);

        if ($addedCount > 0 && $failedCount === 0) {
            return __('%1 product(s) added to cart successfully.', $addedCount)->render();
        }

        if ($addedCount > 0 && $failedCount > 0) {
            return __('%1 product(s) added. %2 product(s) could not be added.', $addedCount, $failedCount)->render();
        }

        if ($addedCount === 0 && $failedCount > 0) {
            return __('No products could be added to cart.')->render();
        }

        return __('No products provided.')->render();
    }
}
