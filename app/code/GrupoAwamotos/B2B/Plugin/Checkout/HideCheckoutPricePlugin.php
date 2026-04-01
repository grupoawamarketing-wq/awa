<?php

/**
 * Plugin to hide prices in the checkout configuration data.
 *
 * Magento's DefaultConfigProvider feeds pricing data (totals, item prices)
 * to the checkout KnockoutJS application. This plugin strips those values
 * for non-authorized users so prices never reach the browser.
 */

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Plugin\Checkout;

use GrupoAwamotos\B2B\Api\PriceVisibilityInterface;
use Magento\Checkout\Model\DefaultConfigProvider;

class HideCheckoutPricePlugin
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
     * After getConfig - strip prices from checkout window.checkoutConfig.
     *
     * @param DefaultConfigProvider $subject
     * @param array $result
     * @return array
     */
    public function afterGetConfig(DefaultConfigProvider $subject, array $result): array
    {
        if ($this->priceVisibility->canViewPrices()) {
            return $result;
        }

        // Zero-out totals data
        if (isset($result['totalsData'])) {
            $result['totalsData'] = $this->hideTotalsData($result['totalsData']);
        }

        // Zero-out quote item data
        if (isset($result['quoteItemData']) && is_array($result['quoteItemData'])) {
            foreach ($result['quoteItemData'] as &$item) {
                $item = $this->hideItemPrices($item);
            }
            unset($item);
        }

        // Add B2B flag so front-end can detect
        $result['b2bPricesHidden'] = true;
        $result['b2bPriceMessage'] = strip_tags($this->priceVisibility->getPriceReplacementMessage());

        return $result;
    }

    /**
     * Replace numeric price fields in totals data with zeroes.
     *
     * @param array $totalsData
     * @return array
     */
    private function hideTotalsData(array $totalsData): array
    {
        $priceFields = [
            'grand_total',
            'base_grand_total',
            'subtotal',
            'base_subtotal',
            'discount_amount',
            'base_discount_amount',
            'subtotal_with_discount',
            'base_subtotal_with_discount',
            'shipping_amount',
            'base_shipping_amount',
            'shipping_discount_amount',
            'base_shipping_discount_amount',
            'tax_amount',
            'base_tax_amount',
            'shipping_tax_amount',
            'base_shipping_tax_amount',
            'subtotal_incl_tax',
            'shipping_incl_tax',
        ];

        foreach ($priceFields as $field) {
            if (array_key_exists($field, $totalsData)) {
                $totalsData[$field] = 0;
            }
        }

        // Zero-out totals segments (subtotal, shipping, tax, grand_total lines)
        if (isset($totalsData['total_segments']) && is_array($totalsData['total_segments'])) {
            foreach ($totalsData['total_segments'] as &$segment) {
                if (isset($segment['value'])) {
                    $segment['value'] = 0;
                }
            }
            unset($segment);
        }

        // Zero-out items inside totalsData
        if (isset($totalsData['items']) && is_array($totalsData['items'])) {
            foreach ($totalsData['items'] as &$item) {
                $item = $this->hideItemPrices($item);
            }
            unset($item);
        }

        return $totalsData;
    }

    /**
     * Replace numeric price fields in a single item array with zeroes.
     *
     * @param array $item
     * @return array
     */
    private function hideItemPrices(array $item): array
    {
        $itemPriceFields = [
            'price',
            'base_price',
            'row_total',
            'base_row_total',
            'row_total_incl_tax',
            'base_row_total_incl_tax',
            'price_incl_tax',
            'base_price_incl_tax',
            'discount_amount',
            'base_discount_amount',
            'tax_amount',
            'base_tax_amount',
            'tax_percent',
        ];

        foreach ($itemPriceFields as $field) {
            if (array_key_exists($field, $item)) {
                $item[$field] = 0;
            }
        }

        return $item;
    }
}
