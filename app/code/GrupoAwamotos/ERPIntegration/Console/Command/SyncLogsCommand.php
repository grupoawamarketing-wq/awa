<?php

declare(strict_types=1);

namespace GrupoAwamotos\ERPIntegration\Console\Command;

use GrupoAwamotos\ERPIntegration\Model\ResourceModel\SyncLog as SyncLogResource;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\Table;

class SyncLogsCommand extends Command
{
    private SyncLogResource $syncLogResource;

    public function __construct(SyncLogResource $syncLogResource)
    {
        parent::__construct();
        $this->syncLogResource = $syncLogResource;
    }

    protected function configure(): void
    {
        $this->setName('erp:sync:logs')
            ->setDescription('Exibe logs de sincronização ERP')
            ->addOption('type', 't', InputOption::VALUE_REQUIRED, 'Filtrar por tipo (product, stock, order, customer)')
            ->addOption('limit', 'l', InputOption::VALUE_REQUIRED, 'Limite de registros', 20)
            ->addOption('stats', 's', InputOption::VALUE_NONE, 'Exibe estatísticas ao invés de logs')
            ->addOption('clean', null, InputOption::VALUE_REQUIRED, 'Remove logs mais antigos que X dias');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $type = $input->getOption('type');
        $limit = (int) $input->getOption('limit');
        $showStats = $input->getOption('stats');
        $cleanDays = $input->getOption('clean');

        // Clean old logs
        if ($cleanDays !== null) {
            return $this->cleanLogs((int) $cleanDays, $output);
        }

        // Show stats
        if ($showStats) {
            return $this->showStats($type, $output);
        }

        // Show logs
        return $this->showLogs($type, $limit, $output);
    }

    private function showLogs(?string $type, int $limit, OutputInterface $output): int
    {
        $output->writeln('<info>Logs de Sincronização ERP</info>');
        $output->writeln('');

        $logs = $this->syncLogResource->getRecentLogs($limit, $type);

        if (empty($logs)) {
            $output->writeln('<comment>Nenhum log encontrado.</comment>');
            return Command::SUCCESS;
        }

        $table = new Table($output);
        $table->setHeaders(['Data', 'Tipo', 'Direção', 'Status', 'Registros', 'Mensagem']);

        foreach ($logs as $log) {
            $status = $log['status'];
            $statusFormatted = match ($status) {
                'success' => "<info>{$status}</info>",
                'error' => "<error>{$status}</error>",
                'partial' => "<comment>{$status}</comment>",
                default => $status,
            };

            $table->addRow([
                $log['created_at'] ?? '-',
                $log['entity_type'] ?? '-',
                $log['direction'] ?? '-',
                $statusFormatted,
                $log['records_processed'] ?? '-',
                $this->truncate($log['message'] ?? '', 50),
            ]);
        }

        $table->render();

        return Command::SUCCESS;
    }

    private function showStats(?string $type, OutputInterface $output): int
    {
        $output->writeln('<info>Estatísticas de Sincronização ERP (últimos 7 dias)</info>');
        $output->writeln('');

        $types = $type ? [$type] : ['product', 'stock', 'order', 'customer', 'price'];

        foreach ($types as $entityType) {
            $stats = $this->syncLogResource->getSyncStats($entityType, 7);

            if (empty($stats)) {
                continue;
            }

            $output->writeln("<comment>{$entityType}:</comment>");

            $total = 0;
            $records = 0;
            foreach ($stats as $stat) {
                $status = $stat['status'];
                $count = (int) $stat['total'];
                $processed = (int) ($stat['total_records'] ?? 0);
                $total += $count;
                $records += $processed;

                $statusFormatted = match ($status) {
                    'success' => "<info>{$status}</info>",
                    'error' => "<error>{$status}</error>",
                    'partial' => "<comment>{$status}</comment>",
                    default => $status,
                };

                $output->writeln("  {$statusFormatted}: {$count} execuções, {$processed} registros");
            }

            $output->writeln("  <info>Total: {$total} execuções, {$records} registros processados</info>");
            $output->writeln('');
        }

        return Command::SUCCESS;
    }

    private function cleanLogs(int $days, OutputInterface $output): int
    {
        if ($days < 1) {
            $output->writeln('<error>O número de dias deve ser maior que 0.</error>');
            return Command::FAILURE;
        }

        $output->writeln("<info>Removendo logs mais antigos que {$days} dias...</info>");

        $deleted = $this->syncLogResource->cleanOldLogs($days);

        $output->writeln("<info>✓ {$deleted} registros removidos.</info>");

        return Command::SUCCESS;
    }

    private function truncate(string $text, int $length): string
    {
        if (strlen($text) <= $length) {
            return $text;
        }
        return substr($text, 0, $length - 3) . '...';
    }
}
