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

class HideCartItemPricePlugin
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

        return '<div class="b2b-login-to-see-price b2b-cart-price-hidden">'
            . $this->priceVisibility->getPriceReplacementMessage()
            . '</div>';
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

        return '<span class="b2b-login-to-see-price b2b-cart-price-hidden">-</span>';
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

        return '<span class="b2b-login-to-see-price b2b-cart-price-hidden">-</span>';
    }
}
