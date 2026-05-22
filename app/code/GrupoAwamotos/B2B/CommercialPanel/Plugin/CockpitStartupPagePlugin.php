<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\CommercialPanel\Plugin;

use Magento\Authorization\Model\UserContextInterface;
use Magento\Backend\Model\Url as BackendUrl;
use Magento\Framework\AuthorizationInterface;

/**
 * Redireciona perfis comerciais para o dashboard AWA Comercial após login.
 */
class CockpitStartupPagePlugin
{
    private const ACL_COMMERCIAL_DASHBOARD = 'GrupoAwamotos_B2B::commercial_dashboard';

    public function __construct(
        private readonly AuthorizationInterface $authorization,
        private readonly UserContextInterface $userContext
    ) {
    }

    public function afterGetStartupPageUrl(BackendUrl $subject, string $result): string
    {
        if ((int) $this->userContext->getUserId() <= 0) {
            return $result;
        }

        if ($this->authorization->isAllowed(self::ACL_COMMERCIAL_DASHBOARD)) {
            return $subject->getUrl('awa_commercial/commercialdashboard/index');
        }

        return $result;
    }
}
