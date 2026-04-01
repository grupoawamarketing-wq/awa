<?php

declare(strict_types=1);

namespace GrupoAwamotos\AbandonedCart\Console\Command;

use GrupoAwamotos\AbandonedCart\Cron\ProcessAbandonedCarts;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ProcessCommand extends Command
{
    private ProcessAbandonedCarts $processor;

    public function __construct(ProcessAbandonedCarts $processor)
    {
        $this->processor = $processor;
        parent::__construct();
    }

    protected function configure()
    {
        $this->setName('abandonedcart:process')
            ->setDescription('Processa carrinhos abandonados e identifica novos registros');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('<info>Processando carrinhos abandonados...</info>');

        try {
            $this->processor->execute();
            $output->writeln('<info>Processamento concluído com sucesso!</info>');
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $output->writeln('<error>Erro: ' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }
    }
}
