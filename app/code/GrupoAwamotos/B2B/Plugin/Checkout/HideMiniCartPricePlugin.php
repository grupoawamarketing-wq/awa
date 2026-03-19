<?php
/**
 * Plugin to hide prices in mini-cart sidebar for non-authorized users.
 *
 * The mini-cart fetches data via customer-data sections (AJAX).
 * This plugin intercepts the section data to strip/replace price values.
 */
declare(strict_types=1);

namespace GrupoAwamotos\B2B\Plugin\Checkout;

use GrupoAwamotos\B2B\Api\PriceVisibilityInterface;
use Magento\Checkout\CustomerData\Cart;

class HideMiniCartPricePlugin
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
     * After getSectionData - strip prices if user cannot view them.
     *
     * @param Cart $subject
     * @param array $result
     * @return array
     */
    public function afterGetSectionData(Cart $subject, array $result): array
    {
        if ($this->priceVisibility->canViewPrices()) {
            return $result;
        }

        $message = $this->priceVisibility->getPriceReplacementMessage();
        $messageText = strip_tags($message);

        // Hide global subtotal
        if (isset($result['subtotalAmount'])) {
            $result['subtotalAmount'] = 0;
        }
        if (isset($result['subtotal'])) {
            $result['subtotal'] = '<span class="b2b-price-hidden">' . $messageText . '</span>';
        }

        // Hide per-item prices
        if (isset($result['items']) && is_array($result['items'])) {
            foreach ($result['items'] as &$item) {
                if (isset($item['product_price'])) {
                    $item['product_price'] = '<span class="b2b-price-hidden">' . $messageText . '</span>';
                }
                if (isset($item['product_price_value'])) {
                    $item['product_price_value'] = 0;
                }
                if (isset($item['product_has_url'])) {
                    // Keep navigation ability
                }
            }
            unset($item);
        }

        // Add flag so front-end JS/CSS can react
        $result['b2b_prices_hidden'] = true;

        return $result;
    }
}
