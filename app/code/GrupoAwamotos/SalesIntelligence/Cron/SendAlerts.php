<?php

declare(strict_types=1);

namespace GrupoAwamotos\SalesIntelligence\Cron;

use GrupoAwamotos\SalesIntelligence\Model\EmailSender;
use Psr\Log\LoggerInterface;

class SendAlerts
{
    private EmailSender $emailSender;
    private LoggerInterface $logger;

    public function __construct(
        EmailSender $emailSender,
        LoggerInterface $logger
    ) {
        $this->emailSender = $emailSender;
        $this->logger = $logger;
    }

    public function execute(): void
    {
        try {
            $this->emailSender->sendDailyAlert();
        } catch (\Exception $e) {
            $this->logger->error('[SalesIntelligence] SendAlerts cron error: ' . $e->getMessage());
        }
    }
}
