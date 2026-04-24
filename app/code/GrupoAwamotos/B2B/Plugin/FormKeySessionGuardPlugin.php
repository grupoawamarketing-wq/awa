<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Plugin;

use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\Data\Form\FormKey;

/**
 * Prevents PHP session_start() on FPC HIT paths caused by RegisterFormKeyFromCookie.
 *
 * Problem: Magento\PageCache\Plugin\RegisterFormKeyFromCookie::beforeDispatch() fires for
 * every request that has a `form_key` cookie. It calls FormKey::set() → session->setData()
 * → SessionManager::__construct() → session_start() → Redis read+write overhead for every
 * returning guest, even when the response will be served from Full Page Cache.
 *
 * Fix: Skip FormKey::set() when the PHP session is not yet started AND the request is not
 * a POST (i.e., no form submission needs CSRF validation). On GET FPC HITs, the form_key
 * cookie is sufficient for JavaScript to populate forms. When the user submits a form
 * (POST), RegisterFormKeyFromCookie will run again at that point, the session will start
 * via other means, and the form_key will be properly set then.
 *
 * NOTE: This plugin requires DI compilation to take effect:
 *   bin/magento setup:di:compile
 *
 * Safe scenarios:
 *  - Fresh guest GET, FPC HIT     → no PHPSESSID cookie → session not started → SKIP ✅
 *  - Returning guest GET, FPC HIT → form_key cookie but no PHPSESSID → SKIP ✅
 *  - GET page render (FPC MISS)   → session already active by the time FormKey::set() is
 *                                   called during template rendering → ALLOW ✅
 *  - POST form submission         → request->isPost() → ALLOW → CSRF validation works ✅
 *  - AJAX POST                    → request->isPost() → ALLOW ✅
 */
class FormKeySessionGuardPlugin
{
    public function __construct(
        private readonly HttpRequest $request
    ) {
    }

    /**
     * @param FormKey $subject
     * @param callable $proceed
     * @param string $value
     * @return void
     */
    public function aroundSet(FormKey $subject, callable $proceed, string $value): void
    {
        // If the session is already active, proceed normally.
        // This handles FPC MISS paths where the session was started during action execution,
        // as well as POST requests where CustomerSession/other code already started the session.
        if (session_status() === PHP_SESSION_ACTIVE) {
            $proceed($value);
            return;
        }

        // If this is a form submission (POST/PUT/DELETE), allow the session to start so
        // CSRF validation can compare the submitted form_key against the session value.
        if ($this->request->isPost() || $this->request->isPut() || $this->request->isDelete()) {
            $proceed($value);
            return;
        }

        // GET/HEAD request with no active session: skip FormKey::set() to prevent
        // session_start() overhead on FPC HIT paths.
        // The form_key cookie is already set and JS will read it from there.
    }
}
