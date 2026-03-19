<?php
declare(strict_types=1);

namespace GrupoAwamotos\B2B\Plugin\SearchAutocomplete;

use GrupoAwamotos\B2B\Helper\Data as B2BHelper;
use Mirasvit\SearchAutocomplete\Block\Injection;

class RestrictInjectionConfigPlugin
{
    public function __construct(
        private readonly B2BHelper $b2bHelper
    ) {
    }

    /**
     * Aligns the autocomplete payload with the storefront B2B visibility rules.
     *
     * @param array<string, mixed> $result
     * @return array<string, mixed>
     */
    public function afterGetJsConfig(Injection $subject, array $result): array
    {
        if (!$this->b2bHelper->isEnabled()) {
            return $result;
        }

        $canViewPrices = $this->b2bHelper->canViewPrices();
        $canAddToCart = $this->b2bHelper->canAddToCart();

        $result['isShowPrice'] = !empty($result['isShowPrice']) && $canViewPrices;
        $result['isShowCartButton'] = !empty($result['isShowCartButton']) && $canAddToCart;
        $result['isB2BRestricted'] = !$canViewPrices || !$canAddToCart;

        return $result;
    }
}
