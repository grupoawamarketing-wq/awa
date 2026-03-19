<?php
/**
 * Plugin to hide product prices on the cart page for non-authorized users.
 *
 * Intercepts the cart item renderer so that individual item price HTML
 * is replaced with the B2B login message.
 */
declare(strict_types=1);

namespace GrupoAwamotos\B2B\Plugin\Checkout;

use GrupoAwamotos\B2B\Api\PriceVisibilityInterface;
use Magento\Checkout\Block\Cart\Item\Renderer;
use Magento\Framework\Escaper;

class HideCartItemPricePlugin
{
    public function __construct(
        private readonly PriceVisibilityInterface $priceVisibility,
        private readonly Escaper $escaper
    ) {
    }

    /**
     * After getProductPriceHtml - replace with B2B message when prices are hidden.
     *
     * @param Renderer $subject
     * @param string $result
     * @return string
     */
    public function afterGetProductPriceHtml(Renderer $subject, $result): string
    {
        if ($this->priceVisibility->canViewPrices()) {
            return $result;
        }

        return $this->renderPriceGate('full');
    }

    /**
     * After getUnitPriceHtml - replace unit price with B2B message.
     *
     * @param Renderer $subject
     * @param string $result
     * @return string
     */
    public function afterGetUnitPriceHtml(Renderer $subject, $result): string
    {
        if ($this->priceVisibility->canViewPrices()) {
            return $result;
        }

        return $this->renderPriceGate('inline');
    }

    /**
     * After getRowTotalHtml - replace row total with B2B message.
     *
     * @param Renderer $subject
     * @param string $result
     * @return string
     */
    public function afterGetRowTotalHtml(Renderer $subject, $result): string
    {
        if ($this->priceVisibility->canViewPrices()) {
            return $result;
        }

        return $this->renderPriceGate('inline');
    }

    private function renderPriceGate(string $variant): string
    {
        $messageHtml = $this->priceVisibility->getPriceReplacementMessage();

        if ($variant === 'inline') {
            return '<span class="b2b-cart-price-pill"'
                . ' data-b2b-price-surface="cart-inline"'
                . ' title="' . $this->escaper->escapeHtmlAttr(__('Liberado apos aprovacao comercial')) . '">'
                . $this->escaper->escapeHtml(__('Acesso B2B'))
                . '</span>';
        }

        return '<div class="b2b-login-to-see-price b2b-cart-price-hidden awa-b2b-cart-price-gate"'
            . ' data-b2b-price-surface="cart-price">'
            . '<span class="awa-b2b-cart-price-gate__badge">'
            . $this->escaper->escapeHtml(__('Conta empresarial'))
            . '</span>'
            . '<div class="price-label awa-b2b-cart-price-gate__message">'
            . $messageHtml
            . '</div>'
            . '</div>';
    }
}
