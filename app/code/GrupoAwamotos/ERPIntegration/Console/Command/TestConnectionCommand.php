<?php

declare(strict_types=1);

namespace GrupoAwamotos\ERPIntegration\Console\Command;

use GrupoAwamotos\ERPIntegration\Api\ConnectionInterface;
use GrupoAwamotos\ERPIntegration\Helper\Data as Helper;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\Table;

class TestConnectionCommand extends Command
{
    private ConnectionInterface $connection;
    private Helper $helper;

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
        $this->setName('erp:connection:test')
            ->setDescription('Testa a conexão com o ERP SQL Server');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('<info>Testando conexão com ERP SQL Server...</info>');
        $output->writeln('');

        // Check if enabled
        if (!$this->helper->isEnabled()) {
            $output->writeln('<error>Integração ERP está desabilitada nas configurações.</error>');
            return Command::FAILURE;
        }

        // Check drivers
        $drivers = $this->connection->getAvailableDrivers();
        $output->writeln('<comment>Drivers disponíveis:</comment> ' . (empty($drivers) ? 'Nenhum' : implode(', ', $drivers)));

        if (!$this->connection->hasAvailableDriver()) {
            $output->writeln('');
            $output->writeln('<error>Nenhum driver SQL Server disponível!</error>');
            $output->writeln('');
            $output->writeln('Instale um dos seguintes:');
            $output->writeln('  - php-sqlsrv (Microsoft Driver - recomendado)');
            $output->writeln('  - php-sybase (FreeTDS/dblib)');
            $output->writeln('  - php-odbc (ODBC Driver)');
            return Command::FAILURE;
        }

        // Test connection
        $result = $this->connection->testConnection();

        $output->writeln('');

        if ($result['success']) {
            $output->writeln('<info>✓ Conexão estabelecida com sucesso!</info>');
            $output->writeln('');

            $table = new Table($output);
            $table->setHeaders(['Propriedade', 'Valor']);
            $table->addRows([
                ['Driver utilizado', $result['driver_used'] ?? 'N/A'],
                ['Servidor', $result['server_name'] ?? 'N/A'],
                ['Versão', $result['version'] ?? 'N/A'],
                ['Banco de dados', $result['database'] ?? 'N/A'],
                ['Hora do servidor', $result['server_time'] ?? 'N/A'],
                ['Total de tabelas', $result['table_count'] ?? 0],
                ['String de conexão', $result['connection_string'] ?? 'N/A'],
            ]);
            $table->render();

            if (!empty($result['sample_tables'])) {
                $output->writeln('');
                $output->writeln('<comment>Tabelas de exemplo:</comment>');
                foreach (array_slice($result['sample_tables'], 0, 10) as $tableName) {
                    $output->writeln('  - ' . $tableName);
                }
            }

            return Command::SUCCESS;
        }

        $output->writeln('<error>✗ Falha na conexão!</error>');
        $output->writeln('');
        $output->writeln('<error>Erro: ' . ($result['message'] ?? 'Desconhecido') . '</error>');

        if (!empty($result['troubleshooting'])) {
            $output->writeln('');
            $output->writeln('<comment>Sugestões:</comment>');
            foreach ($result['troubleshooting'] as $tip) {
                $output->writeln('  • ' . $tip);
            }
        }

        return Command::FAILURE;
    }
}
