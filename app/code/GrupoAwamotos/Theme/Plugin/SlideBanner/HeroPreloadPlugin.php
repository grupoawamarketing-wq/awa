<?php
declare(strict_types=1);

namespace GrupoAwamotos\Theme\Plugin\SlideBanner;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\View\Page\Config as PageConfig;
use Magento\Store\Model\StoreManagerInterface;
use Rokanthemes\SlideBanner\Block\Slider;

/**
 * Plugin: injeta <link rel="preload" as="image" fetchpriority="high"> no <head>
 * para a imagem hero do primeiro slide desktop.
 *
 * Usa ResourceConnection diretamente para evitar dependencia da API do bloco
 * que pode falhar no contexto before (getSlider() / getBannerCollection()).
 */
class HeroPreloadPlugin
{
    private PageConfig $pageConfig;
    private StoreManagerInterface $storeManager;
    private ResourceConnection $resource;
    private bool $done = false;

    public function __construct(
        PageConfig $pageConfig,
        StoreManagerInterface $storeManager,
        ResourceConnection $resource
    ) {
        $this->pageConfig = $pageConfig;
        $this->storeManager = $storeManager;
        $this->resource = $resource;
    }

    /**
     * Antes de renderizar o HTML do slider, injetar preload da imagem hero.
     *
     * @param Slider $subject
     * @return null
     */
    public function beforeToHtml(Slider $subject): ?array
    {
        if ($this->done) {
            return null;
        }

        try {
            $conn = $this->resource->getConnection();

            $sliderIdentifier = (string) $subject->getSliderId();

            // Buscar imagem do primeiro slide ativo do slider atual.
            $select = $conn->select()
                ->from(['s' => $this->resource->getTableName('rokanthemes_slide')], ['slide_image'])
                ->joinInner(
                    ['sl' => $this->resource->getTableName('rokanthemes_slider')],
                    'sl.slider_id = s.slider_id',
                    []
                )
                ->where('s.slide_status = ?', 1)
                ->where('sl.slider_status = ?', 1)
                ->where('s.slide_image IS NOT NULL')
                ->where('s.slide_image != ?', '');

            if ($sliderIdentifier !== '') {
                if (is_numeric($sliderIdentifier)) {
                    $select->where('sl.slider_id = ?', (int) $sliderIdentifier);
                } else {
                    $select->where('sl.slider_identifier = ?', $sliderIdentifier);
                }
            }

            $select
                ->order('s.slide_position ASC')
                ->limit(1);

            $slideImage = $conn->fetchOne($select);

            if (!$slideImage) {
                return null;
            }

            $mediaUrl = $this->storeManager->getStore()->getBaseUrl(
                \Magento\Framework\UrlInterface::URL_TYPE_MEDIA
            );

            $imageUrl = rtrim($mediaUrl, '/') . '/' . ltrim($slideImage, '/');

            // Prefere WebP quando disponível em disco (85% menor que JPEG/PNG)
            $webpImage = preg_replace('/\.(jpg|jpeg|png)$/i', '.webp', $slideImage);
            $webpDiskPath = BP . '/pub/media/' . ltrim($webpImage, '/');
            if ($webpImage !== $slideImage && file_exists($webpDiskPath)) {
                $imageUrl = rtrim($mediaUrl, '/') . '/' . ltrim($webpImage, '/');
            }

            $this->pageConfig->addRemotePageAsset(
                $imageUrl,
                'link',
                ['attributes' => ['rel' => 'preload', 'as' => 'image', 'fetchpriority' => 'high']],
                'awa-hero-preload-desktop'
            );
            $this->done = true;
        } catch (\Throwable $e) {
            // Silenciosamente falhar -- preload e otimizacao, nao funcionalidade critica
        }

        return null;
    }
}
