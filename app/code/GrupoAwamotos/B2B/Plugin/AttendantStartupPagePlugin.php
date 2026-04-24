<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Plugin;

use GrupoAwamotos\B2B\Helper\CurrentAttendant;
use Magento\Backend\Model\Url as BackendUrl;

/**
 * Redireciona atendentes para o painel B2B ao fazer login no admin.
 */
class AttendantStartupPagePlugin
{
    public function __construct(
        private readonly CurrentAttendant $currentAttendant
    ) {}

    public function afterGetStartupPageUrl(BackendUrl $subject, string $result): string
    {
        if ($this->currentAttendant->isAttendant()) {
            return $subject->getUrl('grupoawamotos_b2b/attendant/dashboard');
        }

        return $result;
    }
}
