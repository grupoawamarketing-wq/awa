<?php
/**
 * Block for B2B access restriction modals.
 * Shown to guests (login/register modal) AND to logged-in non-approved users (pending message).
 */
declare(strict_types=1);

namespace GrupoAwamotos\B2B\Block;

use GrupoAwamotos\B2B\Api\PriceVisibilityInterface;
use GrupoAwamotos\B2B\Helper\Config;
use Magento\Customer\Model\Session;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;

class LoginToCart extends Template
{
    private const LOGIN_TO_CART_CSS_ASSET = 'GrupoAwamotos_B2B::css/login-to-cart.css';
    private const LOGIN_TO_CART_CSS_ASSET_NAME = 'grupoawamotos_b2b_login_to_cart_css';

    /**
     * @var Config
     */
    private $config;

    /**
     * @var Session
     */
    private $customerSession;

    /**
     * @var PriceVisibilityInterface
     */
    private $priceVisibility;

    public function __construct(
        Context $context,
        Config $config,
        Session $customerSession,
        PriceVisibilityInterface $priceVisibility,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->config = $config;
        $this->customerSession = $customerSession;
        $this->priceVisibility = $priceVisibility;
    }

    /**
     * Load modal styles only when the modal/banner is actually rendered.
     *
     * @return $this
     */
    protected function _prepareLayout()
    {
        parent::_prepareLayout();

        if ($this->shouldRender()) {
            $this->pageConfig->addPageAsset(
                self::LOGIN_TO_CART_CSS_ASSET,
                [],
                self::LOGIN_TO_CART_CSS_ASSET_NAME
            );
        }

        return $this;
    }

    /**
     * Check if modal should be rendered (guests OR non-approved logged-in users)
     *
     * @return bool
     */
    public function shouldRender(): bool
    {
        if (!$this->config->isEnabled()) {
            return false;
        }

        // Guest: render if hide_add_to_cart_guests is enabled
        if (!$this->customerSession->isLoggedIn()) {
            return $this->config->hideAddToCartForGuests();
        }

        // Logged-in: render if user cannot add to cart (non-approved)
        return !$this->priceVisibility->canAddToCart();
    }

    /**
     * Only output HTML if conditions are met
     *
     * @return string
     */
    protected function _toHtml(): string
    {
        if (!$this->shouldRender()) {
            return '';
        }
        return parent::_toHtml();
    }

    /**
     * Check if user is a guest (not logged in)
     *
     * @return bool
     */
    public function isGuest(): bool
    {
        return !$this->customerSession->isLoggedIn();
    }

    /**
     * Check if user is logged in but pending approval
     *
     * @return bool
     */
    public function isPendingApproval(): bool
    {
        if (!$this->customerSession->isLoggedIn()) {
            return false;
        }
        return !$this->priceVisibility->isCustomerApproved();
    }

    /**
     * Get the pending message from config
     *
     * @return string
     */
    public function getPendingMessage(): string
    {
        $message = $this->config->getPendingMessage();
        if (empty($message)) {
            $message = 'Sua conta está aguardando aprovação. Você receberá um e-mail quando for aprovada.';
        }
        return $message;
    }

    /**
     * Get login URL
     *
     * @return string
     */
    public function getLoginUrl(): string
    {
        if ($this->config->isStrictB2B()) {
            return $this->getUrl('b2b/account/login');
        }

        return $this->getUrl('customer/account/login');
    }

    /**
     * Get register URL
     *
     * @return string
     */
    public function getRegisterUrl(): string
    {
        if ($this->config->isStrictB2B()) {
            return $this->getUrl('b2b/register');
        }

        return $this->getUrl('customer/account/create');
    }

    /**
     * Check if store is operating in strict B2B mode.
     */
    public function isStrictB2B(): bool
    {
        return $this->config->isStrictB2B();
    }

    /**
     * Get B2B register URL
     *
     * @return string
     */
    public function getB2bRegisterUrl(): string
    {
        return $this->getUrl('b2b/register');
    }

    /**
     * Get account dashboard URL
     *
     * @return string
     */
    public function getAccountUrl(): string
    {
        return $this->getUrl('b2b/account/dashboard');
    }
}
