<?php

/**
 * Quote Button Block
 */

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Block\Quote;

use GrupoAwamotos\B2B\Helper\Config;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;

class Button extends Template
{
    private const MARKETING_CSS_ASSET = 'GrupoAwamotos_B2B::css/b2b-marketing.css';
    private const MARKETING_CSS_ASSET_NAME = 'grupoawamotos_b2b_marketing_css';

    /**
     * @var Config
     */
    private $config;

    public function __construct(
        Context $context,
        Config $config,
        array $data = []
    ) {
        $this->config = $config;
        parent::__construct($context, $data);
    }

    /**
     * Load quote CTA styles only when the quote button is active on the page.
     *
     * @return $this
     */
    protected function _prepareLayout()
    {
        parent::_prepareLayout();

        if ($this->shouldDisplay()) {
            $this->pageConfig->addPageAsset(
                self::MARKETING_CSS_ASSET,
                [],
                self::MARKETING_CSS_ASSET_NAME
            );
        }

        return $this;
    }

    /**
     * Check if quote button should be displayed
     *
     * @return bool
     */
    public function shouldDisplay(): bool
    {
        if (!$this->config->isQuoteEnabled()) {
            return false;
        }

        $position = $this->config->getQuoteButtonPosition();
        $context = $this->getData('context') ?: 'cart';

        switch ($position) {
            case 'cart':
                return $context === 'cart';
            case 'product':
                return $context === 'product';
            case 'both':
                return true;
            default:
                return false;
        }
    }

    /**
     * Get quote form URL
     *
     * @return string
     */
    public function getQuoteUrl(): string
    {
        return $this->getUrl('b2b/quote');
    }

    /**
     * Get button text
     *
     * @return string
     */
    public function getButtonText(): string
    {
        return (string) __('Solicitar Cotação');
    }
}
