<?php

/**
 * Plugin to hide prices for non-logged users
 */

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Plugin;

use GrupoAwamotos\B2B\Api\PriceVisibilityInterface;
use Magento\Catalog\Block\Product\AbstractProduct;

class HidePricePlugin
{
    private PriceVisibilityInterface $priceVisibility;

    public function __construct(
        PriceVisibilityInterface $priceVisibility
    ) {
        $this->priceVisibility = $priceVisibility;
    }

    /**
     * After getProductPrice - return message instead of price if not allowed
     */
    public function afterGetProductPrice(AbstractProduct $subject, string $result): string
    {
        if (!$this->priceVisibility->canViewPrices()) {
            return '<div class="b2b-login-to-see-price">'
                . $this->priceVisibility->getPriceReplacementMessage()
                . '</div>';
        }

        return $result;
    }
}
