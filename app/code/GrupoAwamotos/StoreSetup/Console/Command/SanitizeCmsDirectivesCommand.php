<?php

declare(strict_types=1);

namespace GrupoAwamotos\StoreSetup\Console\Command;

use GrupoAwamotos\StoreSetup\Model\CmsDirectiveSanitizer;
use Magento\Framework\App\State;
use Magento\Framework\Exception\LocalizedException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SanitizeCmsDirectivesCommand extends Command
{
    public function __construct(
        private readonly State $appState,
        private readonly CmsDirectiveSanitizer $cmsDirectiveSanitizer,
        ?string $name = null
    ) {
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this->setName('grupoawamotos:cms:sanitize-directives')
            ->setDescription('Normaliza diretivas CMS inválidas com aspas duplas/escapadas em blocos e páginas.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->ensureAreaCode();

        $summary = $this->cmsDirectiveSanitizer->sanitizeAll();

        $output->writeln('<info>Sanitização CMS concluída.</info>');
        $output->writeln(sprintf(' - Blocos atualizados: %d', $summary['blocks']));
        $output->writeln(sprintf(' - Páginas atualizadas: %d', $summary['pages']));

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
