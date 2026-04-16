<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Plugin\Catalog\Block\Product;

use GrupoAwamotos\B2B\Helper\Config;
use Magento\Catalog\Block\Product\AbstractProduct;
use Magento\Customer\Model\Context as CustomerContext;
use Magento\Framework\App\Http\Context as HttpContext;

class AbstractProductCacheKeyPlugin
{
    private const CONTEXT_APPROVAL_STATUS = 'b2b_approval_status';
    private const CONTEXT_PRICE_LIST = 'erp_price_list';

    public function __construct(
        private readonly Config $config,
        private readonly HttpContext $httpContext
    ) {
    }

    /**
     * Make cached product-list/carousel blocks vary by B2B visibility context.
     *
     * This prevents guest-rendered price placeholders from being reused for
     * approved B2B customers on cacheable homepage/category widgets.
     *
     * @param string[] $result
     * @return string[]
     */
    public function afterGetCacheKeyInfo(AbstractProduct $subject, array $result): array
    {
        if (!$this->config->isEnabled()) {
            return $result;
        }

        $result[] = 'b2b_auth=' . ((int) (bool) $this->httpContext->getValue(CustomerContext::CONTEXT_AUTH));
        $result[] = 'b2b_status=' . (string) ($this->httpContext->getValue(self::CONTEXT_APPROVAL_STATUS) ?? 'guest');
        $result[] = 'b2b_price_list=' . (string) ($this->httpContext->getValue(self::CONTEXT_PRICE_LIST) ?? '0');

        return $result;
    }
}
