<?php

declare(strict_types=1);

namespace GrupoAwamotos\ERPIntegration\Console\Command;

use GrupoAwamotos\ERPIntegration\Api\ConnectionInterface;
use GrupoAwamotos\ERPIntegration\Helper\Data as Helper;
use GrupoAwamotos\ERPIntegration\Model\B2BClientRegistration;
use Magento\Framework\App\ResourceConnection;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Diagnoses and reports the Sectra B2B integration status.
 *
 * Shows:
 *  - GR_INTEGRACAOVALIDADOR registration stats (registered vs unregistered)
 *  - Pending orders whose customers are not registered in Sectra
 *  - OpenCart bridge table health (oc_customer_id_map, oc_customer_b2b_confirmed)
 *  - Latest SQL export file path
 *  - VE_PEDIDO status distribution for web orders
 */
class SectraStatusCommand extends Command
{
    private const ORIGEM_CLIENTE = '7D4C6FBD-62CF-427F-A0ED-3C06602F05D7';

    public function __construct(
        private readonly ConnectionInterface $erpConnection,
        private readonly Helper $helper,
        private readonly B2BClientRegistration $b2bRegistration,
        private readonly ResourceConnection $resourceConnection
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('erp:sectra:status')
            ->setAliases(['erp:sectra:diagnose'])
            ->setDescription('Status completo da integração Sectra B2B (GR_INTEGRACAOVALIDADOR + oc_* tables + pedidos)')
            ->addOption('orders', 'o', InputOption::VALUE_NONE, 'Mostra pedidos pendentes com clientes não registrados')
            ->addOption('sql', 's', InputOption::VALUE_NONE, 'Gera SQL para registrar clientes pendentes')
            ->addOption('batch-size', null, InputOption::VALUE_OPTIONAL, 'Tamanho do lote para --sql', '200');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->helper->isEnabled()) {
            $output->writeln('<error>Integração ERP está desabilitada.</error>');
            return Command::FAILURE;
        }

        $output->writeln('<info>╔══════════════════════════════════════════════════╗</info>');
        $output->writeln('<info>║     STATUS DA INTEGRAÇÃO SECTRA B2B             ║</info>');
        $output->writeln('<info>╚══════════════════════════════════════════════════╝</info>');
        $output->writeln('');

        $this->showRegistrationStats($output);
        $this->showOrderBridgeStats($output);
        $this->showVePedidoStats($output);

        if ($input->getOption('orders')) {
            $this->showPendingOrdersWithUnregisteredClients($output);
        }

        if ($input->getOption('sql')) {
            $batchSize = max(1, (int) $input->getOption('batch-size'));
            $this->generatePendingSQL($batchSize, $output);
        }

        $this->showSqlFiles($output);

