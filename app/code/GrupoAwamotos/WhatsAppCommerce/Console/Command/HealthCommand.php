<?php

declare(strict_types=1);

namespace GrupoAwamotos\WhatsAppCommerce\Console\Command;

use GrupoAwamotos\WhatsAppCommerce\Api\HealthCheckInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * CLI: php bin/magento awa:whatsapp:health
 */
class HealthCommand extends Command
{
    public function __construct(
        private readonly HealthCheckInterface $healthCheck,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('awa:whatsapp:health')
            ->setDescription('Verifica saude do WhatsApp Commerce (modulo, DB, API)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('<info>WhatsApp Commerce - Health Check</info>');
        $output->writeln(str_repeat('-', 50));

        $result = $this->healthCheck->check();

        foreach ($result['checks'] as $name => $check) {
            $icon = match ($check['status']) {
                'ok' => '<fg=green>[OK]</>',
                'warning' => '<fg=yellow>[WARN]</>',
                'info' => '<fg=cyan>[INFO]</>',
                default => '<fg=red>[FAIL]</>',
            };

            $output->writeln(sprintf('  %s %s: %s', $icon, $name, $check['message']));
        }

        $output->writeln(str_repeat('-', 50));

        if ($result['healthy']) {
            $output->writeln('<info>Status: Todos os componentes operacionais</info>');
            return Command::SUCCESS;
        }

        $output->writeln('<error>Status: Problemas detectados</error>');
        return Command::FAILURE;
    }
}
