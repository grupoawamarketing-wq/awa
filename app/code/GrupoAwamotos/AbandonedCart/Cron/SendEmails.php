<?php

declare(strict_types=1);

namespace GrupoAwamotos\AbandonedCart\Cron;

use GrupoAwamotos\AbandonedCart\Api\AbandonedCartRepositoryInterface;
use GrupoAwamotos\AbandonedCart\Api\EmailSenderInterface;
use GrupoAwamotos\AbandonedCart\Helper\Data as Helper;
use GrupoAwamotos\AbandonedCart\Model\WhatsAppSender;
use Psr\Log\LoggerInterface;

class SendEmails
{
    private Helper $helper;
    private AbandonedCartRepositoryInterface $abandonedCartRepository;
    private EmailSenderInterface $emailSender;
    private WhatsAppSender $whatsappSender;
    private LoggerInterface $logger;

    public function __construct(
        Helper $helper,
        AbandonedCartRepositoryInterface $abandonedCartRepository,
        EmailSenderInterface $emailSender,
        WhatsAppSender $whatsappSender,
        LoggerInterface $logger
    ) {
        $this->helper = $helper;
        $this->abandonedCartRepository = $abandonedCartRepository;
        $this->emailSender = $emailSender;
        $this->whatsappSender = $whatsappSender;
        $this->logger = $logger;
    }

    public function execute(): void
    {
        $totalSent = 0;
        $totalFailed = 0;

        // Processar cada nível de email (1, 2, 3)
        for ($emailNumber = 1; $emailNumber <= 3; $emailNumber++) {
            $result = $this->processEmailLevel($emailNumber);
            $totalSent += $result['sent'];
            $totalFailed += $result['failed'];
        }

        if ($totalSent > 0 || $totalFailed > 0) {
            $this->logger->info(sprintf(
                '[AbandonedCart] Email cron completed: sent=%d, failed=%d',
                $totalSent,
                $totalFailed
            ));
        }
    }

    private function processEmailLevel(int $emailNumber): array
    {
        $sent = 0;
        $failed = 0;

        $pendingCarts = $this->abandonedCartRepository->getPendingForEmail($emailNumber, 50);

        foreach ($pendingCarts as $abandonedCart) {
            try {
                $storeId = $abandonedCart->getStoreId();

                if (!$this->helper->isEnabled($storeId)) {
                    continue;
                }

                if (!$this->helper->isEmailEnabled($emailNumber, $storeId)) {
                    continue;
                }

                // Enviar email
                $success = $this->emailSender->sendEmail($abandonedCart, $emailNumber);

                if ($success) {
                    // Marcar como enviado
                    $sentAtMethod = 'setEmail' . $emailNumber . 'SentAt';
                    $sentMethod = 'setEmail' . $emailNumber . 'Sent';

                    $abandonedCart->$sentMethod(true);
                    $abandonedCart->$sentAtMethod(date('Y-m-d H:i:s'));

                    $this->abandonedCartRepository->save($abandonedCart);
                    $sent++;

                    // Send WhatsApp (in addition to email, never replaces)
                    $this->whatsappSender->send($abandonedCart, $emailNumber);
                } else {
                    $failed++;
                }
            } catch (\Exception $e) {
                $this->logger->error(sprintf(
                    '[AbandonedCart] Error sending email %d for cart %d: %s',
                    $emailNumber,
                    $abandonedCart->getEntityId(),
                    $e->getMessage()
                ));
                $failed++;
            }
        }

        if ($sent > 0 || $failed > 0) {
            $this->logger->info(sprintf(
                '[AbandonedCart] Email %d: sent=%d, failed=%d',
                $emailNumber,
                $sent,
                $failed
            ));
        }

        return ['sent' => $sent, 'failed' => $failed];
    }
}
