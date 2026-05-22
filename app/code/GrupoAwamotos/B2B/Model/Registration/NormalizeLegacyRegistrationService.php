<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Model\Registration;

/**
 * Orchestrates audit → backfill phone → backfill razao (cache + OC legacy) → audit.
 */
class NormalizeLegacyRegistrationService
{
    public function __construct(
        private readonly RegistrationMissingDataAuditService $auditService,
        private readonly BackfillPhoneFromAddressService $phoneBackfillService,
        private readonly BackfillRazaoSocialFromCnpjCacheService $razaoCacheBackfillService,
        private readonly BackfillRazaoSocialFromOcLegacyService $razaoOcBackfillService
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function execute(
        bool $apply = false,
        ?int $limit = null,
        ?int $fromId = null,
        ?int $toId = null,
        bool $skipPhone = false,
        bool $skipRazao = false
    ): array {
        $report = [
            'mode' => $apply ? 'apply' : 'dry-run',
            'audit_before' => $this->auditService->collectSummary(),
            'phone' => null,
            'razao_cache' => null,
            'razao_oc' => null,
            'audit_after' => null,
        ];

        if (!$skipPhone) {
            $report['phone'] = $this->phoneBackfillService->execute($apply, $limit, $fromId, $toId);
        }

        if (!$skipRazao) {
            $report['razao_cache'] = $this->razaoCacheBackfillService->execute($apply, $limit, $fromId, $toId);
            $report['razao_oc'] = $this->razaoOcBackfillService->execute($apply, $limit, $fromId, $toId);
        }

        $report['audit_after'] = $this->auditService->collectSummary();

        return $report;
    }
}
