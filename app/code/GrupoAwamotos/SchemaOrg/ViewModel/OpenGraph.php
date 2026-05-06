<?php

/**
 * ViewModel para Open Graph Meta Tags.
 *
 * Substitui o uso direto de ObjectManager no template opengraph.phtml,
 * permitindo que o bloco seja cacheable=true e compatível com FPC.
 */

declare(strict_types=1);

namespace GrupoAwamotos\SchemaOrg\ViewModel;

use Magento\Catalog\Helper\Image as ImageHelper;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\Registry;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Framework\View\Page\Config as PageConfig;
use Magento\Store\Model\StoreManagerInterface;

class OpenGraph implements ArgumentInterface
{
    private StoreManagerInterface $storeManager;
    private Registry $registry;
    private PageConfig $pageConfig;
    private ImageHelper $imageHelper;
    private HttpRequest $request;

    public function __construct(
        StoreManagerInterface $storeManager,
        Registry $registry,
        PageConfig $pageConfig,
        ImageHelper $imageHelper,
        HttpRequest $request
    ) {
        $this->storeManager = $storeManager;
        $this->registry = $registry;
        $this->pageConfig = $pageConfig;
        $this->imageHelper = $imageHelper;
        $this->request = $request;
    }

    public function getBaseUrl(): string
    {
        return rtrim($this->storeManager->getStore()->getBaseUrl(), '/');
    }

    public function getStoreName(): string
    {
        return $this->storeManager->getStore()->getName();
    }

    /**
     * URL canônica da página atual, sem depender de $_SERVER['REQUEST_URI'].
     * Compatível com Full Page Cache (FPC/Varnish).
     */
    public function getCurrentUrl(): string
    {
        $baseUrl = $this->getBaseUrl();
        $identifier = trim($this->request->getPathInfo(), '/');
        return $identifier ? $baseUrl . '/' . $identifier : $baseUrl . '/';
    }

    public function getCurrentProduct(): ?\Magento\Catalog\Model\Product
    {
        return $this->registry->registry('current_product');
    }

    public function getCurrentCategory(): ?\Magento\Catalog\Model\Category
    {
        return $this->registry->registry('current_category');
    }

    public function getPageTitle(): string
    {
        return $this->pageConfig->getTitle()->get();
    }

    public function getPageDescription(): string
    {
        return $this->pageConfig->getDescription()
            ?: 'Peças e acessórios para motos — AWA Motos';
    }

    public function getDefaultImage(): string
    {
        return $this->getBaseUrl() . '/media/rokanthemes/logo/default/logo-awa.png';
    }

    public function getProductImageUrl(\Magento\Catalog\Model\Product $product): string
    {
        return $this->imageHelper->init($product, 'product_page_main_image')->getUrl();
    }

    /**
     * Verifica se a página atual é a homepage (cms_index_index).
     */
    public function isHomepage(): bool
    {
        return $this->request->getFullActionName() === 'cms_index_index';
    }
}
