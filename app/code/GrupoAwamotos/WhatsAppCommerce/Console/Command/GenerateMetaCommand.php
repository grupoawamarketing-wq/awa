<?php

declare(strict_types=1);

namespace GrupoAwamotos\WhatsAppCommerce\Console\Command;

use GrupoAwamotos\WhatsAppCommerce\Cron\MetaDescriptionGenerator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * CLI: php bin/magento awa:whatsapp:generate-meta [--limit=20]
 *
 * Gera meta descriptions com IA para produtos que não possuem.
 * Usa Groq Llama para gerar textos SEO otimizados.
 */
class GenerateMetaCommand extends Command
{
    public function __construct(
        private readonly MetaDescriptionGenerator $generator,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('awa:whatsapp:generate-meta')
            ->setDescription('Gera meta descriptions com IA (Groq) para produtos sem meta description')
            ->addOption('limit', 'l', InputOption::VALUE_OPTIONAL, 'Número de produtos a processar', '20');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $limit = (int) $input->getOption('limit');

        $output->writeln('<info>Gerando meta descriptions com IA...</info>');
        $output->writeln("Limite: {$limit} produtos");
        $output->writeln('');

        $result = $this->generator->generateBatch($limit);

        if (!$result['success']) {
            $output->writeln('<error>' . ($result['message'] ?? 'Erro desconhecido') . '</error>');
            return Command::FAILURE;
        }

        if (($result['generated'] ?? 0) === 0) {
            $output->writeln('<comment>' . ($result['message'] ?? 'Nenhum produto processado') . '</comment>');
            return Command::SUCCESS;
        }

        $output->writeln('<info>Resultados:</info>');
        $output->writeln("  Total processados: {$result['total']}");
        $output->writeln("  Gerados: <info>{$result['generated']}</info>");
        $output->writeln("  Falhas: <error>{$result['failed']}</error>");
        $output->writeln('');

        if (!empty($result['results'])) {
            $output->writeln('<info>Meta descriptions geradas:</info>');
            foreach ($result['results'] as $item) {
                $output->writeln("  SKU <comment>{$item['sku']}</comment>:");
                $output->writeln("    {$item['meta']}");
            }
        }

        return Command::SUCCESS;
    }
}
