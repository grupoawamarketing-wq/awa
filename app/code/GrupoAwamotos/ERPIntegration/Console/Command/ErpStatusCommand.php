<?php

declare(strict_types=1);

namespace GrupoAwamotos\ERPIntegration\Console\Command;

use GrupoAwamotos\ERPIntegration\Api\ConnectionInterface;
use GrupoAwamotos\ERPIntegration\Model\CircuitBreaker;
use GrupoAwamotos\ERPIntegration\Model\ResourceModel\SyncLog;
use GrupoAwamotos\ERPIntegration\Helper\Data as Helper;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\Table;

class ErpStatusCommand extends Command
{
    private ConnectionInterface $connection;
    private CircuitBreaker $circuitBreaker;
    private SyncLog $syncLogResource;
    private Helper $helper;

    public function __construct(
        ConnectionInterface $connection,
        CircuitBreaker $circuitBreaker,
        SyncLog $syncLogResource,
        Helper $helper
    ) {
        parent::__construct();
        $this->connection = $connection;
        $this->circuitBreaker = $circuitBreaker;
        $this->syncLogResource = $syncLogResource;
        $this->helper = $helper;
    }

    protected function configure(): void
    {
        $this->setName('erp:status')
            ->setDescription('Mostra status completo da integração ERP')
            ->addOption('json', 'j', InputOption::VALUE_NONE, 'Saída em formato JSON')
            ->addOption('detailed', 'd', InputOption::VALUE_NONE, 'Mostra informações detalhadas');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $isJson = $input->getOption('json');
        $isVerbose = $input->getOption('detailed') || $output->isVerbose();

        $status = $this->collectStatus();

        if ($isJson) {
            $output->writeln(json_encode($status, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            return Command::SUCCESS;
        }

        return $this->displayStatus($output, $status, $isVerbose);
    }

    private function collectStatus(): array
    {
        $status = [
            'timestamp' => date('Y-m-d H:i:s'),
            'enabled' => $this->helper->isEnabled(),
            'connection' => $this->getConnectionStatus(),
            'circuit_breaker' => $this->circuitBreaker->getStats(),
            'sync_config' => $this->getSyncConfig(),
            'sync_stats' => $this->getSyncStats(),
            'recent_errors' => $this->getRecentErrors(),
        ];

        return $status;
    }

    private function getConnectionStatus(): array
    {
        if (!$this->helper->isEnabled()) {
            return ['connected' => false, 'message' => 'Integração desabilitada'];
        }

        try {
            $startTime = microtime(true);
            $result = $this->connection->testConnection();
            $latency = round((microtime(true) - $startTime) * 1000, 2);

            return [
                'connected' => $result['success'] ?? false,
                'latency_ms' => $latency,
                'driver' => $result['driver_used'] ?? null,
                'server' => $result['server_name'] ?? null,
                'database' => $result['database'] ?? null,
                'version' => $result['version'] ?? null,
                'message' => $result['message'] ?? null,
            ];
        } catch (\Exception $e) {
            return [
                'connected' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    private function getSyncConfig(): array
    {
        return [
            'products' => [
                'enabled' => $this->helper->isProductSyncEnabled(),
                'frequency' => $this->helper->getProductSyncFrequency() . ' min',
            ],
            'stock' => [
                'enabled' => $this->helper->isStockSyncEnabled(),
                'realtime' => $this->helper->isStockRealtime(),
                'filial' => $this->helper->getStockFilial(),
                'cache_ttl' => $this->helper->getStockCacheTtl() . 's',
            ],
            'customers' => [
                'enabled' => $this->helper->isCustomerSyncEnabled(),
            ],
            'orders' => [
                'enabled' => $this->helper->isOrderSyncEnabled(),
                'queue' => $this->helper->isOrderQueueEnabled(),
            ],
            'prices' => [
                'enabled' => $this->helper->isPriceSyncEnabled(),
            ],
        ];
    }

    private function getSyncStats(): array
    {
        $entityTypes = ['product', 'stock', 'customer', 'order', 'price'];
        $stats = [];

        foreach ($entityTypes as $type) {
            $logs = $this->syncLogResource->getRecentLogs(1, $type);
            $dayStats = $this->syncLogResource->getSyncStats($type, 1);

            $lastLog = $logs[0] ?? null;
            $successCount = 0;
            $errorCount = 0;
            $totalRecords = 0;

            foreach ($dayStats as $stat) {
                if ($stat['status'] === 'success') {
                    $successCount = (int)$stat['total'];
                    $totalRecords = (int)($stat['total_records'] ?? 0);
                } elseif ($stat['status'] === 'error') {
                    $errorCount = (int)$stat['total'];
                }
            }

            $stats[$type] = [
                'last_sync' => $lastLog ? $lastLog['created_at'] : null,
                'last_status' => $lastLog ? $lastLog['status'] : null,
                'last_records' => $lastLog ? (int)($lastLog['records_processed'] ?? 0) : 0,
                'success_24h' => $successCount,
                'error_24h' => $errorCount,
                'records_24h' => $totalRecords,
            ];
        }

        return $stats;
    }

    private function getRecentErrors(): array
    {
        $connection = $this->syncLogResource->getConnection();
        $select = $connection->select()
            ->from($this->syncLogResource->getMainTable(), ['entity_type', 'message', 'created_at'])
            ->where('status = ?', 'error')
            ->order('created_at DESC')
            ->limit(5);

        return $connection->fetchAll($select);
    }

    private function displayStatus(OutputInterface $output, array $status, bool $verbose): int
    {
        $output->writeln('');
        $output->writeln('<info>═══════════════════════════════════════════════════════════════</info>');
        $output->writeln('<info>                    ERP INTEGRATION STATUS                      </info>');
        $output->writeln('<info>═══════════════════════════════════════════════════════════════</info>');
        $output->writeln('');

        // General Status
        $enabledIcon = $status['enabled'] ? '<info>✓</info>' : '<error>✗</error>';
        $output->writeln("Integração habilitada: {$enabledIcon}");
        $output->writeln("Data/Hora: {$status['timestamp']}");
        $output->writeln('');

        // Connection Status
        $output->writeln('<comment>── Conexão SQL Server ──</comment>');
        $conn = $status['connection'];
        if ($conn['connected']) {
            $output->writeln("  Status:   <info>✓ Conectado</info>");
            $output->writeln("  Latência: {$conn['latency_ms']}ms");
            $output->writeln("  Driver:   {$conn['driver']}");
            if ($verbose) {
                $output->writeln("  Servidor: {$conn['server']}");
                $output->writeln("  Database: {$conn['database']}");
                $output->writeln("  Versão:   {$conn['version']}");
            }
        } else {
            $output->writeln("  Status: <error>✗ Desconectado</error>");
            $output->writeln("  Erro:   " . ($conn['message'] ?? 'Desconhecido'));
        }
        $output->writeln('');

        // Circuit Breaker Status
        $output->writeln('<comment>── Circuit Breaker ──</comment>');
        $cb = $status['circuit_breaker'];
        $stateIcon = match ($cb['state']) {
            'CLOSED' => '<info>✓ Normal</info>',
            'OPEN' => '<error>✗ Bloqueado</error>',
            'HALF_OPEN' => '<comment>⚡ Testando</comment>',
            default => $cb['state'],
        };
        $output->writeln("  Estado:  {$stateIcon}");
        $output->writeln("  Falhas:  {$cb['failure_count']}/{$cb['failure_threshold']}");
        if ($cb['state'] === 'OPEN' && $cb['time_until_half_open'] > 0) {
            $output->writeln("  Retry:   {$cb['time_until_half_open']}s");
        }
        $output->writeln('');

        // Sync Configuration
        $output->writeln('<comment>── Configuração de Sync ──</comment>');
        $syncTable = new Table($output);
        $syncTable->setHeaders(['Tipo', 'Habilitado', 'Detalhes']);
        $config = $status['sync_config'];

        $syncTable->addRows([
            ['Produtos', $config['products']['enabled'] ? '<info>Sim</info>' : '<error>Não</error>', 'Freq: ' . $config['products']['frequency']],
            ['Estoque', $config['stock']['enabled'] ? '<info>Sim</info>' : '<error>Não</error>', 'Realtime: ' . ($config['stock']['realtime'] ? 'Sim' : 'Não') . ', Filial: ' . $config['stock']['filial']],
            ['Clientes', $config['customers']['enabled'] ? '<info>Sim</info>' : '<error>Não</error>', ''],
            ['Pedidos', $config['orders']['enabled'] ? '<info>Sim</info>' : '<error>Não</error>', 'Fila: ' . ($config['orders']['queue'] ? 'Sim' : 'Não')],
            ['Preços', $config['prices']['enabled'] ? '<info>Sim</info>' : '<error>Não</error>', ''],
        ]);
        $syncTable->render();
        $output->writeln('');

        // Sync Stats (last 24h)
        $output->writeln('<comment>── Estatísticas de Sync (24h) ──</comment>');
        $statsTable = new Table($output);
        $statsTable->setHeaders(['Tipo', 'Última Sync', 'Status', 'Registros', 'OK/Erro (24h)']);

        foreach ($status['sync_stats'] as $type => $stat) {
            $lastSync = $stat['last_sync'] ? $this->formatRelativeTime($stat['last_sync']) : 'Nunca';
            $statusIcon = match ($stat['last_status']) {
                'success' => '<info>OK</info>',
                'error' => '<error>Erro</error>',
                default => '<comment>-</comment>',
            };

            $statsTable->addRow([
                ucfirst($type),
                $lastSync,
                $statusIcon,
                $stat['last_records'],
                "<info>{$stat['success_24h']}</info>/<error>{$stat['error_24h']}</error>",
            ]);
        }
        $statsTable->render();
        $output->writeln('');

        // Recent Errors
        if (!empty($status['recent_errors'])) {
            $output->writeln('<comment>── Erros Recentes ──</comment>');
            foreach ($status['recent_errors'] as $error) {
                $time = $this->formatRelativeTime($error['created_at']);
                $output->writeln("  <error>[{$error['entity_type']}]</error> {$time}: " . substr($error['message'], 0, 60));
            }
            $output->writeln('');
        }

        $output->writeln('<info>═══════════════════════════════════════════════════════════════</info>');

        return Command::SUCCESS;
    }

    private function formatRelativeTime(string $datetime): string
    {
        $timestamp = strtotime($datetime);
        $diff = time() - $timestamp;

        if ($diff < 60) {
            return 'Agora';
        } elseif ($diff < 3600) {
            $mins = floor($diff / 60);
            return "{$mins} min atrás";
        } elseif ($diff < 86400) {
            $hours = floor($diff / 3600);
            return "{$hours}h atrás";
        } else {
            $days = floor($diff / 86400);
            return "{$days}d atrás";
        }
    }
}
