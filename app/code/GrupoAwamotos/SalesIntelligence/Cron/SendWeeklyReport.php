<?php

declare(strict_types=1);

namespace GrupoAwamotos\SalesIntelligence\Cron;

use GrupoAwamotos\SalesIntelligence\Model\EmailSender;
use Psr\Log\LoggerInterface;

class SendWeeklyReport
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
            $this->emailSender->sendWeeklyReport();
        } catch (\Exception $e) {
            $this->logger->error('[SalesIntelligence] SendWeeklyReport cron error: ' . $e->getMessage());
        }
    }
}
