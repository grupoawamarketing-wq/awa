<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Block\Header;

use Magento\Framework\View\Element\Html\Link;
use Magento\Framework\View\Element\Template\Context;
use Magento\Customer\Model\Session as CustomerSession;
use GrupoAwamotos\B2B\Helper\Data as B2BHelper;

class QuickOrderLink extends Link
{
    /**
     * @var CustomerSession
     */
    protected $customerSession;

    /**
     * @var B2BHelper
     */
    protected $b2bHelper;

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
     * Determine if link should be rendered
     */
    protected function _toHtml()
    {
        if (!$this->b2bHelper->isEnabled() || !$this->customerSession->isLoggedIn()) {
            return '';
        }

        $groupId = (int) $this->customerSession->getCustomerGroupId();

        // Verifica se o cliente pertence a um grupo B2B
        if (!in_array($groupId, $this->b2bHelper->getB2BGroupIds(), true)) {
            return '';
        }

        return parent::_toHtml();
    }

    public function getHref()
    {
        return $this->getUrl('b2b/quickorder');
    }

    public function getLabel()
    {
        return __('Pedido Rápido');
    }
}
