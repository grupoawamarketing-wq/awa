<?php

/**
 * Quick Order Add to Cart Controller
 * Processes multiple SKUs and adds to cart
 */

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Controller\Quickorder;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Checkout\Model\Cart;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Psr\Log\LoggerInterface;

class Add implements HttpPostActionInterface
{
    private const MAX_ITEMS = 120;
    private const MAX_QTY_PER_SKU = 9999;

    private RequestInterface $request;
    private JsonFactory $resultJsonFactory;
    private FormKeyValidator $formKeyValidator;
    private ProductRepositoryInterface $productRepository;
    private Cart $cart;
    private LoggerInterface $logger;
    private CustomerSession $customerSession;

    public function __construct(
        RequestInterface $request,
        JsonFactory $resultJsonFactory,
        FormKeyValidator $formKeyValidator,
        ProductRepositoryInterface $productRepository,
        Cart $cart,
        LoggerInterface $logger,
        CustomerSession $customerSession
    ) {
        $this->request = $request;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->formKeyValidator = $formKeyValidator;
        $this->productRepository = $productRepository;
        $this->cart = $cart;
        $this->logger = $logger;
        $this->customerSession = $customerSession;
    }

    public function execute(): Json
    {
        $result = $this->resultJsonFactory->create();

        if (!$this->formKeyValidator->validate($this->request)) {
            return $result->setData([
                'success' => false,
                'message' => __('Formulário inválido. Tente novamente.'),
                'added' => [],
                'errors' => [],
            ]);
        }

        if (!$this->customerSession->isLoggedIn()) {
            return $result->setData([
                'success' => false,
                'message' => __('Faça login para usar o Pedido Rápido.'),
                'added' => [],
                'errors' => [],
            ]);
        }

        $rawItems = $this->request->getParam('items', []);
        $items = $this->normalizeItems(is_array($rawItems) ? $rawItems : []);

        if ($items === []) {
            return $result->setData([
                'success' => false,
                'message' => __('Informe pelo menos um SKU válido.'),
                'added' => [],
                'errors' => [],
            ]);
        }

        $added = [];
        $errors = [];

        foreach ($items as $item) {
            $sku = $item['sku'];
            $qty = $item['qty'];

            try {
                $product = $this->productRepository->get($sku);

                if (!$product->isSaleable()) {
                    $errors[] = [
                        'sku' => $sku,
                        'message' => __('Produto indisponível'),
                    ];
                    continue;
                }

                $this->cart->addProduct($product, ['qty' => $qty]);
                $added[] = [
                    'sku' => $sku,
                    'name' => (string) $product->getName(),
                    'qty' => $qty,
                ];
            } catch (NoSuchEntityException $e) {
                $errors[] = [
                    'sku' => $sku,
                    'message' => __('SKU não encontrado'),
                ];
            } catch (LocalizedException $e) {
                $errors[] = [
                    'sku' => $sku,
                    'message' => $e->getMessage(),
                ];
            } catch (\Exception $e) {
                $this->logger->error('[B2B QuickOrder] Erro ao adicionar SKU', [
                    'sku' => $sku,
                    'exception' => $e,
                ]);

                $errors[] = [
                    'sku' => $sku,
                    'message' => __('Erro ao adicionar produto'),
                ];
            }
        }

        if ($added !== []) {
            $this->cart->save();
        }

        $addedCount = count($added);
        $errorCount = count($errors);

        return $result->setData([
            'success' => $addedCount > 0,
            'added' => $added,
            'errors' => $errors,
            'processed' => count($items),
            'cartUrl' => $this->cart->getQuote()->getStore()->getUrl('checkout/cart'),
            'message' => $addedCount > 0
                ? __('%1 SKU(s) adicionado(s) ao carrinho.', $addedCount)
                : __('Nenhum SKU foi adicionado ao carrinho.'),
            'summary' => [
                'added' => $addedCount,
                'failed' => $errorCount,
            ],
        ]);
    }

    /**
     * @param array<int, mixed> $rawItems
     * @return array<int, array{sku:string,qty:int}>
     */
    private function normalizeItems(array $rawItems): array
    {
        $deduped = [];

        foreach ($rawItems as $item) {
            if (!is_array($item)) {
                continue;
            }

            $sku = trim((string) ($item['sku'] ?? ''));
            if ($sku === '') {
                continue;
            }

            $qty = (int) ($item['qty'] ?? 1);
            $qty = max(1, min(self::MAX_QTY_PER_SKU, $qty));

            $key = strtolower($sku);
            if (!isset($deduped[$key])) {
                if (count($deduped) >= self::MAX_ITEMS) {
                    break;
                }

                $deduped[$key] = [
                    'sku' => $sku,
                    'qty' => 0,
                ];
            }

            $deduped[$key]['qty'] += $qty;
            if ($deduped[$key]['qty'] > self::MAX_QTY_PER_SKU) {
                $deduped[$key]['qty'] = self::MAX_QTY_PER_SKU;
            }
        }

        return array_values($deduped);
    }
}
