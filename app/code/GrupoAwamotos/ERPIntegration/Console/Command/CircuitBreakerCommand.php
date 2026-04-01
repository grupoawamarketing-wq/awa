<?php

declare(strict_types=1);

namespace GrupoAwamotos\ERPIntegration\Console\Command;

use GrupoAwamotos\ERPIntegration\Model\CircuitBreaker;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\Table;

class CircuitBreakerCommand extends Command
{
    private CircuitBreaker $circuitBreaker;

    public function __construct(CircuitBreaker $circuitBreaker)
    {
        parent::__construct();
        $this->circuitBreaker = $circuitBreaker;
    }

    protected function configure(): void
    {
        $this->setName('erp:circuit-breaker')
            ->setDescription('Gerencia o Circuit Breaker da integração ERP')
            ->addOption('status', 's', InputOption::VALUE_NONE, 'Mostra status atual do circuit breaker')
            ->addOption('reset', 'r', InputOption::VALUE_NONE, 'Reseta o circuit breaker para estado CLOSED')
            ->addOption('trip', 't', InputOption::VALUE_NONE, 'Força abertura do circuit breaker (OPEN)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $reset = $input->getOption('reset');
        $trip = $input->getOption('trip');

        // Reset circuit breaker
        if ($reset) {
            return $this->resetCircuit($output);
        }

        // Force trip (open) circuit breaker
        if ($trip) {
            return $this->tripCircuit($output);
        }

        // Default: show status
        return $this->showStatus($output);
    }

    private function showStatus(OutputInterface $output): int
    {
        $output->writeln('<info>Circuit Breaker - Status</info>');
        $output->writeln('');

        $stats = $this->circuitBreaker->getStats();

        $stateLabel = match ($stats['state']) {
            'CLOSED' => '<info>CLOSED (Normal)</info>',
            'OPEN' => '<error>OPEN (Bloqueado)</error>',
            'HALF_OPEN' => '<comment>HALF_OPEN (Testando)</comment>',
            default => $stats['state'],
        };

        $table = new Table($output);
        $table->setHeaders(['Propriedade', 'Valor']);
        $table->addRows([
            ['Estado', strip_tags($stateLabel)],
            ['Contagem de falhas', $stats['failure_count'] . ' / ' . $stats['failure_threshold']],
            ['Contagem de sucessos', $stats['success_count'] . ' / ' . $stats['success_threshold']],
            ['Timeout para retry', $stats['open_timeout'] . 's'],
            ['Tempo até half-open', $stats['time_until_half_open'] > 0 ? $stats['time_until_half_open'] . 's' : 'N/A'],
            ['Última falha', $stats['last_failure_time'] ? date('d/m/Y H:i:s', $stats['last_failure_time']) : 'N/A'],
            ['Aberto em', $stats['opened_at'] ? date('d/m/Y H:i:s', $stats['opened_at']) : 'N/A'],
        ]);
        $table->render();

        $output->writeln('');
        $output->writeln("Estado atual: {$stateLabel}");

        if ($stats['state'] === 'OPEN') {
            $output->writeln('');
            $output->writeln('<comment>O circuit breaker está aberto. Requisições ao ERP estão bloqueadas.</comment>');
            $output->writeln('<comment>Use --reset para forçar o fechamento do circuit.</comment>');
        } elseif ($stats['state'] === 'HALF_OPEN') {
            $output->writeln('');
            $output->writeln('<comment>O circuit breaker está testando a recuperação do serviço.</comment>');
        }

        return Command::SUCCESS;
    }

    private function resetCircuit(OutputInterface $output): int
    {
        $output->writeln('<info>Resetando Circuit Breaker...</info>');

        $stateBefore = $this->circuitBreaker->getState();
        $this->circuitBreaker->reset();
        $stateAfter = $this->circuitBreaker->getState();

        $output->writeln('');
        $output->writeln("Estado anterior: <comment>{$stateBefore}</comment>");
        $output->writeln("Estado atual:    <info>{$stateAfter}</info>");
        $output->writeln('');
        $output->writeln('<info>✓ Circuit Breaker resetado com sucesso!</info>');

        return Command::SUCCESS;
    }

    private function tripCircuit(OutputInterface $output): int
    {
        $output->writeln('<comment>Forçando abertura do Circuit Breaker...</comment>');

        // Record multiple failures to trip the circuit
        for ($i = 0; $i < 5; $i++) {
            $this->circuitBreaker->recordFailure(new \Exception('Manual trip via CLI'));
        }

        $state = $this->circuitBreaker->getState();

        $output->writeln('');
        $output->writeln("Estado atual: <error>{$state}</error>");
        $output->writeln('');
        $output->writeln('<comment>Circuit Breaker forçado para estado OPEN.</comment>');
        $output->writeln('<comment>Use --reset para restaurar operação normal.</comment>');

        return Command::SUCCESS;
    }
}
