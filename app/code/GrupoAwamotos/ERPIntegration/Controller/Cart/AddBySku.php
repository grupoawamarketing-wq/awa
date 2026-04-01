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
 * Add product to cart by SKU
 *
 * Used by the Suggested Cart feature
 */
class AddBySku implements HttpPostActionInterface
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
     */
    public function execute()
    {
        $result = $this->jsonFactory->create();

        // Validate form key
        if (!$this->formKeyValidator->validate($this->request)) {
            return $result->setData([
                'success' => false,
                'message' => 'Invalid form key'
            ]);
        }

        $sku = $this->request->getParam('sku');
        $qty = (int) $this->request->getParam('qty', 1);

        if (empty($sku)) {
            return $result->setData([
                'success' => false,
                'message' => 'SKU is required'
            ]);
        }

        if ($qty < 1) {
            $qty = 1;
        }

        try {
            // Get product by SKU
            $product = $this->productRepository->get($sku);

            // Check if product is available
            if (!$product->isAvailable()) {
                return $result->setData([
                    'success' => false,
                    'message' => 'Product is not available'
                ]);
            }

            // Add to cart
            $this->cart->addProduct($product, ['qty' => $qty]);
            $this->cart->save();

            $this->logger->info(sprintf(
                '[ERP Cart] Added product %s (qty: %d) to cart',
                $sku,
                $qty
            ));

            return $result->setData([
                'success' => true,
                'message' => 'Product added to cart',
                'product' => [
                    'sku' => $product->getSku(),
                    'name' => $product->getName(),
                    'qty' => $qty
                ]
            ]);
        } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
            return $result->setData([
                'success' => false,
                'message' => 'Product not found'
            ]);
        } catch (\Exception $e) {
            $this->logger->error('[ERP Cart] Error adding to cart: ' . $e->getMessage());

            return $result->setData([
                'success' => false,
                'message' => 'Error adding product to cart: ' . $e->getMessage()
            ]);
        }
    }
}
