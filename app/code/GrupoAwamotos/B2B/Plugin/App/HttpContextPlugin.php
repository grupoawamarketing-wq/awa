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
     * IMPORTANT PERFORMANCE NOTE: This method must NOT start a PHP session for anonymous
     * requests. Calling customerSession->isLoggedIn() starts the session, which:
     * 1. Prevents the PhpCookieDisabler from removing PHPSESSID on FPC HIT responses
     * 2. Adds Redis session overhead even when the Full Page Cache serves the HTML
     *
     * Guard: if there is no session cookie in the request, the user is definitively a guest —
     * skip session start entirely and set the default context value directly.
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

        // If there is no session cookie in the request, the visitor cannot be logged in.
        // Avoid starting the PHP session (session_start) entirely for anonymous hits so
        // that the FPC PhpCookieDisabler can strip PHPSESSID from cached responses.
        // NOTE: use PHP's session_name() — do NOT call $this->customerSession->*() here
        // because CustomerSession extends SessionManager whose constructor starts the session.
        if ($request->getCookie(session_name()) === null) {
            $this->httpContext->setValue('b2b_approval_status', 'guest', 'guest');
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
