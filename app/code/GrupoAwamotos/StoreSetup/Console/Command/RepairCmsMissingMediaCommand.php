<?php

declare(strict_types=1);

namespace GrupoAwamotos\StoreSetup\Console\Command;

use GrupoAwamotos\StoreSetup\Model\CmsMissingMediaRepairer;
use Magento\Framework\App\State;
use Magento\Framework\Exception\LocalizedException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class RepairCmsMissingMediaCommand extends Command
{
    public function __construct(
        private readonly State $appState,
        private readonly CmsMissingMediaRepairer $cmsMissingMediaRepairer,
        ?string $name = null
    ) {
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this->setName('grupoawamotos:cms:repair-missing-media')
            ->setDescription('Repara referências de mídia inexistente em blocos CMS legados do tema demo.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->ensureAreaCode();

        $summary = $this->cmsMissingMediaRepairer->repairAll();

        $output->writeln('<info>Reparo de mídia CMS concluído.</info>');
        $output->writeln(sprintf(' - Blocos atualizados: %d', $summary['blocks']));
        $output->writeln(sprintf(' - Páginas atualizadas: %d', $summary['pages']));
        $output->writeln(sprintf(' - Referências substituídas: %d', $summary['replaced']));

        if ($summary['failed_blocks'] !== []) {
            $output->writeln(sprintf(' - Blocos com falha: %d', count($summary['failed_blocks'])));
            $output->writeln('   ' . implode(', ', $summary['failed_blocks']));
        }

        if ($summary['failed_pages'] !== []) {
            $output->writeln(sprintf(' - Páginas com falha: %d', count($summary['failed_pages'])));
            $output->writeln('   ' . implode(', ', $summary['failed_pages']));
        }

        return Command::SUCCESS;
    }

    private function ensureAreaCode(): void
    {
        try {
            $this->appState->getAreaCode();
        } catch (LocalizedException) {
            $this->appState->setAreaCode('adminhtml');
        }
    }
}
