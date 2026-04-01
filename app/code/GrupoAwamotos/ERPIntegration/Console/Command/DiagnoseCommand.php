<?php

declare(strict_types=1);

namespace GrupoAwamotos\ERPIntegration\Console\Command;

use GrupoAwamotos\ERPIntegration\Api\ConnectionInterface;
use GrupoAwamotos\ERPIntegration\Helper\Data as Helper;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\Table;

class DiagnoseCommand extends Command
{
    private ConnectionInterface $connection;
    private Helper $helper;

    // Expected tables for each sync type
    private const EXPECTED_TABLES = [
        'products' => ['MT_MATERIAL', 'MT_MATERIALCUSTO', 'MT_COMPOSICAOPRECO'],
        'customers' => ['FN_FORNECEDORES', 'FN_CONTATO'],
        'orders' => ['VE_PEDIDO', 'VE_PEDIDOITENS'],
        'stock' => ['MT_ESTOQUE', 'MT_MATERIAL'],
        'prices' => ['MT_COMPOSICAOPRECO', 'MT_MATERIALCUSTO'],
    ];

    public function __construct(
        ConnectionInterface $connection,
        Helper $helper
    ) {
        parent::__construct();
        $this->connection = $connection;
        $this->helper = $helper;
    }

    protected function configure(): void
    {
        $this->setName('erp:diagnose')
            ->setDescription('Diagnostica a configuração e estrutura do ERP')
            ->addOption('tables', 't', InputOption::VALUE_NONE, 'Mostra todas as tabelas disponíveis')
            ->addOption('columns', 'c', InputOption::VALUE_REQUIRED, 'Mostra colunas de uma tabela específica')
            ->addOption('sql', null, InputOption::VALUE_REQUIRED, 'Executa uma query SQL diretamente');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->helper->isEnabled()) {
            $output->writeln('<error>Integração ERP está desabilitada.</error>');
            return Command::FAILURE;
        }

        $showTables = $input->getOption('tables');
        $tableName = $input->getOption('columns');
        $query = $input->getOption('sql');

        // Execute custom query
        if ($query) {
            return $this->executeQuery($query, $output);
        }

        // Show columns for specific table
        if ($tableName) {
            return $this->showTableColumns($tableName, $output);
        }

        // Show all tables
        if ($showTables) {
            return $this->showAllTables($output);
        }

