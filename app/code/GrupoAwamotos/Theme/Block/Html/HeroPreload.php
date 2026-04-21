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

            return rtrim($mediaUrl, '/') . '/' . ltrim($slideImage, '/');
        } catch (\Throwable $e) {
            return '';
        }
    }

    /**
     * Retorna a URL da versao mobile (primeira imagem mobile ativa) ou string vazia.
     */
    public function getHeroImageMobileUrl(): string
    {
        try {
            $conn = $this->resource->getConnection();
            $select = $conn->select()
                ->from(
                    ['s' => $this->resource->getTableName('rokanthemes_slide')],
                    ['slide_image_mobile']
                )
                ->where('s.slide_status = ?', 1)
                ->where('s.slide_image_mobile IS NOT NULL')
                ->where('s.slide_image_mobile != ?', '')
                ->order('s.slide_position ASC')
                ->limit(1);

            $slideImage = (string) $conn->fetchOne($select);
            if ($slideImage === '') {
                return '';
            }

            $mediaUrl = $this->storeManager->getStore()->getBaseUrl(
                \Magento\Framework\UrlInterface::URL_TYPE_MEDIA
            );

            return rtrim($mediaUrl, '/') . '/' . ltrim($slideImage, '/');
        } catch (\Throwable $e) {
            return '';
        }
    }
}
