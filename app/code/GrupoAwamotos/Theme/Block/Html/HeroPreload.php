<?php
declare(strict_types=1);

namespace GrupoAwamotos\Theme\Block\Html;

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

    public function __construct(
        Context $context,
        ResourceConnection $resource,
        StoreManagerInterface $storeManager,
        array $data = []
    ) {
        $this->resource = $resource;
        $this->storeManager = $storeManager;
        parent::__construct($context, $data);
    }

    /**
     * Retorna a URL completa da imagem hero (primeiro slide ativo) ou string vazia.
     */
    public function getHeroImageUrl(): string
    {
        try {
            $conn = $this->resource->getConnection();
            $select = $conn->select()
                ->from(
                    ['s' => $this->resource->getTableName('rokanthemes_slide')],
                    ['slide_image']
                )
                ->where('s.slide_status = ?', 1)
                ->where('s.slide_image IS NOT NULL')
                ->where('s.slide_image != ?', '')
                ->order('s.slide_position ASC')
                ->limit(1);

            $slideImage = (string) $conn->fetchOne($select);
            if ($slideImage === '') {
                return '';
            }

            $mediaUrl = $this->storeManager->getStore()->getBaseUrl(
                \Magento\Framework\UrlInterface::URL_TYPE_MEDIA
            );

            // Preload da imagem hero no <head> (descoberta antecipada pelo browser scanner).
            // A URL .webp é idêntica à gerada pelo slider_home5.phtml no body,
            // então o browser NÃO faz duplo download (deduplicação de URL).
            // Rokanthemes salva a imagem como .jpeg no DB (ex: slidebanner/b/a/bauletos.jpg.jpeg)
            // O arquivo WebP correspondente tem extensão .webp (ex: bauletos.jpg.webp)
            $webpImage = $slideImage . '.webp';
            return rtrim($mediaUrl, '/') . '/' . ltrim($webpImage, '/');
        } catch (\Throwable $e) {
            return '';
        }
    }

    /**
     * Retorna a URL da versao mobile (primeira imagem mobile ativa) ou string vazia.
     * Se slide_image_mobile for NULL, usa slide_image como fallback (mesma imagem).
     */
    public function getHeroImageMobileUrl(): string
    {
        try {
            $conn = $this->resource->getConnection();
            // Tenta o campo mobile primeiro; se NULL, cai para slide_image
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

            // Usa mobile especifico se disponivel, senao fallback para desktop
            $slideImage = (string) (!empty($row['slide_image_mobile'])
                ? $row['slide_image_mobile']
                : $row['slide_image']);

            if ($slideImage === '') {
                return '';
            }

            $mediaUrl = $this->storeManager->getStore()->getBaseUrl(
                \Magento\Framework\UrlInterface::URL_TYPE_MEDIA
            );

            // Mesma logica do getHeroImageUrl: Rokanthemes salva .jpeg no DB,
            // arquivo WebP correspondente tem extensao .webp
            $webpImage = $slideImage . '.webp';
            return rtrim($mediaUrl, '/') . '/' . ltrim($webpImage, '/');
        } catch (\Throwable $e) {
            return '';
        }
    }
}
