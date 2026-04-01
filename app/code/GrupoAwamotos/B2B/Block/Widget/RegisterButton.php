<?php

/**
 * B2B Register Button Widget
 */

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Block\Widget;

use Magento\Framework\View\Element\Template;
use Magento\Widget\Block\BlockInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\View\Element\Template\Context;
use GrupoAwamotos\B2B\Helper\Data as B2BHelper;

class RegisterButton extends Template implements BlockInterface
{
    private const MARKETING_CSS_ASSET = 'GrupoAwamotos_B2B::css/b2b-marketing.css';
    private const MARKETING_CSS_ASSET_NAME = 'grupoawamotos_b2b_marketing_css';

    /**
     * @var CustomerSession
     */
    private $customerSession;

    /**
     * @var B2BHelper
     */
    private $b2bHelper;

    /**
     * @var string
     */
    protected $_template = 'GrupoAwamotos_B2B::widget/register-button.phtml';

    public function __construct(
        Context $context,
        CustomerSession $customerSession,
        B2BHelper $b2bHelper,
        array $data = []
    ) {
        $this->customerSession = $customerSession;
        $this->b2bHelper = $b2bHelper;
        parent::__construct($context, $data);
    }

    /**
     * Load widget styling only when the widget will render.
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
     * Should the button be displayed?
     *
     * @return bool
     */
    public function shouldDisplay(): bool
    {
        if (!$this->b2bHelper->isEnabled()) {
            return false;
        }

        if (!$this->customerSession->isLoggedIn()) {
            return true;
        }

        $customerGroupId = (int) $this->customerSession->getCustomerGroupId();
        $b2bGroups = $this->b2bHelper->getB2BGroupIds();

        return !in_array($customerGroupId, $b2bGroups);
    }

    /**
     * Get B2B registration URL
     *
     * @return string
     */
    public function getRegisterUrl(): string
    {
        return $this->getUrl('b2b/register');
    }

    /**
     * Get button text
     *
     * @return string
     */
    public function getButtonText(): string
    {
        return $this->getData('button_text') ?: __('Seja um Revendedor');
    }

    /**
     * Get button style class
     *
     * @return string
     */
    public function getButtonStyle(): string
    {
        $style = $this->getData('button_style') ?: 'primary';
        return 'b2b-btn-' . $style;
    }
}