        // Default: run full diagnosis
        return $this->runDiagnosis($output);
    }

    private function runDiagnosis(OutputInterface $output): int
    {
        $output->writeln('');
        $output->writeln('<info>═══════════════════════════════════════════════════════════════</info>');
        $output->writeln('<info>                    ERP DIAGNÓSTICO                             </info>');
        $output->writeln('<info>═══════════════════════════════════════════════════════════════</info>');
        $output->writeln('');

        // Test connection
        $output->writeln('<comment>── Conexão ──</comment>');
        $connResult = $this->connection->testConnection();

        if ($connResult['success'] ?? false) {
            $output->writeln("  Status:   <info>✓ Conectado</info>");
            $output->writeln("  Driver:   {$connResult['driver_used']}");
            $output->writeln("  Servidor: {$connResult['server_name']}");
            $output->writeln("  Database: {$connResult['database']}");
            $output->writeln("  Tabelas:  {$connResult['table_count']}");
        } else {
            $output->writeln("  Status: <error>✗ Falha na conexão</error>");
            $output->writeln("  Erro:   " . ($connResult['message'] ?? 'Desconhecido'));
            return Command::FAILURE;
        }

        $output->writeln('');

        // Check required tables
        $output->writeln('<comment>── Tabelas Necessárias ──</comment>');

        $availableTables = $this->getAvailableTables();
        $tableStatus = [];

        foreach (self::EXPECTED_TABLES as $syncType => $tables) {
            $output->writeln("  <info>{$syncType}:</info>");

            foreach ($tables as $table) {
                $exists = in_array($table, $availableTables);
                $icon = $exists ? '<info>✓</info>' : '<error>✗</error>';
                $output->writeln("    {$icon} {$table}");

                $tableStatus[$table] = $exists;
            }
        }

        $output->writeln('');

        // Check table counts
        $output->writeln('<comment>── Contagem de Registros ──</comment>');

        $countTable = new Table($output);
        $countTable->setHeaders(['Tabela', 'Registros', 'Status']);

        $counts = [
            'MT_MATERIAL' => "SELECT COUNT(*) as c FROM MT_MATERIAL WHERE CCKATIVO = 'S'",
            'FN_FORNECEDORES' => "SELECT COUNT(*) as c FROM FN_FORNECEDORES WHERE CKCLIENTE = 'S'",
            'VE_PEDIDO' => "SELECT COUNT(*) as c FROM VE_PEDIDO WHERE STATUS NOT IN ('C', 'X')",
        ];

        foreach ($counts as $table => $sql) {
            if (!($tableStatus[$table] ?? false)) {
                $countTable->addRow([$table, '-', '<comment>Tabela não existe</comment>']);
                continue;
            }

            try {
                $result = $this->connection->fetchOne($sql);
                $count = $result['c'] ?? $result['C'] ?? 0;
                $countTable->addRow([$table, number_format($count, 0, '', '.'), '<info>OK</info>']);
            } catch (\Exception $e) {
                $countTable->addRow([$table, '-', '<error>Erro</error>']);
            }
        }

        $countTable->render();
        $output->writeln('');

        // Configuration check
        $output->writeln('<comment>── Configuração ──</comment>');
        $output->writeln("  Sync Produtos:  " . ($this->helper->isProductSyncEnabled() ? '<info>Habilitado</info>' : '<comment>Desabilitado</comment>'));
        $output->writeln("  Sync Estoque:   " . ($this->helper->isStockSyncEnabled() ? '<info>Habilitado</info>' : '<comment>Desabilitado</comment>'));
        $output->writeln("  Sync Clientes:  " . ($this->helper->isCustomerSyncEnabled() ? '<info>Habilitado</info>' : '<comment>Desabilitado</comment>'));
        $output->writeln("  Sync Pedidos:   " . ($this->helper->isOrderSyncEnabled() ? '<info>Habilitado</info>' : '<comment>Desabilitado</comment>'));
        $output->writeln("  Sync Preços:    " . ($this->helper->isPriceSyncEnabled() ? '<info>Habilitado</info>' : '<comment>Desabilitado</comment>'));
        $output->writeln("  Sync Imagens:   " . ($this->helper->isImageSyncEnabled() ? '<info>Habilitado</info>' : '<comment>Desabilitado</comment>'));
        $output->writeln("  Estoque Realtime: " . ($this->helper->isStockRealtime() ? '<info>Habilitado</info>' : '<comment>Desabilitado</comment>'));
        $output->writeln("  Filial Estoque: " . $this->helper->getStockFilial());

        $output->writeln('');
        $output->writeln('<info>═══════════════════════════════════════════════════════════════</info>');

        return Command::SUCCESS;
    }

    private function showAllTables(OutputInterface $output): int
    {
        $output->writeln('<info>Listando tabelas do ERP...</info>');
        $output->writeln('');

        $tables = $this->getAvailableTables();

        if (empty($tables)) {
            $output->writeln('<error>Nenhuma tabela encontrada.</error>');
            return Command::FAILURE;
        }

        $output->writeln("Total de tabelas: <comment>" . count($tables) . "</comment>");
        $output->writeln('');

        // Group by prefix
        $grouped = [];
        foreach ($tables as $table) {
            $prefix = explode('_', $table)[0] ?? 'OTHER';
            $grouped[$prefix][] = $table;
        }

        ksort($grouped);

        foreach ($grouped as $prefix => $prefixTables) {
            $output->writeln("<comment>{$prefix}_* (" . count($prefixTables) . " tabelas):</comment>");
            foreach ($prefixTables as $table) {
                $output->writeln("  - {$table}");
            }
            $output->writeln('');
        }

        return Command::SUCCESS;
    }

    private function showTableColumns(string $tableName, OutputInterface $output): int
    {
        $output->writeln("<info>Colunas da tabela: {$tableName}</info>");
        $output->writeln('');

        try {
            // SQL Server specific query to get column info
            $sql = "SELECT COLUMN_NAME, DATA_TYPE, CHARACTER_MAXIMUM_LENGTH, IS_NULLABLE
                    FROM INFORMATION_SCHEMA.COLUMNS
                    WHERE TABLE_NAME = :table
                    ORDER BY ORDINAL_POSITION";

            $columns = $this->connection->query($sql, [':table' => $tableName]);

            if (empty($columns)) {
                $output->writeln('<error>Tabela não encontrada ou sem colunas.</error>');
                return Command::FAILURE;
            }

            $table = new Table($output);
            $table->setHeaders(['Coluna', 'Tipo', 'Tamanho', 'Nulo']);

            foreach ($columns as $col) {
                $table->addRow([
                    $col['COLUMN_NAME'],
                    $col['DATA_TYPE'],
                    $col['CHARACTER_MAXIMUM_LENGTH'] ?? '-',
                    $col['IS_NULLABLE'] === 'YES' ? 'Sim' : 'Não',
                ]);
            }

            $table->render();

            // Show sample data
            $output->writeln('');
            $output->writeln('<comment>Amostra de dados (5 registros):</comment>');

            try {
                $sampleSql = "SELECT TOP 5 * FROM [{$tableName}]";
                $samples = $this->connection->query($sampleSql);

                if (!empty($samples)) {
                    $sampleTable = new Table($output);
                    $sampleTable->setHeaders(array_keys($samples[0]));

                    foreach ($samples as $row) {
                        $formattedRow = [];
                        foreach ($row as $value) {
                            $formattedRow[] = mb_substr((string)$value, 0, 30);
                        }
                        $sampleTable->addRow($formattedRow);
                    }

                    $sampleTable->render();
                }
            } catch (\Exception $e) {
                $output->writeln('<comment>Não foi possível obter amostra de dados.</comment>');
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $output->writeln('<error>Erro: ' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }
    }

    private function executeQuery(string $query, OutputInterface $output): int
    {
        $output->writeln('<comment>Executando query...</comment>');
        $output->writeln('');
        $output->writeln("<info>SQL:</info> {$query}");
        $output->writeln('');

        try {
            $startTime = microtime(true);
            $results = $this->connection->query($query);
            $execTime = round((microtime(true) - $startTime) * 1000, 2);

            if (empty($results)) {
                $output->writeln('<comment>Nenhum resultado retornado.</comment>');
                $output->writeln("Tempo de execução: {$execTime}ms");
                return Command::SUCCESS;
            }

            $output->writeln("Resultados: <info>" . count($results) . "</info>");
            $output->writeln("Tempo de execução: {$execTime}ms");
            $output->writeln('');

            $table = new Table($output);
            $table->setHeaders(array_keys($results[0]));

            foreach (array_slice($results, 0, 50) as $row) {
                $formattedRow = [];
                foreach ($row as $value) {
                    $formattedRow[] = mb_substr((string)$value, 0, 40);
                }
                $table->addRow($formattedRow);
            }

            $table->render();

            if (count($results) > 50) {
                $output->writeln('');
                $output->writeln('<comment>Mostrando apenas os primeiros 50 resultados.</comment>');
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $output->writeln('<error>Erro: ' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }
    }

    private function getAvailableTables(): array
    {
        try {
            // Include both tables and views since views are commonly used in ERP systems
            $sql = "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_TYPE IN ('BASE TABLE', 'VIEW') ORDER BY TABLE_NAME";
            $results = $this->connection->query($sql);

            $tables = [];
            foreach ($results as $row) {
                $tables[] = $row['TABLE_NAME'];
            }

            return $tables;
        } catch (\Exception $e) {
            return [];
        }
    }
}
