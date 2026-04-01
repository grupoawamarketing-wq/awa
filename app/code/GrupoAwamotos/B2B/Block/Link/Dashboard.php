<?php

/**
 * B2B Dashboard Link Block - Only shows for B2B customers
 */

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Block\Link;

use Magento\Framework\View\Element\Html\Link\Current;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\View\Element\Template\Context;
use Magento\Framework\App\DefaultPathInterface;
use Magento\Customer\Block\Account\SortLinkInterface;
use GrupoAwamotos\B2B\Helper\Data as B2BHelper;

class Dashboard extends Current implements SortLinkInterface
{
    /**
     * @var CustomerSession
     */
    private $customerSession;

    /**
     * @var B2BHelper
     */
    private $b2bHelper;

    public function __construct(
        Context $context,
        DefaultPathInterface $defaultPath,
        CustomerSession $customerSession,
        B2BHelper $b2bHelper,
        array $data = []
    ) {
        $this->customerSession = $customerSession;
        $this->b2bHelper = $b2bHelper;
        parent::__construct($context, $defaultPath, $data);
    }

    /**
     * Get sort order
     *
     * @return int
     */
    public function getSortOrder(): int
    {
        return (int) $this->getData('sortOrder') ?: 5;
    }

    /**
     * Render block HTML only for B2B customers
     *
     * @return string
     */
    protected function _toHtml(): string
    {
        if (!$this->customerSession->isLoggedIn()) {
            return '';
        }

        // Check if customer is in B2B group
        $customerGroupId = (int) $this->customerSession->getCustomerGroupId();
        $b2bGroups = $this->b2bHelper->getB2BGroupIds();

        if (!in_array($customerGroupId, $b2bGroups)) {
            return '';
        }

        return parent::_toHtml();
    }
}
