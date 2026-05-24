<?php
declare(strict_types=1);

namespace GrupoAwamotos\Theme\Block\Html;

use GrupoAwamotos\Theme\ViewModel\HeroResponsiveImage;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Bloco de head: injeta <link rel="preload" as="image" fetchpriority="high">
 * para a imagem hero do primeiro slide ativo.
 *
 * Renderiza ANTES do <body>, garantindo que o browser descubra a imagem
 * imediatamente e possa resolver o LCP.
 *
 * Registrado em cms_index_index.xml no container head.additional.
 */
class HeroPreload extends Template
{
    private ResourceConnection $resource;
    private StoreManagerInterface $storeManager;
    private HeroResponsiveImage $heroResponsiveImage;

    public function __construct(
        Context $context,
        ResourceConnection $resource,
        StoreManagerInterface $storeManager,
        HeroResponsiveImage $heroResponsiveImage,
        array $data = []
    ) {
        $this->resource = $resource;
        $this->storeManager = $storeManager;
        $this->heroResponsiveImage = $heroResponsiveImage;
        parent::__construct($context, $data);
    }

    /**
     * Retorna a URL completa da imagem hero desktop (preload 1920w) ou string vazia.
     */
    public function getHeroImageUrl(): string
    {
        $slideImage = $this->fetchFirstSlideImagePath(false);
        if ($slideImage === '') {
            return '';
        }

        return $this->heroResponsiveImage->getPreloadUrl($slideImage, false);
    }

    /**
     * Retorna a URL da variante mobile (~768w) para preload LCP.
     */
    public function getHeroImageMobileUrl(): string
    {
        $slideImage = $this->fetchFirstSlideImagePath(true);
        if ($slideImage === '') {
            return '';
        }

        return $this->heroResponsiveImage->getPreloadUrl($slideImage, true);
    }

    public function getHeroImageSrcset(): string
    {
        $slideImage = $this->fetchFirstSlideImagePath(false);

        return $slideImage !== '' ? $this->heroResponsiveImage->buildSrcset($slideImage) : '';
    }

    public function getHeroImageMobileSrcset(): string
    {
        $slideImage = $this->fetchFirstSlideImagePath(true);

        return $slideImage !== '' ? $this->heroResponsiveImage->buildSrcset($slideImage) : '';
    }

    public function getHeroImageSizes(): string
    {
        return $this->heroResponsiveImage->getSizesAttribute(false);
    }

    public function getHeroImageMobileSizes(): string
    {
        return $this->heroResponsiveImage->getSizesAttribute(true);
    }

    private function fetchFirstSlideImagePath(bool $preferMobile): string
    {
        try {
            $conn = $this->resource->getConnection();
            $select = $conn->select()
                ->from(
                    ['s' => $this->resource->getTableName('rokanthemes_slide')],
                    ['slide_image_mobile', 'slide_image']
                )
                ->where('s.slide_status = ?', 1)
                ->where('s.slide_image IS NOT NULL')
                ->where('s.slide_image != ?', '')
                ->order('s.slide_position ASC')
                ->limit(1);

            $row = $conn->fetchRow($select);
            if (empty($row)) {
                return '';
            }

            if ($preferMobile && !empty($row['slide_image_mobile'])) {
                return (string) $row['slide_image_mobile'];
            }

            return (string) $row['slide_image'];
        } catch (\Throwable $e) {
            return '';
        }
    }
}
