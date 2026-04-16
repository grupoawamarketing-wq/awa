<?php

declare(strict_types=1);

namespace GrupoAwamotos\Theme\Plugin\Catalog\Block\Product;

use Magento\Catalog\Block\Product\AbstractProduct;
use Magento\Framework\App\Request\Http;

class HidePdpReviewSummaryPlugin
{
    public function __construct(
        private readonly Http $request
    ) {
    }

    public function afterGetReviewsSummaryHtml(AbstractProduct $subject, string $result): string
    {
        if ($this->request->getFullActionName() !== 'catalog_product_view') {
            return $result;
        }

        return '';
    }
}
