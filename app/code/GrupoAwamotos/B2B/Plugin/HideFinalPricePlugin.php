<?php

/**
 * Plugin to hide final price box for non-logged users
 */

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Plugin;

use GrupoAwamotos\B2B\Api\PriceVisibilityInterface;
use Magento\Catalog\Pricing\Render\FinalPriceBox;

class HideFinalPricePlugin
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
     * Around toHtml - replace price with message if not allowed
     *
     * @param FinalPriceBox $subject
     * @param callable $proceed
     * @return string
     */
    public function aroundToHtml(FinalPriceBox $subject, callable $proceed)
    {
        if (!$this->priceVisibility->canViewPrices()) {
            return '<div class="b2b-login-to-see-price price-box">'
                . '<span class="price-label">'
                . $this->priceVisibility->getPriceReplacementMessage()
                . '</span></div>';
        }

        return $proceed();
    }
}
