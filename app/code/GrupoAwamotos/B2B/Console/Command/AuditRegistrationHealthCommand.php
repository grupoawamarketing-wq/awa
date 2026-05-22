<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Console\Command;

use GrupoAwamotos\B2B\Model\Registration\RegistrationHealthAuditService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class AuditRegistrationHealthCommand extends Command
{
    public function __construct(
        private readonly RegistrationHealthAuditService $healthAuditService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('b2b:registration:audit-health')
            ->setDescription('Auditoria de saúde contínua dos cadastros B2B (read-only)')
            ->addOption(
                'export',
                null,
                InputOption::VALUE_OPTIONAL,
                'Exporta relatório de saúde (ex.: var/export/b2b_registration_health.csv)'
            )
            ->addOption(
                'export-commercial',
                null,
                InputOption::VALUE_OPTIONAL,
                'Exporta pendências comerciais (ex.: var/export/b2b_clientes_pendentes_comercial.csv)'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $summary = $this->healthAuditService->collectSummary();

        $output->writeln('');
        $output->writeln('<info>=== Saúde cadastral B2B (read-only) ===</info>');
        $output->writeln(sprintf('Total com CNPJ:                         %d', $summary['total_with_cnpj']));
        $output->writeln(sprintf('Sem b2b_phone:                          %d', $summary['no_b2b_phone']));
        $output->writeln(sprintf('Sem b2b_razao_social:                   %d', $summary['no_b2b_razao_social']));
        $output->writeln(sprintf('Sem erp_customer_sync_status:           %d', $summary['no_erp_customer_sync_status']));
        $output->writeln(sprintf('Sem b2b_origin_host:                    %d', $summary['no_b2b_origin_host']));
        $output->writeln(sprintf('Sem b2b_registration_campaign:          %d', $summary['no_b2b_registration_campaign']));
        $output->writeln(sprintf('Tel. no endereço sem b2b_phone:         %d', $summary['phone_in_address_not_b2b_phone']));
        $output->writeln(sprintf('Pendentes correção manual (comercial):  %d', $summary['manual_correction_pending']));
        $output->writeln(sprintf('Contas teste/QA (sem telefone):         %d', $summary['test_qa_accounts']));
        $output->writeln('');

        $exportPath = $input->getOption('export');
        if ($exportPath !== null && $exportPath !== '') {
            try {
                $export = $this->healthAuditService->exportHealthCsv((string) $exportPath);
                $output->writeln(sprintf('<info>CSV saúde: %s (%d linhas de detalhe)</info>', $export['path'], $export['rows']));
            } catch (\Throwable $e) {
                $output->writeln('<error>Falha export saúde: ' . $e->getMessage() . '</error>');

                return Command::FAILURE;
            }
        }

        $commercialPath = $input->getOption('export-commercial');
        if ($commercialPath !== null && $commercialPath !== '') {
            try {
                $export = $this->healthAuditService->exportCommercialPendingCsv((string) $commercialPath);
                $output->writeln(sprintf(
                    '<info>CSV comercial: %s (%d clientes)</info>',
                    $export['path'],
                    $export['rows']
                ));
            } catch (\Throwable $e) {
                $output->writeln('<error>Falha export comercial: ' . $e->getMessage() . '</error>');

                return Command::FAILURE;
            }
        }

        return Command::SUCCESS;
    }
}
