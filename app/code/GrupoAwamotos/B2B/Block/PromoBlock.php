<?php
/**
 * B2B Promotional Block - Shows B2B registration banner for guests
 */
declare(strict_types=1);

namespace GrupoAwamotos\B2B\Block;

use Magento\Framework\View\Element\Template;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\View\Element\Template\Context;
use GrupoAwamotos\B2B\Helper\Data as B2BHelper;

class PromoBlock extends Template
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
    protected $_template = 'GrupoAwamotos_B2B::promo/banner.phtml';

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
     * Load marketing CSS only when the widget is present and visible.
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
     * Should the promo banner be displayed?
     *
     * @return bool
     */
    public function shouldDisplay(): bool
    {
        // Don't display if module is disabled
        if (!$this->b2bHelper->isEnabled()) {
            return false;
        }

        // Show for guests
        if (!$this->customerSession->isLoggedIn()) {
            return true;
        }

        // Don't show for B2B customers (they're already registered)
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
     * Is current visitor a guest?
     */
    public function isGuest(): bool
    {
        return !$this->customerSession->isLoggedIn();
    }

    /**
     * Get login URL for B2B price visibility.
     */
    public function getLoginUrl(): string
    {
        if ($this->b2bHelper->getMode() === 'strict') {
            return $this->getUrl('b2b/account/login');
        }

        return $this->getUrl('customer/account/login');
    }

    /**
     * Get quote request URL.
     */
    public function getQuoteUrl(): string
    {
        return $this->getUrl('b2b/quote/index');
    }

    /**
     * Short operational rules shown in homepage B2B promo.
     *
     * @return string[]
     */
    public function getQuickRules(): array
    {
        return [
            'Pedido mínimo inicial: R$ 500',
            'Reposição a partir de R$ 300',
            'Múltiplos por embalagem quando aplicável',
            'Grupos Atacado/VIP com vantagens progressivas'
        ];
    }

    /**
     * Get benefits list
     *
     * @return array
     */
    public function getBenefits(): array
    {
        return [
            [
                'icon' => 'percent',
                'title' => 'Descontos Exclusivos',
                'description' => 'Até 20% de desconto em todos os produtos'
            ],
            [
                'icon' => 'credit-card',
                'title' => 'Crédito Empresarial',
                'description' => 'Linha de crédito para sua empresa'
            ],
            [
                'icon' => 'file-text',
                'title' => 'Orçamentos Personalizados',
                'description' => 'Solicite cotações para grandes volumes'
            ],
            [
                'icon' => 'truck',
                'title' => 'Frete Diferenciado',
                'description' => 'Condições especiais de entrega'
            ]
        ];
    }
}
