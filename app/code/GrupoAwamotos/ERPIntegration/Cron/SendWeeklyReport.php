<?php

declare(strict_types=1);

namespace GrupoAwamotos\ERPIntegration\Cron;

use GrupoAwamotos\ERPIntegration\Model\Alert\EmailSender;
use GrupoAwamotos\ERPIntegration\Helper\Data as Helper;
use Psr\Log\LoggerInterface;

/**
 * Cron Job - Send Weekly RFM Report
 *
 * Runs every Monday at 8:00 AM
 */
class SendWeeklyReport
{
    private EmailSender $emailSender;
    private Helper $helper;
    private LoggerInterface $logger;

    public function __construct(
        EmailSender $emailSender,
        Helper $helper,
        LoggerInterface $logger
    ) {
        $this->emailSender = $emailSender;
        $this->helper = $helper;
        $this->logger = $logger;
    }

    /**
     * Execute cron job
     */
    public function execute(): void
    {
        if (!$this->helper->isEnabled() || !$this->helper->isRfmEnabled()) {
            return;
        }

        $this->logger->info('[ERP Cron] Sending weekly RFM report...');

        try {
            if ($this->emailSender->sendWeeklyRfmReport()) {
                $this->logger->info('[ERP Cron] Weekly RFM report sent successfully.');
            } else {
                $this->logger->info('[ERP Cron] Weekly RFM report not sent (no data or disabled).');
            }
        } catch (\Exception $e) {
            $this->logger->error('[ERP Cron] Weekly report error: ' . $e->getMessage());
        }
    }
}
