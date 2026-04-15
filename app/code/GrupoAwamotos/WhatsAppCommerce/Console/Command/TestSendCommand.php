<?php

declare(strict_types=1);

namespace GrupoAwamotos\WhatsAppCommerce\Console\Command;

use GrupoAwamotos\WhatsAppCommerce\Model\MessageSender;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * CLI: php bin/magento awa:whatsapp:test-send 5516991234567
 */
class TestSendCommand extends Command
{
    public function __construct(
        private readonly MessageSender $messageSender,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('awa:whatsapp:test-send')
            ->setDescription('Envia mensagem de teste via WhatsApp (Evolution API)')
            ->addArgument('phone', InputArgument::REQUIRED, 'Numero com codigo do pais (ex: 5516991234567)')
            ->addOption('message', 'm', InputOption::VALUE_OPTIONAL, 'Mensagem customizada', '');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $phone = $input->getArgument('phone');
        $customMessage = $input->getOption('message');

        $digits = preg_replace('/\D/', '', (string) $phone) ?? '';
        if (strlen($digits) < 12) {
            $output->writeln('<error>Numero invalido. Use formato com DDI: 5516991234567</error>');
            return Command::FAILURE;
        }

        $message = $customMessage ?: sprintf(
            "AWA Motos - Mensagem de Teste\n\nData: %s\nSistema WhatsApp Commerce operacional!",
            date('d/m/Y H:i:s')
        );

        $output->writeln(sprintf('<info>Enviando para %s...</info>', $digits));

        $success = $this->messageSender->send($digits, $message);

        if ($success) {
            $output->writeln('<info>Mensagem enviada com sucesso!</info>');
            return Command::SUCCESS;
        }

        $output->writeln('<error>Falha ao enviar - verifique var/log/whatsapp_commerce.log</error>');
        return Command::FAILURE;
    }
}
