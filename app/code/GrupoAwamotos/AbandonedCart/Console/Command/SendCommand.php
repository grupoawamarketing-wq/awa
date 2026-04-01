<?php

declare(strict_types=1);

namespace GrupoAwamotos\AbandonedCart\Console\Command;

use GrupoAwamotos\AbandonedCart\Cron\SendEmails;
use GrupoAwamotos\AbandonedCart\Api\EmailSenderInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class SendCommand extends Command
{
    private SendEmails $sender;
    private EmailSenderInterface $emailSender;

    public function __construct(
        SendEmails $sender,
        EmailSenderInterface $emailSender
    ) {
        $this->sender = $sender;
        $this->emailSender = $emailSender;
        parent::__construct();
    }

    protected function configure()
    {
        $this->setName('abandonedcart:send')
            ->setDescription('Envia emails de recuperação de carrinho abandonado')
            ->addOption('test', 't', InputOption::VALUE_REQUIRED, 'Email para envio de teste')
            ->addOption('email-number', 'e', InputOption::VALUE_REQUIRED, 'Número do email (1, 2 ou 3)', '1');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $testEmail = $input->getOption('test');
        $emailNumber = (int) $input->getOption('email-number');

        if ($testEmail) {
            $output->writeln("<info>Enviando email de teste #{$emailNumber} para {$testEmail}...</info>");

            try {
                $success = $this->emailSender->sendTestEmail($testEmail, $emailNumber);
                if ($success) {
                    $output->writeln('<info>Email de teste enviado com sucesso!</info>');
                    return Command::SUCCESS;
                } else {
                    $output->writeln('<error>Falha ao enviar email de teste</error>');
                    return Command::FAILURE;
                }
            } catch (\Exception $e) {
                $output->writeln('<error>Erro: ' . $e->getMessage() . '</error>');
                return Command::FAILURE;
            }
        }

        $output->writeln('<info>Enviando emails de recuperação...</info>');

        try {
            $this->sender->execute();
            $output->writeln('<info>Envio concluído com sucesso!</info>');
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $output->writeln('<error>Erro: ' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }
    }
}
