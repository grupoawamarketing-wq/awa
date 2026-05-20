<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Platform\Block\Adminhtml;

use GrupoAwamotos\B2B\Platform\Model\Config\PlatformConfig;
use Magento\Backend\Block\Template;

/**
 * Registra CSS do design system quando a feature flag está ativa.
 */
class PlatformAssets extends Template
{
    /** @var list<string> */
    private const CSS_ASSETS = [
        'css/platform/awa-b2b-tokens.css',
        'css/platform/awa-b2b-layout.css',
        'css/platform/awa-b2b-components.css',
        'css/platform/awa-b2b-status.css',
        'css/platform/awa-b2b-responsive.css',
        'css/platform/awa-b2b-compat.css',
    ];

    public function __construct(
        Template\Context $context,
        private readonly PlatformConfig $platformConfig,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * @inheritdoc
     */
    protected function _prepareLayout()
    {
        parent::_prepareLayout();

        if (!$this->platformConfig->isDesignSystemEnabled()) {
            return $this;
        }

        $pageConfig = $this->pageConfig;
        foreach (self::CSS_ASSETS as $asset) {
            $pageConfig->addPageAsset('GrupoAwamotos_B2B::' . $asset);
        }

        $pageConfig->addBodyClass('awa-b2b-platform');

        if ($this->platformConfig->isLegacyMenuBadgeEnabled()) {
            $pageConfig->addBodyClass('awa-b2b-legacy-menu-badge');
        }

        return $this;
    }

    public function isDesignSystemEnabled(): bool
    {
        return $this->platformConfig->isDesignSystemEnabled();
    }

    /**
     * Evita renderização de HTML vazio no admin.
     */
    protected function _toHtml(): string
    {
        return '';
    }
}
