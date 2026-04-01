<?php

declare(strict_types=1);

namespace GrupoAwamotos\ERPIntegration\Console\Command;

use GrupoAwamotos\ERPIntegration\Model\StockSync;
use GrupoAwamotos\ERPIntegration\Helper\Data as Helper;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class SyncStockCommand extends Command
{
    private StockSync $stockSync;
    private Helper $helper;

    public function __construct(
        StockSync $stockSync,
        Helper $helper
    ) {
        parent::__construct();
        $this->stockSync = $stockSync;
        $this->helper = $helper;
    }

    protected function configure(): void
    {
        $this->setName('erp:sync:stock')
            ->setDescription('Sincroniza estoque do ERP para o Magento')
            ->addOption('sku', 's', InputOption::VALUE_REQUIRED, 'Sincroniza estoque de um SKU específico')
            ->addOption('clear-cache', 'c', InputOption::VALUE_NONE, 'Limpa todo o cache de estoque antes');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('<info>Sincronização de Estoque ERP</info>');
        $output->writeln('');

        if (!$this->helper->isEnabled()) {
            $output->writeln('<error>Integração ERP está desabilitada.</error>');
            return Command::FAILURE;
        }

        if (!$this->helper->isStockSyncEnabled()) {
            $output->writeln('<error>Sincronização de estoque está desabilitada.</error>');
            return Command::FAILURE;
        }

        $sku = $input->getOption('sku');
        $clearCache = $input->getOption('clear-cache');

        $output->writeln("Filial configurada: <comment>{$this->helper->getStockFilial()}</comment>");
        $output->writeln("Cache TTL: <comment>{$this->helper->getStockCacheTtl()}s</comment>");
        $output->writeln('');

        // Clear cache if requested
        if ($clearCache) {
            $output->writeln('<comment>Limpando cache de estoque...</comment>');
            $this->stockSync->invalidateAllCache();
            $output->writeln('<info>Cache limpo!</info>');
            $output->writeln('');
        }

        // Single SKU sync
        if ($sku) {
            return $this->syncSingleSku($sku, $output);
        }

        // Full sync
        return $this->syncAll($output);
    }

    private function syncSingleSku(string $sku, OutputInterface $output): int
    {
        $output->writeln("Consultando estoque para SKU: <comment>{$sku}</comment>");
        $output->writeln('');

        // Get stock data
        $stockData = $this->stockSync->getStockBySku($sku);

        if ($stockData === null) {
            $output->writeln('<error>Nenhum registro de estoque encontrado no ERP para este SKU.</error>');
            return Command::FAILURE;
        }

        $output->writeln('<info>Dados do ERP:</info>');
        $output->writeln("  Quantidade: {$stockData['qty']}");
        $output->writeln("  Custo médio: R$ " . number_format($stockData['cost'], 2, ',', '.'));
        $output->writeln("  Data: {$stockData['date']}");
        $output->writeln('');

        // Sync
        $result = $this->stockSync->syncBySku($sku);

        if ($result) {
            $output->writeln('<info>✓ Estoque sincronizado com sucesso!</info>');
            return Command::SUCCESS;
        }

        $output->writeln('<error>✗ Falha ao sincronizar estoque. Verifique se o produto existe no Magento.</error>');
        return Command::FAILURE;
    }

    private function syncAll(OutputInterface $output): int
    {
        $output->writeln('<info>Iniciando sincronização completa de estoque...</info>');
        $output->writeln('');

        $startTime = microtime(true);
        $result = $this->stockSync->syncAll();
        $totalTime = round(microtime(true) - $startTime, 2);

        $output->writeln('<info>Sincronização concluída!</info>');
        $output->writeln('');
        $output->writeln("  Total ERP:       <comment>{$result['total_erp_records']}</comment>");
        $output->writeln("  Atualizados:     <info>{$result['updated']}</info>");
        $output->writeln("  Sem alteração:   {$result['unchanged']}");
        $output->writeln("  Não encontrados: <comment>{$result['not_found']}</comment>");
        $output->writeln("  Ignorados:       {$result['skipped']}");
        $output->writeln("  Erros:           " . ($result['errors'] > 0 ? "<error>{$result['errors']}</error>" : "0"));
        $output->writeln("  Tempo:           {$totalTime}s ({$result['execution_time']}ms)");

        if ($result['not_found'] > 0) {
            $output->writeln('');
            $output->writeln('<comment>Nota: Produtos "não encontrados" existem no ERP mas não no Magento.</comment>');
        }

        return $result['errors'] > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
