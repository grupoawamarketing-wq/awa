<?php

declare(strict_types=1);

namespace GrupoAwamotos\StoreSetup\Console\Command;

use GrupoAwamotos\StoreSetup\Model\TransactionalEmailConfigSynchronizer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SyncTransactionalEmailsCommand extends Command
{
    public function __construct(
        private readonly TransactionalEmailConfigSynchronizer $transactionalEmailConfigSynchronizer,
        ?string $name = null
    ) {
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this->setName('grupoawamotos:config:sync-transactional-emails')
            ->setDescription('Sincroniza remetentes transacionais padrão do Magento com endereços reais da loja.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $summary = $this->transactionalEmailConfigSynchronizer->synchronizeDefaultScope();

        $output->writeln('<info>Sincronização de e-mails transacionais concluída.</info>');
        $output->writeln(sprintf(' - Configurações atualizadas: %d', $summary['saved']));
        $output->writeln(sprintf(' - Configurações já corretas: %d', $summary['unchanged']));
        $output->writeln(sprintf(' - Falhas: %d', $summary['failed']));

        if ($summary['changed_paths'] !== []) {
            $output->writeln(' - Paths alterados:');
            foreach ($summary['changed_paths'] as $path) {
                $output->writeln('   ' . $path);
            }
        }

        if ($summary['failed_paths'] !== []) {
            $output->writeln(' - Paths com falha:');
            foreach ($summary['failed_paths'] as $path) {
                $output->writeln('   ' . $path);
            }
        }

        return Command::SUCCESS;
    }
}
