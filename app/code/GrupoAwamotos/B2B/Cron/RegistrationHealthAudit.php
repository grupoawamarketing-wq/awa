<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Cron;

use GrupoAwamotos\B2B\Model\Registration\RegistrationHealthAuditService;
use Psr\Log\LoggerInterface;

/**
 * Daily read-only health audit for B2B registrations. Never modifies customer data.
 */
class RegistrationHealthAudit
{
    private const LOG_FILE = '/var/log/b2b_registration_health.log';
    private const HEALTH_CSV = 'export/b2b_registration_health_cron.csv';
    private const COMMERCIAL_CSV = 'export/b2b_clientes_pendentes_comercial.csv';

    public function __construct(
        private readonly RegistrationHealthAuditService $healthAuditService,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(): void
    {
        try {
            $summary = $this->healthAuditService->collectSummary();
            $hasIssues = $summary['no_b2b_phone'] > 0
                || $summary['no_b2b_razao_social'] > 0
                || $summary['no_erp_customer_sync_status'] > 0
                || $summary['no_b2b_origin_host'] > 0
                || $summary['no_b2b_registration_campaign'] > 0
                || $summary['phone_in_address_not_b2b_phone'] > 0;

            $line = sprintf(
                '[%s] total=%d sem_phone=%d sem_razao=%d sem_erp=%d sem_origin=%d sem_campaign=%d addr_phone_gap=%d manual=%d test_qa=%d',
                date('Y-m-d H:i:s'),
                $summary['total_with_cnpj'],
                $summary['no_b2b_phone'],
                $summary['no_b2b_razao_social'],
                $summary['no_erp_customer_sync_status'],
                $summary['no_b2b_origin_host'],
                $summary['no_b2b_registration_campaign'],
                $summary['phone_in_address_not_b2b_phone'],
                $summary['manual_correction_pending'],
                $summary['test_qa_accounts']
            );

            @file_put_contents(BP . self::LOG_FILE, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
            $this->logger->info('[B2B Health Cron] ' . $line);

            if (!$hasIssues) {
                return;
            }

            $this->healthAuditService->exportHealthCsv(self::HEALTH_CSV);
            $this->healthAuditService->exportCommercialPendingCsv(self::COMMERCIAL_CSV);
        } catch (\Throwable $e) {
            $this->logger->error('[B2B Health Cron] Falha: ' . $e->getMessage());
        }
    }
}
