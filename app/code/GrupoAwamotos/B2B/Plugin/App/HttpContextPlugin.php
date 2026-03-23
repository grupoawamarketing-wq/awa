<?php
/**
 * Plugin to add B2B approval status to HTTP Context for FPC cache variation.
 *
 * Without this, Magento's built-in Full Page Cache uses the same cache key
 * for all logged-in customers in the same customer_group — meaning a page
 * rendered for a "pending" customer (with prices hidden) gets served to
 * "approved" customers and vice-versa.
 *
 * By adding b2b_approval_status to the HTTP context, the FPC generates
 * separate cache entries per approval status.
 */
declare(strict_types=1);

namespace GrupoAwamotos\B2B\Plugin\App;

use GrupoAwamotos\B2B\Helper\Config;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Http\Context as HttpContext;
use Magento\Framework\App\RequestInterface;

class HttpContextPlugin
{
    public function __construct(
        private readonly HttpContext $httpContext,
        private readonly CustomerSession $customerSession,
        private readonly Config $config
    ) {
    }

    /**
     * Set B2B approval status in HTTP context before action dispatch.
     *
    * @param object $subject
     * @param RequestInterface $request
     * @return array|null
     */
    public function beforeDispatch(object $subject, RequestInterface $request): ?array
    {
        if (!$this->config->isEnabled()) {
            return null;
        }

        $approvalStatus = 'guest';

        if ($this->customerSession->isLoggedIn()) {
            $customerData = $this->customerSession->getCustomerData();
            if ($customerData) {
                $attr = $customerData->getCustomAttribute('b2b_approval_status');
                $approvalStatus = $attr ? (string) $attr->getValue() : 'approved';
            }
        }

        $this->httpContext->setValue('b2b_approval_status', $approvalStatus, 'guest');

        return null;
    }
}