        return Command::SUCCESS;
    }

    private function showRegistrationStats(OutputInterface $output): void
    {
        $output->writeln('<comment>── GR_INTEGRACAOVALIDADOR (Validador Sectra) ──</comment>');

        try {
            $conn = $this->erpConnection;

            $totalRegistered = (int) $conn->fetchColumn(
                "SELECT COUNT(*) FROM GR_INTEGRACAOVALIDADOR WHERE INTEGRACAOORIGEM = :o",
                [':o' => self::ORIGEM_CLIENTE]
            );

            $lastSync = $conn->fetchOne(
                "SELECT TOP 1 DTSINCRONIZACAO FROM GR_INTEGRACAOVALIDADOR
                 WHERE INTEGRACAOORIGEM = :o ORDER BY DTSINCRONIZACAO DESC",
                [':o' => self::ORIGEM_CLIENTE]
            );
        } catch (\Exception $e) {
            $output->writeln(sprintf('<error>  Erro ao consultar ERP: %s</error>', $e->getMessage()));
            $output->writeln('');
            return;
        }

        $magentoConn = $this->resourceConnection->getConnection();

        // Total B2B customers with ERP codes in Magento
        $totalMagento = (int) $magentoConn->fetchOne(
            $magentoConn->select()
                ->from('grupoawamotos_erp_entity_map', ['COUNT(*)'])
                ->where('entity_type = ?', 'customer')
                ->where('erp_code > ?', '0')
        );

        $unregistered = max(0, $totalMagento - $totalRegistered);
        $pct = $totalMagento > 0 ? round(($totalRegistered / $totalMagento) * 100, 1) : 0;

        $output->writeln(sprintf('  Registrados no Sectra : <info>%d</info>', $totalRegistered));
        $output->writeln(sprintf('  Clientes B2B Magento  : <info>%d</info>', $totalMagento));
        $output->writeln(sprintf(
            '  NÃO registrados       : %s',
            $unregistered > 0
                ? sprintf('<error>%d</error> (%.1f%% ainda faltam)', $unregistered, 100 - $pct)
                : '<info>0 (todos registrados!)</info>'
        ));

        if ($lastSync) {
            $dt = $lastSync['DTSINCRONIZACAO'] ?? reset($lastSync) ?? '?';
            $output->writeln(sprintf('  Último sync           : <comment>%s</comment>', $dt));
        }

        $output->writeln('');
    }

    private function showOrderBridgeStats(OutputInterface $output): void
    {
        $output->writeln('<comment>── OpenCart Bridge (oc_* tables) ──</comment>');

        $magentoConn = $this->resourceConnection->getConnection();

        try {
            $ocMap = (int) $magentoConn->fetchOne(
                $magentoConn->select()->from('oc_customer_id_map', ['COUNT(*)'])
            );
            $ocConfirmed = (int) $magentoConn->fetchOne(
                $magentoConn->select()->from('oc_customer_b2b_confirmed', ['COUNT(*)'])
            );
            $output->writeln(sprintf('  oc_customer_id_map       : <info>%d</info> mapeamentos', $ocMap));
            $output->writeln(sprintf('  oc_customer_b2b_confirmed: <info>%d</info> confirmados', $ocConfirmed));
        } catch (\Exception $e) {
            $output->writeln('  <comment>oc_* tables não acessíveis: ' . $e->getMessage() . '</comment>');
        }

        // Orders in oc_order view
        try {
            $ocOrders = (int) $this->erpConnection->fetchColumn('SELECT COUNT(*) FROM oc_order');
            $output->writeln(sprintf('  oc_order (view ERP)      : <info>%d</info> pedidos visíveis', $ocOrders));
        } catch (\Exception $e) {
            $output->writeln('  <comment>oc_order: ' . $e->getMessage() . '</comment>');
        }

        $output->writeln('');
    }

    private function showVePedidoStats(OutputInterface $output): void
    {
        $output->writeln('<comment>── VE_PEDIDO — Status dos Pedidos Web ──</comment>');

        try {
            $rows = $this->erpConnection->query(
                "SELECT STATUS, COUNT(*) AS TOTAL
                 FROM VE_PEDIDO
                 WHERE PEDIDOWEB IS NOT NULL AND PEDIDOWEB <> ''
                 GROUP BY STATUS ORDER BY TOTAL DESC"
            );

            $statusLabels = [
                'W' => 'Aguardando importação Sectra',
                'A' => 'Aberto / Em andamento',
                'P' => 'Em processamento',
                'F' => 'Faturado',
                'E' => 'Encerrado/Completo',
                'C' => 'Cancelado',
                'D' => 'Em devolução/Holded',
                'T' => 'Transferido',
            ];

            $table = new Table($output);
            $table->setHeaders(['STATUS', 'Descrição', 'Total']);
            foreach ($rows as $row) {
                $status = trim($row['STATUS'] ?? '');
                $label = $statusLabels[$status] ?? '(desconhecido)';
                $table->addRow([$status ?: '(vazio)', $label, $row['TOTAL']]);
            }
            $table->render();
        } catch (\Exception $e) {
            $output->writeln('  <comment>VE_PEDIDO inacessível: ' . $e->getMessage() . '</comment>');
        }

        $output->writeln('');
    }

    private function showPendingOrdersWithUnregisteredClients(OutputInterface $output): void
    {
        $output->writeln('<comment>── Pedidos Pendentes com Clientes NÃO Registrados ──</comment>');

        $magentoConn = $this->resourceConnection->getConnection();

        try {
            $registeredCodes = array_fill_keys($this->b2bRegistration->getRegisteredClientCodes(), true);

            // Orders pending sync
            $select = $magentoConn->select()
                ->from(['em' => 'grupoawamotos_erp_entity_map'], ['magento_entity_id', 'erp_code'])
                ->where('em.entity_type = ?', 'order')
                ->join(['o' => 'sales_order'], 'o.entity_id = em.magento_entity_id', ['increment_id', 'customer_id', 'status'])
                ->where("o.state NOT IN ('complete','canceled','closed')")
                ->limit(50);

            $orders = $magentoConn->fetchAll($select);

            if (empty($orders)) {
                $output->writeln('  <info>Nenhum pedido pendente encontrado.</info>');
                $output->writeln('');
                return;
            }

            $table = new Table($output);
            $table->setHeaders(['Pedido', 'Status', 'ERP Cliente', 'Registrado?']);

            $unregisteredOrders = 0;
            foreach ($orders as $order) {
                // Get customer ERP code
                $customerErpSelect = $magentoConn->select()
                    ->from('grupoawamotos_erp_entity_map', ['erp_code'])
                    ->where('entity_type = ?', 'customer')
                    ->where('magento_entity_id = ?', (int) $order['customer_id']);
                $customerErpCode = (int) $magentoConn->fetchOne($customerErpSelect);

                $registered = isset($registeredCodes[$customerErpCode]);
                if (!$registered) {
                    $unregisteredOrders++;
                }

                $table->addRow([
                    $order['increment_id'],
                    $order['status'],
                    $customerErpCode ?: '(sem código)',
                    $registered ? '<info>✓ SIM</info>' : '<error>✗ NÃO</error>',
                ]);
            }

            $table->render();
            $output->writeln(sprintf(
                '  Total: <comment>%d pedidos</comment>, <error>%d com cliente não registrado</error>',
                count($orders),
                $unregisteredOrders
            ));
        } catch (\Exception $e) {
            $output->writeln(sprintf('  <error>Erro: %s</error>', $e->getMessage()));
        }

        $output->writeln('');
    }

    private function generatePendingSQL(int $limit, OutputInterface $output): void
    {
        $output->writeln('<comment>── Gerando SQL para Clientes Não Registrados ──</comment>');

        $magentoConn = $this->resourceConnection->getConnection();

        $select = $magentoConn->select()
            ->from('grupoawamotos_erp_entity_map', ['erp_code'])
            ->where('entity_type = ?', 'customer')
            ->where('erp_code > ?', '0')
            ->limit($limit * 3);

        $allCodes = array_unique(array_map('intval', $magentoConn->fetchCol($select)));
        $allCodes = array_filter($allCodes, fn($c) => $c > 0);

        $registeredCodes = array_fill_keys($this->b2bRegistration->getRegisteredClientCodes(), true);
        $unregistered = array_filter($allCodes, fn($c) => !isset($registeredCodes[$c]));
        $unregistered = array_slice(array_values($unregistered), 0, $limit);

        if (empty($unregistered)) {
            $output->writeln('  <info>Todos os clientes já estão registrados!</info>');
            $output->writeln('');
            return;
        }

        $sql = $this->b2bRegistration->generateRegistrationSQL($unregistered);
        $dir = BP . '/var/log';
        $file = $dir . '/sectra_register_clients_' . date('Ymd_His') . '.sql';
        $latest = $dir . '/sectra_register_clients_latest.sql';
        file_put_contents($file, $sql);
        file_put_contents($latest, $sql);

        $output->writeln(sprintf('  Clientes a registrar: <comment>%d</comment>', count($unregistered)));
        $output->writeln(sprintf('  SQL salvo em       : <info>%s</info>', $file));
        $output->writeln(sprintf('  SQL (latest)       : <info>%s</info>', $latest));
        $output->writeln('');
        $output->writeln('<comment>  Execute no SQL Server Management Studio (usuário com INSERT em GR_INTEGRACAOVALIDADOR):</comment>');
        $output->writeln(sprintf('  <comment>sqlcmd -S localhost -d INDUSTRIAL -i "%s"</comment>', basename($latest)));
        $output->writeln('');
    }

    private function showSqlFiles(OutputInterface $output): void
    {
        $dir = BP . '/var/log';
        $latest = $dir . '/sectra_register_clients_latest.sql';
        $legacyLatest = $dir . '/erp_register_clients_pending_latest.sql';

        $output->writeln('<comment>── Arquivos SQL Pendentes ──</comment>');

        foreach ([$latest, $legacyLatest] as $file) {
            if (!file_exists($file)) {
                continue;
            }
            $size = filesize($file);
            $mtime = date('Y-m-d H:i:s', filemtime($file));
            if ($size > 10) { // Not just the "already registered" comment
                $output->writeln(sprintf(
                    '  <comment>%s</comment> (%d bytes, modificado: %s)',
                    $file,
                    $size,
                    $mtime
                ));
            }
        }

        // Count auto-generated files
        $autoFiles = glob($dir . '/erp_register_clients_auto_*.sql') ?: [];
        if (!empty($autoFiles)) {
            $output->writeln(sprintf(
                '  <comment>%d arquivo(s) auto-gerado(s) em var/log/erp_register_clients_auto_*.sql</comment>',
                count($autoFiles)
            ));
        }

        $output->writeln('');
        $output->writeln('<comment>Comandos úteis:</comment>');
        $output->writeln('  <info>bin/magento erp:sectra:status --orders</info>       # Ver pedidos com clientes não registrados');
        $output->writeln('  <info>bin/magento erp:sectra:status --sql</info>          # Gerar SQL para todos os pendentes');
        $output->writeln('  <info>bin/magento erp:client:register --all --generate-sql --save</info>  # Alternativa');
    }
}
