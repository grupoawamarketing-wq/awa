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
use Magento\Framework\Escaper;

class HideMiniCartPricePlugin
{
    public function __construct(
        private readonly PriceVisibilityInterface $priceVisibility,
        private readonly Escaper $escaper
    ) {
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
            $result['subtotal'] = $this->renderMiniPriceGate($messageText, true);
        }

        // Hide per-item prices
        if (isset($result['items']) && is_array($result['items'])) {
            foreach ($result['items'] as &$item) {
                if (isset($item['product_price'])) {
                    $item['product_price'] = $this->renderMiniPriceGate($messageText, false);
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

    private function renderMiniPriceGate(string $messageText, bool $isSubtotal): string
    {
        $surface = $isSubtotal ? 'minicart-subtotal' : 'minicart-item';
        $label = $isSubtotal ? __('Subtotal restrito') : __('Preco sob aprovacao');

        return '<span class="b2b-price-hidden awa-b2b-mini-price-gate'
            . ($isSubtotal ? ' awa-b2b-mini-price-gate--subtotal' : '')
            . '" data-b2b-price-surface="' . $this->escaper->escapeHtmlAttr($surface) . '"'
            . ' title="' . $this->escaper->escapeHtmlAttr($messageText) . '">'
            . '<span class="awa-b2b-mini-price-gate__label">'
            . $this->escaper->escapeHtml($label)
            . '</span>'
            . '<span class="awa-b2b-mini-price-gate__text">'
            . $this->escaper->escapeHtml($messageText)
            . '</span>'
            . '</span>';
    }
}
