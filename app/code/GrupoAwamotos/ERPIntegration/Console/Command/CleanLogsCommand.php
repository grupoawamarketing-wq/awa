<?php

declare(strict_types=1);

namespace GrupoAwamotos\ERPIntegration\Console\Command;

use GrupoAwamotos\ERPIntegration\Model\ResourceModel\SyncLog;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class CleanLogsCommand extends Command
{
    private SyncLog $syncLogResource;

    public function __construct(SyncLog $syncLogResource)
    {
        parent::__construct();
        $this->syncLogResource = $syncLogResource;
    }

    protected function configure(): void
    {
        $this->setName('erp:logs:clean')
            ->setDescription('Limpa logs de sincronização antigos')
            ->addOption('days', 'd', InputOption::VALUE_REQUIRED, 'Dias para manter (padrão: 30)', 30)
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Não pedir confirmação');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $days = (int) $input->getOption('days');
        $force = $input->getOption('force');

        if ($days < 1) {
            $output->writeln('<error>O número de dias deve ser maior que 0.</error>');
            return Command::FAILURE;
        }

        // Count logs to be deleted
        $countBefore = $this->getLogCount();
        $countToDelete = $this->getLogCountOlderThan($days);

        $output->writeln('');
        $output->writeln('<info>ERP Sync Logs - Limpeza</info>');
        $output->writeln('');
        $output->writeln("Total de logs:        <comment>{$countBefore}</comment>");
        $output->writeln("Logs a serem removidos: <comment>{$countToDelete}</comment> (anteriores a {$days} dias)");
        $output->writeln("Logs a serem mantidos:  <comment>" . ($countBefore - $countToDelete) . "</comment>");
        $output->writeln('');

        if ($countToDelete === 0) {
            $output->writeln('<info>Nenhum log para remover.</info>');
            return Command::SUCCESS;
        }

        if (!$force) {
            $helper = $this->getHelper('question');
            $question = new ConfirmationQuestion(
                "Deseja realmente remover {$countToDelete} logs? [y/N] ",
                false
            );

            if (!$helper->ask($input, $output, $question)) {
                $output->writeln('<comment>Operação cancelada.</comment>');
                return Command::SUCCESS;
            }
        }

        $output->writeln('');
        $output->writeln('<info>Removendo logs...</info>');

        $deleted = $this->syncLogResource->cleanOldLogs($days);

        $output->writeln('');
        $output->writeln("<info>✓ {$deleted} logs removidos com sucesso!</info>");

        return Command::SUCCESS;
    }

    private function getLogCount(): int
    {
        $connection = $this->syncLogResource->getConnection();
        $select = $connection->select()
            ->from($this->syncLogResource->getMainTable(), 'COUNT(*) as total');

        return (int) $connection->fetchOne($select);
    }

    private function getLogCountOlderThan(int $days): int
    {
        $connection = $this->syncLogResource->getConnection();
        $cutoffDate = (new \DateTime())->modify("-{$days} days")->format('Y-m-d H:i:s');

        $select = $connection->select()
            ->from($this->syncLogResource->getMainTable(), 'COUNT(*) as total')
            ->where('created_at < ?', $cutoffDate);

        return (int) $connection->fetchOne($select);
    }
}
