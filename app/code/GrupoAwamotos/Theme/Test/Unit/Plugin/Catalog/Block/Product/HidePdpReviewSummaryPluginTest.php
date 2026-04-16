<?php

declare(strict_types=1);

namespace GrupoAwamotos\Theme\Test\Unit\Plugin\Catalog\Block\Product;

use GrupoAwamotos\Theme\Plugin\Catalog\Block\Product\HidePdpReviewSummaryPlugin;
use Magento\Catalog\Block\Product\AbstractProduct;
use Magento\Framework\App\Request\Http;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class HidePdpReviewSummaryPluginTest extends TestCase
{
    private Http&MockObject $request;

    protected function setUp(): void
    {
        $this->request = $this->createMock(Http::class);
    }

    public function testKeepsReviewHtmlOutsideProductView(): void
    {
        $this->request->method('getFullActionName')->willReturn('catalog_category_view');

        $plugin = new HidePdpReviewSummaryPlugin($this->request);
        $subject = $this->createMock(AbstractProduct::class);

        $result = $plugin->afterGetReviewsSummaryHtml($subject, '<div class="product-reviews-summary">x</div>');

        $this->assertSame('<div class="product-reviews-summary">x</div>', $result);
    }

    public function testRemovesReviewHtmlOnProductView(): void
    {
        $this->request->method('getFullActionName')->willReturn('catalog_product_view');

        $plugin = new HidePdpReviewSummaryPlugin($this->request);
        $subject = $this->createMock(AbstractProduct::class);

        $result = $plugin->afterGetReviewsSummaryHtml($subject, '<div class="product-reviews-summary">x</div>');

        $this->assertSame('', $result);
    }
}
