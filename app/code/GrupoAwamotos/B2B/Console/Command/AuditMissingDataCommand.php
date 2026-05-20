<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Console\Command;

use GrupoAwamotos\B2B\Model\Registration\RegistrationMissingDataAuditService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class AuditMissingDataCommand extends Command
{
    private const DEFAULT_SAMPLE_LIMIT = 50;

    public function __construct(
        private readonly RegistrationMissingDataAuditService $auditService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('b2b:registration:audit-missing-data')
            ->setDescription('Auditoria read-only de lacunas cadastrais B2B (telefone, razão social, ERP)')
            ->addOption(
                'limit',
                null,
                InputOption::VALUE_OPTIONAL,
                'Quantidade de clientes problemáticos exibidos na amostra',
                (string) self::DEFAULT_SAMPLE_LIMIT
            )
            ->addOption('from-id', null, InputOption::VALUE_OPTIONAL, 'entity_id mínimo na amostra/export')
            ->addOption('to-id', null, InputOption::VALUE_OPTIONAL, 'entity_id máximo na amostra/export')
            ->addOption(
                'export',
                null,
                InputOption::VALUE_OPTIONAL,
                'Exporta todos os clientes problemáticos para CSV (ex.: var/export/b2b_missing_data.csv)'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $sampleLimit = max(1, (int) $input->getOption('limit'));
        $exportPath = $input->getOption('export');
        $fromId = $input->getOption('from-id') !== null ? (int) $input->getOption('from-id') : null;
        $toId = $input->getOption('to-id') !== null ? (int) $input->getOption('to-id') : null;

        $summary = $this->auditService->collectSummary();

        $output->writeln('');
        $output->writeln('<info>=== Auditoria cadastral B2B (somente leitura) ===</info>');
        $output->writeln('');
        $output->writeln('<comment>Fontes consultadas:</comment>');
        $output->writeln('  customer_entity + customer_entity_varchar (EAV varchar)');
        $output->writeln('  customer_address_entity (telephone, company)');
        $output->writeln('  b2b_cnpj, b2b_phone, b2b_razao_social, b2b_origin_host, erp_customer_sync_status');
        $output->writeln('');
        $output->writeln('<info>Totais globais (clientes com CNPJ preenchido)</info>');
        $output->writeln(sprintf('Total com CNPJ:                         %d', $summary['total_with_cnpj']));
        $output->writeln(sprintf('Sem telefone (b2b_phone):               %d', $summary['no_phone']));
        $output->writeln(sprintf('Sem razão social (b2b_razao_social):    %d', $summary['no_razao_social']));
        $output->writeln(sprintf('Sem telefone no endereço:               %d', $summary['no_address_phone']));
        $output->writeln(sprintf('Sem company no endereço:                %d', $summary['no_address_company']));
        $output->writeln(sprintf('Pode copiar telefone do endereço:       %d', $summary['can_copy_phone']));
        $output->writeln(sprintf('Pode copiar razão social do endereço:   %d', $summary['can_copy_razao_from_address']));
        $output->writeln(sprintf('Pode backfill razão via OC legacy:      %d', $summary['can_backfill_razao_from_oc_legacy'] ?? 0));
        $output->writeln(sprintf('Com legacy_unknown (b2b_origin_host):   %d', $summary['legacy_unknown']));
        $output->writeln(sprintf('erp_customer_sync_status vazio:         %d', $summary['erp_status_empty']));
        $output->writeln(sprintf('Pendentes de backfill atribuição:       %d', $summary['pending_backfill']));
        $output->writeln(sprintf('Problemáticos (tel/razão/ERP):          %d', $summary['problematic_total']));
        $output->writeln('');

        $sample = $this->auditService->fetchProblematicCustomers($sampleLimit, $fromId, $toId);

        $output->writeln(sprintf(
            '<info>Amostra — primeiros %d clientes problemáticos (entity_id ASC)</info>',
            min($sampleLimit, count($sample))
        ));

        if ($fromId !== null || $toId !== null) {
            $output->writeln(sprintf(
                'Faixa entity_id: %s → %s',
                $fromId !== null ? (string) $fromId : '*',
                $toId !== null ? (string) $toId : '*'
            ));
        }

        if ($sample === []) {
            $output->writeln('<comment>Nenhum cliente problemático encontrado.</comment>');
        } else {
            $table = new Table($output);
            $table->setHeaders([
                'entity_id',
                'email',
                'cnpj',
                'b2b_phone',
                'tel. endereço',
                'b2b_razao_social',
                'company end.',
                'origin_host',
                'status ERP',
                'problemas',
            ]);

            foreach ($sample as $row) {
                $table->addRow([
                    (string) $row['entity_id'],
                    $row['email'],
                    $row['cnpj'] !== '' ? $row['cnpj'] : '—',
                    $row['b2b_phone'] !== '' ? $row['b2b_phone'] : '—',
                    $row['address_phone'] !== '' ? $row['address_phone'] : '—',
                    $row['b2b_razao_social'] !== '' ? mb_substr($row['b2b_razao_social'], 0, 30) : '—',
                    $row['address_company'] !== '' ? mb_substr($row['address_company'], 0, 25) : '—',
                    $row['b2b_origin_host'] !== '' ? $row['b2b_origin_host'] : '—',
                    $row['erp_status'] !== '' ? $row['erp_status'] : '—',
                    $row['issues'],
                ]);
            }

            $table->render();

            if ($summary['problematic_total'] > count($sample)) {
                $output->writeln(sprintf(
                    '<comment>... +%d clientes problemáticos não exibidos (use --export para lista completa)</comment>',
                    $summary['problematic_total'] - count($sample)
                ));
            }
        }

        if ($exportPath !== null && $exportPath !== '') {
            $output->writeln('');
            try {
                $export = $this->auditService->exportProblematicCustomersCsv((string) $exportPath, $fromId, $toId);
                $output->writeln(sprintf(
                    '<info>CSV exportado: %s (%d linhas de dados)</info>',
                    $export['path'],
                    $export['rows']
                ));
            } catch (\Throwable $e) {
                $output->writeln('<error>Falha ao exportar CSV: ' . $e->getMessage() . '</error>');

                return Command::FAILURE;
            }
        }

        $output->writeln('');

        return Command::SUCCESS;
    }
}
