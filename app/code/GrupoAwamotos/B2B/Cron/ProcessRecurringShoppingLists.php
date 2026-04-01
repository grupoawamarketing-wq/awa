<?php

/**
 * Cron Job: Process Recurring Shopping Lists
 *
 * Creates shopping carts for customers with due recurring lists.
 * The customer then proceeds to checkout on their next login.
 */

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Cron;

use GrupoAwamotos\B2B\Helper\Config;
use GrupoAwamotos\B2B\Model\ShoppingListService;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\DataObject;
use Psr\Log\LoggerInterface;

class ProcessRecurringShoppingLists
{
    private ShoppingListService $shoppingListService;
    private Config $config;
    private CartManagementInterface $cartManagement;
    private CartRepositoryInterface $cartRepository;
    private ProductRepositoryInterface $productRepository;
    private StoreManagerInterface $storeManager;
    private LoggerInterface $logger;

    public function __construct(
        ShoppingListService $shoppingListService,
        Config $config,
        CartManagementInterface $cartManagement,
        CartRepositoryInterface $cartRepository,
        ProductRepositoryInterface $productRepository,
        StoreManagerInterface $storeManager,
        LoggerInterface $logger
    ) {
        $this->shoppingListService = $shoppingListService;
        $this->config = $config;
        $this->cartManagement = $cartManagement;
        $this->cartRepository = $cartRepository;
        $this->productRepository = $productRepository;
        $this->storeManager = $storeManager;
        $this->logger = $logger;
    }

    public function execute(): void
    {
        if (!$this->config->isEnabled()) {
            return;
        }

        $this->logger->info('[B2B Recurring] Iniciando processamento de listas recorrentes...');

        try {
            $dueLists = $this->shoppingListService->getRecurringListsDue();
        } catch (\Exception $e) {
            $this->logger->error('[B2B Recurring] Erro ao buscar listas: ' . $e->getMessage());
            return;
        }

        $processed = 0;
        $errors = 0;

        foreach ($dueLists as $list) {
            try {
                $this->processRecurringList($list);
                $processed++;
            } catch (\Exception $e) {
                $errors++;
                $this->logger->error(sprintf(
                    '[B2B Recurring] Erro na lista #%d: %s',
                    $list->getId(),
                    $e->getMessage()
                ));
            }
        }

        $this->logger->info(sprintf(
            '[B2B Recurring] Concluido: %d processadas, %d erros.',
            $processed,
            $errors
        ));
    }

    private function processRecurringList($list): void
    {
        $customerId = (int) $list->getCustomerId();
        $listId = (int) $list->getId();

        // Create a new cart for the customer
        $cartId = $this->cartManagement->createEmptyCartForCustomer($customerId);
        $quote = $this->cartRepository->get($cartId);

        $store = $this->storeManager->getDefaultStoreView();
        if ($store) {
            $quote->setStoreId((int) $store->getId());
        }

        // Load list items and add to cart
        $items = $list->getItemsCollection();
        $added = 0;

        foreach ($items as $item) {
            try {
                $product = $this->productRepository->getById((int) $item->getProductId());

                if (!$product->isSalable()) {
                    $this->logger->warning(sprintf(
                        '[B2B Recurring] Produto SKU %s indisponivel, lista #%d',
                        $item->getSku(),
                        $listId
                    ));
                    continue;
                }

                $quote->addProduct($product, new DataObject([
                    'qty' => (float) $item->getQty()
                ]));
                $added++;
            } catch (\Exception $e) {
                $this->logger->warning(sprintf(
                    '[B2B Recurring] Nao foi possivel adicionar SKU %s: %s',
                    $item->getSku(),
                    $e->getMessage()
                ));
            }
        }

        if ($added > 0) {
            $quote->collectTotals();
            $this->cartRepository->save($quote);
        }

        // Update next_order_date
        $intervalDays = (int) $list->getData('recurring_interval');
        if ($intervalDays < 1) {
            $intervalDays = 30;
        }
        $nextDate = date('Y-m-d', strtotime("+{$intervalDays} days"));
        $list->setData('next_order_date', $nextDate);
        $list->save();

        $this->logger->info(sprintf(
            '[B2B Recurring] Lista #%d processada: %d itens adicionados ao carrinho do cliente #%d. Proxima: %s',
            $listId,
            $added,
            $customerId,
            $nextDate
        ));
    }
}
