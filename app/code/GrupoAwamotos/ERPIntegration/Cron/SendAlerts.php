<?php

declare(strict_types=1);

namespace GrupoAwamotos\ERPIntegration\Cron;

use GrupoAwamotos\ERPIntegration\Model\Alert\EmailSender;
use GrupoAwamotos\ERPIntegration\Helper\Data as Helper;
use Psr\Log\LoggerInterface;

/**
 * Cron Job - Send Email Alerts
 *
 * Sends automated alerts based on configuration:
 * - At-Risk customers alert (daily)
 * - Sales forecast alerts (when threshold exceeded)
 */
class SendAlerts
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
        if (!$this->helper->isEnabled()) {
            return;
        }

        $alertsSent = 0;

        // Send At-Risk customers alert
        if ($this->helper->isAlertAtRiskEnabled()) {
            try {
                if ($this->emailSender->sendAtRiskAlert()) {
                    $alertsSent++;
                }
            } catch (\Exception $e) {
                $this->logger->error('[ERP Cron] At-Risk alert error: ' . $e->getMessage());
            }
        }

        // Send Forecast alert
        if ($this->helper->isForecastAlertEnabled()) {
            try {
                if ($this->emailSender->sendForecastAlert()) {
                    $alertsSent++;
                }
            } catch (\Exception $e) {
                $this->logger->error('[ERP Cron] Forecast alert error: ' . $e->getMessage());
            }
        }

        $this->logger->info(sprintf('[ERP Cron] Alert check completed. %d alerts sent.', $alertsSent));
    }
}
