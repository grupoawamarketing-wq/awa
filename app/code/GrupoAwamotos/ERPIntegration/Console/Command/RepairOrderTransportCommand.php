<?php

declare(strict_types=1);

namespace GrupoAwamotos\ERPIntegration\Console\Command;

use GrupoAwamotos\ERPIntegration\Model\OrderTransportRepair;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class RepairOrderTransportCommand extends Command
{
    private const COMMAND_NAME = 'erp:order:repair-transport';

    public function __construct(
        private readonly OrderTransportRepair $orderTransportRepair
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName(self::COMMAND_NAME)
            ->setDescription('Corrige transporte/redespacho inválido em pedidos B2B web pendentes no Sectra (PEDORIGEM=5)')
            ->addOption('apply', null, InputOption::VALUE_NONE, 'Executa UPDATE no ERP (requer permissão de escrita)')
            ->addOption('generate-sql', 's', InputOption::VALUE_NONE, 'Gera arquivo SQL para execução manual no Sectra')
            ->addOption('status', null, InputOption::VALUE_OPTIONAL, 'Status ERP a corrigir', 'W')
            ->addOption('limit', 'l', InputOption::VALUE_OPTIONAL, 'Limite de pedidos analisados', '100');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $dryRun = !$input->getOption('apply');
        $generateSql = (bool) $input->getOption('generate-sql');
        $status = strtoupper(trim((string) $input->getOption('status')));
        $limit = max(1, (int) $input->getOption('limit'));

        if ($generateSql) {
            $dryRun = true;
        } elseif ($dryRun) {
            $output->writeln('<comment>Modo dry-run — use --apply ou --generate-sql</comment>');
        } else {
            $output->writeln('<fg=red>Aplicando correções no ERP...</fg=red>');
        }

        $result = $this->orderTransportRepair->run($status, $limit, $dryRun);

        if ($result['needs_repair'] === 0) {
            $output->writeln('<info>Nenhum pedido precisa de correção de transporte/redespacho.</info>');
            return Command::SUCCESS;
        }

        $table = new Table($output);
        $table->setHeaders([
            'Pedido ERP',
            'PedidoCli',
            'Cliente',
            'Transp. atual',
            'Transp. novo',
            'RespRed. atual',
            'RespRed. novo',
            'Redespacho',
        ]);

        foreach ($result['items'] as $item) {
            $current = $item['current'];
            $resolved = $item['resolved'];
            $table->addRow([
                $item['pedido_id'],
                $item['pedido_cli'] ?: '-',
                $item['cliente'],
                $current['transportador'],
                $resolved['transportador'],
                $current['respredespacho'],
                $resolved['respredespacho'],
                $resolved['redespacho'] ?? 'null',
            ]);
        }

        $table->render();

        $output->writeln('');

        if ($generateSql) {
            $sql = $this->orderTransportRepair->buildSqlStatements($result['items']);
            $file = BP . '/var/export/erp_repair_order_transport_' . date('Y-m-d_His') . '.sql';
            $dir = dirname($file);
            if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
                $output->writeln('<error>Não foi possível criar var/export/</error>');
                $output->writeln($sql);
                return Command::FAILURE;
            }
            file_put_contents($file, $sql);
            @chmod($file, 0664);
            $output->writeln(sprintf(
                '<info>SQL gerado: %s (%d pedidos)</info>',
                $file,
                $result['needs_repair']
            ));
            $output->writeln('<comment>Execute no SQL Server do Sectra com usuário UPDATE em VE_PEDIDO.</comment>');
            return Command::SUCCESS;
        }

        if ($dryRun) {
            $output->writeln(sprintf(
                '<info>%d pedido(s) seriam corrigidos. Use --generate-sql ou --apply.</info>',
                $result['needs_repair']
            ));
        } else {
            $output->writeln(sprintf(
                '<info>Corrigidos: %d | Erros: %d | Total identificado: %d</info>',
                $result['fixed'],
                $result['errors'],
                $result['needs_repair']
            ));
            if ($result['fixed'] === 0 && $result['errors'] > 0) {
                $output->writeln(
                    '<comment>Permissão UPDATE negada no ERP. Gere o SQL com --generate-sql e execute no Sectra.</comment>'
                );
            }
        }

        return $result['errors'] > 0 && !$dryRun ? Command::FAILURE : Command::SUCCESS;
    }
}
