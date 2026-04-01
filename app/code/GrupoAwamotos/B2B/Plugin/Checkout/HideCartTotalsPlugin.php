<?php

/**
 * Plugin to hide cart totals on the cart page for non-authorized users.
 *
 * Intercepts Magento\Quote\Model\Cart\CartTotalRepository to zero-out
 * totals returned via the REST/GraphQL API (used by KnockoutJS on cart page).
 */

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Plugin\Checkout;

use GrupoAwamotos\B2B\Api\PriceVisibilityInterface;
use Magento\Quote\Api\CartTotalRepositoryInterface;
use Magento\Quote\Api\Data\TotalsInterface;

class HideCartTotalsPlugin
{
    /**
     * @var PriceVisibilityInterface
     */
    private $priceVisibility;

    public function __construct(
        PriceVisibilityInterface $priceVisibility
    ) {
        $this->priceVisibility = $priceVisibility;
    }

    /**
     * After get - zero-out totals for non-authorized users.
     *
     * @param CartTotalRepositoryInterface $subject
     * @param TotalsInterface $result
     * @return TotalsInterface
     */
    public function afterGet(CartTotalRepositoryInterface $subject, TotalsInterface $result): TotalsInterface
    {
        if ($this->priceVisibility->canViewPrices()) {
            return $result;
        }

        $result->setGrandTotal(0);
        $result->setBaseGrandTotal(0);
        $result->setSubtotal(0);
        $result->setBaseSubtotal(0);
        $result->setDiscountAmount(0);
        $result->setBaseDiscountAmount(0);
        $result->setSubtotalWithDiscount(0);
        $result->setBaseSubtotalWithDiscount(0);
        $result->setShippingAmount(0);
        $result->setBaseShippingAmount(0);
        $result->setTaxAmount(0);
        $result->setBaseTaxAmount(0);

        // Zero-out individual item totals
        $items = $result->getItems();
        if ($items) {
            foreach ($items as $item) {
                $item->setPrice(0);
                $item->setBasePrice(0);
                $item->setRowTotal(0);
                $item->setBaseRowTotal(0);
                $item->setRowTotalInclTax(0);
                $item->setBaseRowTotalInclTax(0);
                $item->setPriceInclTax(0);
                $item->setBasePriceInclTax(0);
                $item->setDiscountAmount(0);
                $item->setBaseDiscountAmount(0);
                $item->setTaxAmount(0);
                $item->setBaseTaxAmount(0);
            }
        }

        // Zero-out total segments
        $segments = $result->getTotalSegments();
        if ($segments) {
            foreach ($segments as $segment) {
                $segment->setValue(0);
            }
        }

        return $result;
    }
}
