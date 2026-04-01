<?php

/**
 * Shopping List ViewModel
 */

declare(strict_types=1);

namespace GrupoAwamotos\B2B\ViewModel;

use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Pricing\Helper\Data as PricingHelper;
use GrupoAwamotos\B2B\Model\ShoppingListService;
use GrupoAwamotos\B2B\Model\ResourceModel\ShoppingList\Collection as ListCollection;
use GrupoAwamotos\B2B\Model\ResourceModel\ShoppingListItem\Collection as ItemCollection;
use Magento\Catalog\Helper\Image as ImageHelper;

class ShoppingListViewModel implements ArgumentInterface
{
    /**
     * @var ShoppingListService
     */
    private $shoppingListService;

    /**
     * @var CustomerSession
     */
    private $customerSession;

    /**
     * @var PricingHelper
     */
    private $pricingHelper;

    /**
     * @var ImageHelper
     */
    private $imageHelper;

    /**
     * @param ShoppingListService $shoppingListService
     * @param CustomerSession $customerSession
     * @param PricingHelper $pricingHelper
     * @param ImageHelper $imageHelper
     */
    public function __construct(
        ShoppingListService $shoppingListService,
        CustomerSession $customerSession,
        PricingHelper $pricingHelper,
        ImageHelper $imageHelper
    ) {
        $this->shoppingListService = $shoppingListService;
        $this->customerSession = $customerSession;
        $this->pricingHelper = $pricingHelper;
        $this->imageHelper = $imageHelper;
    }

    /**
     * Get customer shopping lists
     *
     * @return ListCollection
     */
    public function getCustomerLists(): ListCollection
    {
        return $this->shoppingListService->getCustomerLists();
    }

    /**
     * Get list items
     *
     * @param int $listId
     * @return ItemCollection
     */
    public function getListItems(int $listId): ItemCollection
    {
        try {
            return $this->shoppingListService->getListItems($listId);
        } catch (\Exception $e) {
            return new ItemCollection();
        }
    }

    /**
     * Get list total
     *
     * @param int $listId
     * @return string
     */
    public function getListTotal(int $listId): string
    {
        try {
            $total = $this->shoppingListService->getListTotal($listId);
            return $this->pricingHelper->currency($total, true, false);
        } catch (\Exception $e) {
            return $this->pricingHelper->currency(0, true, false);
        }
    }

    /**
     * Format price
     *
     * @param float $price
     * @return string
     */
    public function formatPrice(float $price): string
    {
        return $this->pricingHelper->currency($price, true, false);
    }

    /**
     * Get product image URL
     *
     * @param \Magento\Catalog\Model\Product $product
     * @return string
     */
    public function getProductImageUrl($product): string
    {
        return $this->imageHelper->init($product, 'product_thumbnail_image')
            ->setImageFile($product->getThumbnail())
            ->getUrl();
    }

    /**
     * Check if customer is logged in
     *
     * @return bool
     */
    public function isLoggedIn(): bool
    {
        return $this->customerSession->isLoggedIn();
    }

    /**
     * Get customer ID
     *
     * @return int|null
     */
    public function getCustomerId(): ?int
    {
        return $this->customerSession->getCustomerId() ? (int)$this->customerSession->getCustomerId() : null;
    }

    /**
     * Get items count for list
     *
     * @param int $listId
     * @return int
     */
    public function getItemsCount(int $listId): int
    {
        try {
            return $this->shoppingListService->getListItems($listId)->getSize();
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Get shopping list service
     *
     * @return ShoppingListService
     */
    public function getService(): ShoppingListService
    {
        return $this->shoppingListService;
    }
}
