<?php

/**
 * Plugin para redirecionar o dashboard padrão do Magento para o dashboard B2B unificado.
 * Clientes B2B são redirecionados para /b2b/account/dashboard.
 */

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Plugin\Customer\Account;

use GrupoAwamotos\B2B\Helper\Config;
use GrupoAwamotos\B2B\Helper\Data as B2BHelper;
use Magento\Customer\Controller\Account\Index as AccountIndex;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Controller\Result\RedirectFactory;

class DashboardRedirectPlugin
{
    private Config $config;
    private B2BHelper $b2bHelper;
    private RedirectFactory $redirectFactory;

    public function __construct(
        Config $config,
        B2BHelper $b2bHelper,
        RedirectFactory $redirectFactory
    ) {
        $this->config = $config;
        $this->b2bHelper = $b2bHelper;
        $this->redirectFactory = $redirectFactory;
    }

    /**
     * Redirect B2B customers from standard dashboard to B2B dashboard
     *
     * @param AccountIndex $subject
     * @param \Closure $proceed
     * @return \Magento\Framework\Controller\ResultInterface
     */
    public function aroundExecute(AccountIndex $subject, \Closure $proceed): ResultInterface
    {
        if ($this->config->isEnabled() && $this->b2bHelper->isB2BCustomer()) {
            $redirect = $this->redirectFactory->create();
            $redirect->setPath('b2b/account/dashboard');
            return $redirect;
        }

        return $proceed();
    }
}
