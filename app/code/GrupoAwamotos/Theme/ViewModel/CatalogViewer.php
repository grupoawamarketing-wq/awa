<?php
declare(strict_types=1);

namespace GrupoAwamotos\Theme\ViewModel;

use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;

class CatalogViewer implements ArgumentInterface
{
    private const PDF_RELATIVE_PATH = 'media/awa/catalogo/catalogo-2026.pdf';

    public function __construct(
        private readonly UrlInterface $urlBuilder
    ) {
    }

    public function getPdfUrl(): string
    {
        return $this->urlBuilder->getBaseUrl(['_type' => UrlInterface::URL_TYPE_MEDIA])
            . 'awa/catalogo/catalogo-2026.pdf';
    }

    public function getCoverImageUrl(): string
    {
        return $this->urlBuilder->getBaseUrl(['_type' => UrlInterface::URL_TYPE_MEDIA])
            . 'import/catalog/banners/catalogo.jpg';
    }

    public function getMobileCoverImageUrl(): string
    {
        return $this->urlBuilder->getBaseUrl(['_type' => UrlInterface::URL_TYPE_MEDIA])
            . 'import/catalog/banners/banner-mobile-catalogo.jpg';
    }

    public function getB2bRegisterUrl(): string
    {
        return $this->urlBuilder->getUrl('b2b/register');
    }
}
