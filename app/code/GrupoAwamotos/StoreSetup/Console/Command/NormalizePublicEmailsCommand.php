<?php

declare(strict_types=1);

namespace GrupoAwamotos\StoreSetup\Console\Command;

use GrupoAwamotos\StoreSetup\Model\PublicEmailNormalizer;
use Magento\Framework\App\State;
use Magento\Framework\Exception\LocalizedException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class NormalizePublicEmailsCommand extends Command
{
    public function __construct(
        private readonly State $appState,
        private readonly PublicEmailNormalizer $publicEmailNormalizer,
        ?string $name = null
    ) {
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this->setName('grupoawamotos:content:normalize-public-emails')
            ->setDescription('Padroniza e-mails públicos do storefront para o domínio awamotos.com.br.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->ensureAreaCode();

        $summary = $this->publicEmailNormalizer->normalizeAll();

        $output->writeln('<info>Normalização de e-mails públicos concluída.</info>');
        $output->writeln(sprintf(' - Blocos atualizados: %d', $summary['blocks']));
        $output->writeln(sprintf(' - Páginas atualizadas: %d', $summary['pages']));
        $output->writeln(sprintf(' - Posts atualizados: %d', $summary['posts']));
        $output->writeln(sprintf(' - Ocorrências normalizadas: %d', $summary['replaced']));

        if ($summary['failed_blocks'] !== []) {
            $output->writeln(sprintf(' - Blocos com falha: %d', count($summary['failed_blocks'])));
            $output->writeln('   ' . implode(', ', $summary['failed_blocks']));
        }

        if ($summary['failed_pages'] !== []) {
            $output->writeln(sprintf(' - Páginas com falha: %d', count($summary['failed_pages'])));
            $output->writeln('   ' . implode(', ', $summary['failed_pages']));
        }

        if ($summary['failed_posts'] !== []) {
            $output->writeln(sprintf(' - Posts com falha: %d', count($summary['failed_posts'])));
            $output->writeln('   ' . implode(', ', $summary['failed_posts']));
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
