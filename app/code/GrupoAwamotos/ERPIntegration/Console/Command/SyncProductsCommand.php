<?php
declare(strict_types=1);

namespace GrupoAwamotos\ERPIntegration\Console\Command;

use GrupoAwamotos\ERPIntegration\Model\ProductSync;
use GrupoAwamotos\ERPIntegration\Helper\Data as Helper;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\ProgressBar;

class SyncProductsCommand extends Command
{
    private ProductSync $productSync;
    private Helper $helper;

    public function __construct(
        ProductSync $productSync,
        Helper $helper
    ) {
        parent::__construct();
        $this->productSync = $productSync;
        $this->helper = $helper;
    }

    protected function configure(): void
    {
        $this->setName('erp:sync:products')
            ->setDescription('Sincroniza produtos do ERP para o Magento')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Apenas simula, não faz alterações')
            ->addOption('limit', 'l', InputOption::VALUE_REQUIRED, 'Limita quantidade de produtos', 0)
            ->addOption('sku', 's', InputOption::VALUE_REQUIRED, 'Sincroniza apenas um SKU específico');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('<info>Sincronização de Produtos ERP</info>');
        $output->writeln('');

        if (!$this->helper->isEnabled()) {
            $output->writeln('<error>Integração ERP está desabilitada.</error>');
            return Command::FAILURE;
        }

        if (!$this->helper->isProductSyncEnabled()) {
            $output->writeln('<error>Sincronização de produtos está desabilitada.</error>');
            return Command::FAILURE;
        }

        $dryRun = $input->getOption('dry-run');
        $limit = (int) $input->getOption('limit');
        $sku = $input->getOption('sku');

        if ($dryRun) {
            $output->writeln('<comment>Modo DRY-RUN: nenhuma alteração será feita</comment>');
            $output->writeln('');
        }

        // Single SKU sync
        if ($sku) {
            return $this->syncSingleSku($sku, $output, $dryRun);
        }

        // Full sync
        return $this->syncAll($output, $dryRun, $limit);
    }

    private function syncSingleSku(string $sku, OutputInterface $output, bool $dryRun): int
    {
        $output->writeln("Buscando produto SKU: <comment>{$sku}</comment>");

        $products = $this->productSync->getErpProducts(1, 0);

        // Search for specific SKU
        $found = false;
        foreach ($this->productSync->getErpProducts(10000, 0) as $product) {
            if (trim($product['CODIGO']) === $sku) {
                $found = true;
                $output->writeln('');
                $output->writeln('<info>Produto encontrado no ERP:</info>');
                $output->writeln("  SKU: {$product['CODIGO']}");
                $output->writeln("  Nome: {$product['DESCRICAO']}");
                $output->writeln("  Preço: R$ " . number_format((float)($product['VLRVENDA'] ?? 0), 2, ',', '.'));
                $output->writeln("  Ativo: " . (($product['CCKATIVO'] ?? 'N') === 'S' ? 'Sim' : 'Não'));

                if (!$dryRun) {
                    $result = $this->productSync->syncBySku($sku);
                    $output->writeln('');
                    $output->writeln($result ? '<info>✓ Produto sincronizado com sucesso!</info>' : '<error>✗ Falha ao sincronizar produto</error>');
                }
                break;
            }
        }

        if (!$found) {
            $output->writeln('<error>Produto não encontrado no ERP.</error>');
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    private function syncAll(OutputInterface $output, bool $dryRun, int $limit): int
    {
        $totalCount = $this->productSync->getErpProductCount();

        $output->writeln("Total de produtos no ERP: <comment>{$totalCount}</comment>");

        if ($limit > 0) {
            $output->writeln("Limite definido: <comment>{$limit}</comment>");
        }

        if ($dryRun) {
            $output->writeln('');
            $output->writeln('<comment>Primeiros 10 produtos que seriam sincronizados:</comment>');

            $products = $this->productSync->getErpProducts(10, 0);
            foreach ($products as $product) {
                $output->writeln(sprintf(
                    '  [%s] %s - R$ %.2f',
                    $product['CODIGO'],
                    substr($product['DESCRICAO'] ?? '', 0, 50),
                    (float)($product['VLRVENDA'] ?? 0)
                ));
            }

            return Command::SUCCESS;
        }

        $output->writeln('');
        $output->writeln('<info>Iniciando sincronização...</info>');
        $output->writeln('');

        $startTime = microtime(true);
        $result = $this->productSync->syncAll();
        $totalTime = round(microtime(true) - $startTime, 2);

        $output->writeln('');
        $output->writeln('<info>Sincronização concluída!</info>');
        $output->writeln('');
        $output->writeln("  Criados:     <info>{$result['created']}</info>");
        $output->writeln("  Atualizados: <info>{$result['updated']}</info>");
        $output->writeln("  Desativados: <comment>{$result['deactivated']}</comment>");
        $output->writeln("  Ignorados:   <comment>{$result['skipped']}</comment>");
        $output->writeln("  Erros:       " . ($result['errors'] > 0 ? "<error>{$result['errors']}</error>" : "0"));
        $output->writeln("  Batches:     {$result['batches_processed']}");
        $output->writeln("  Tempo:       {$totalTime}s");
        $output->writeln('');
        $output->writeln('<comment>💡 Nota: Este comando sincroniza apenas dados de texto (nome, preço, status, categorias).</comment>');
        $output->writeln('<comment>   Para sincronizar IMAGENS, execute: php bin/magento erp:sync:images</comment>');
        $output->writeln('<comment>   Ou use: make erp-sync-images</comment>');

        return $result['errors'] > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
