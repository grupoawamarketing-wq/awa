<?php

declare(strict_types=1);

namespace GrupoAwamotos\ERPIntegration\Console\Command;

use GrupoAwamotos\ERPIntegration\Model\CategorySync;
use GrupoAwamotos\ERPIntegration\Helper\Data as Helper;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class SyncCategoriesCommand extends Command
{
    private CategorySync $categorySync;
    private Helper $helper;

    public function __construct(
        CategorySync $categorySync,
        Helper $helper
    ) {
        parent::__construct();
        $this->categorySync = $categorySync;
        $this->helper = $helper;
    }

    protected function configure(): void
    {
        $this->setName('erp:sync:categories')
            ->setDescription('Sincroniza categorias do ERP (MT_GRUPOCOMERCIAL) para o Magento')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Apenas simula, nao faz alteracoes')
            ->addOption('limit', 'l', InputOption::VALUE_REQUIRED, 'Limita quantidade de categorias', 0);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('<info>Sincronizacao de Categorias ERP</info>');
        $output->writeln('');

        if (!$this->helper->isEnabled()) {
            $output->writeln('<error>Integracao ERP esta desabilitada.</error>');
            return Command::FAILURE;
        }

        if (!$this->helper->isCategorySyncEnabled()) {
            $output->writeln('<error>Sincronizacao de categorias esta desabilitada.</error>');
            return Command::FAILURE;
        }

        $dryRun = $input->getOption('dry-run');
        $limit = (int) $input->getOption('limit');

        if ($dryRun) {
            $output->writeln('<comment>Modo DRY-RUN: nenhuma alteracao sera feita</comment>');
            $output->writeln('');
            return $this->dryRun($output, $limit);
        }

        return $this->syncAll($output);
    }

    private function dryRun(OutputInterface $output, int $limit): int
    {
        $totalCount = $this->categorySync->getErpCategoryCount();
        $output->writeln("Total de categorias no ERP: <comment>{$totalCount}</comment>");
        $output->writeln('');

        $categories = $this->categorySync->getErpCategories();

        if (empty($categories)) {
            $output->writeln('<comment>Nenhuma categoria encontrada no ERP.</comment>');
            return Command::SUCCESS;
        }

        $showLimit = $limit > 0 ? min($limit, count($categories)) : min(20, count($categories));
        $output->writeln("<comment>Primeiras {$showLimit} categorias que seriam sincronizadas:</comment>");
        $output->writeln('');
        $output->writeln(sprintf('  %-8s %-12s %-12s %s', 'CODIGO', 'NIVEL', 'NIVELPAI', 'DESCRICAO'));
        $output->writeln('  ' . str_repeat('-', 70));

        $count = 0;
        foreach ($categories as $cat) {
            if ($count >= $showLimit) {
                break;
            }

            $output->writeln(sprintf(
                '  %-8s %-12s %-12s %s',
                $cat['CODIGO'] ?? '?',
                $cat['NIVEL'] ?? '-',
                $cat['NIVELPAI'] ?? '-',
                substr($cat['DESCRICAO'] ?? '', 0, 40)
            ));
            $count++;
        }

        if (count($categories) > $showLimit) {
            $output->writeln(sprintf('  ... e mais %d categorias', count($categories) - $showLimit));
        }

        return Command::SUCCESS;
    }

    private function syncAll(OutputInterface $output): int
    {
        $totalCount = $this->categorySync->getErpCategoryCount();
        $output->writeln("Total de categorias no ERP: <comment>{$totalCount}</comment>");
        $output->writeln('');
        $output->writeln('<info>Iniciando sincronizacao...</info>');
        $output->writeln('');

        $startTime = microtime(true);
        $result = $this->categorySync->syncAll();
        $totalTime = round(microtime(true) - $startTime, 2);

        $output->writeln('');
        $output->writeln('<info>Sincronizacao concluida!</info>');
        $output->writeln('');
        $output->writeln("  Criadas:      <info>{$result['created']}</info>");
        $output->writeln("  Atualizadas:  <info>{$result['updated']}</info>");
        $output->writeln("  Desativadas:  <comment>{$result['deactivated']}</comment>");
        $output->writeln("  Ignoradas:    <comment>{$result['skipped']}</comment>");
        $output->writeln("  Erros:        " . ($result['errors'] > 0 ? "<error>{$result['errors']}</error>" : "0"));
        $output->writeln("  Tempo:        {$totalTime}s");

        return $result['errors'] > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
