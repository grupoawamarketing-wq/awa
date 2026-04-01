<?php

declare(strict_types=1);

namespace GrupoAwamotos\ERPIntegration\Console\Command;

use GrupoAwamotos\ERPIntegration\Model\CustomerSync;
use GrupoAwamotos\ERPIntegration\Helper\Data as Helper;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class SyncCustomersCommand extends Command
{
    private CustomerSync $customerSync;
    private Helper $helper;

    public function __construct(
        CustomerSync $customerSync,
        Helper $helper
    ) {
        parent::__construct();
        $this->customerSync = $customerSync;
        $this->helper = $helper;
    }

    protected function configure(): void
    {
        $this->setName('erp:sync:customers')
            ->setDescription('Sincroniza clientes do ERP para o Magento')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Apenas simula, não faz alterações')
            ->addOption('limit', 'l', InputOption::VALUE_REQUIRED, 'Limita quantidade de clientes', 0)
            ->addOption('cnpj', null, InputOption::VALUE_REQUIRED, 'Sincroniza apenas um cliente por CNPJ/CPF')
            ->addOption('code', 'c', InputOption::VALUE_REQUIRED, 'Sincroniza apenas um cliente por código ERP');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('<info>Sincronização de Clientes ERP</info>');
        $output->writeln('');

        if (!$this->helper->isEnabled()) {
            $output->writeln('<error>Integração ERP está desabilitada.</error>');
            return Command::FAILURE;
        }

        if (!$this->helper->isCustomerSyncEnabled()) {
            $output->writeln('<error>Sincronização de clientes está desabilitada.</error>');
            return Command::FAILURE;
        }

        $dryRun = $input->getOption('dry-run');
        $limit = (int) $input->getOption('limit');
        $cnpj = $input->getOption('cnpj');
        $code = $input->getOption('code');

        if ($dryRun) {
            $output->writeln('<comment>Modo DRY-RUN: nenhuma alteração será feita</comment>');
            $output->writeln('');
        }

        // Single customer sync by code
        if ($code) {
            return $this->syncByCode($code, $output, $dryRun);
        }

        // Single customer sync by CNPJ
        if ($cnpj) {
            return $this->syncByCnpj($cnpj, $output, $dryRun);
        }

        // Full sync
        return $this->syncAll($output, $dryRun, $limit);
    }

    private function syncByCode(string $code, OutputInterface $output, bool $dryRun): int
    {
        $output->writeln("Buscando cliente código: <comment>{$code}</comment>");

        try {
            $customer = $this->customerSync->getErpCustomerByCode((int)$code);

            if (!$customer) {
                $output->writeln('<error>Cliente não encontrado no ERP.</error>');
                return Command::FAILURE;
            }

            $this->displayCustomerInfo($customer, $output);

            if (!$dryRun) {
                $result = $this->customerSync->syncByCode($code);
                $output->writeln('');
                $output->writeln($result ? '<info>✓ Cliente sincronizado com sucesso!</info>' : '<error>✗ Falha ao sincronizar cliente</error>');
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $output->writeln('<error>Erro: ' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }
    }

    private function syncByCnpj(string $cnpj, OutputInterface $output, bool $dryRun): int
    {
        $output->writeln("Buscando cliente CNPJ/CPF: <comment>{$cnpj}</comment>");

        try {
            $customer = $this->customerSync->getErpCustomerByCnpj($cnpj);

            if (!$customer) {
                $output->writeln('<error>Cliente não encontrado no ERP.</error>');
                return Command::FAILURE;
            }

            $this->displayCustomerInfo($customer, $output);

            if (!$dryRun) {
                $result = $this->customerSync->syncByCode((string)$customer['CODIGO']);
                $output->writeln('');
                $output->writeln($result ? '<info>✓ Cliente sincronizado com sucesso!</info>' : '<error>✗ Falha ao sincronizar cliente</error>');
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $output->writeln('<error>Erro: ' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }
    }

    private function displayCustomerInfo(array $customer, OutputInterface $output): void
    {
        $output->writeln('');
        $output->writeln('<info>Cliente encontrado no ERP:</info>');
        $output->writeln("  Código:   {$customer['CODIGO']}");
        $output->writeln("  Razão:    " . ($customer['RAZAO'] ?? 'N/A'));
        $output->writeln("  Fantasia: " . ($customer['FANTASIA'] ?? 'N/A'));
        $output->writeln("  CNPJ/CPF: " . ($customer['CGC'] ?? $customer['CPF'] ?? 'N/A'));
        $output->writeln("  Email:    " . ($customer['EMAIL'] ?? 'N/A'));
        $output->writeln("  Cidade:   " . ($customer['CIDADE'] ?? 'N/A') . '/' . ($customer['UF'] ?? 'N/A'));
        $output->writeln("  Tipo:     " . (($customer['CKPESSOA'] ?? 'J') === 'J' ? 'Jurídica' : 'Física'));
    }

    private function syncAll(OutputInterface $output, bool $dryRun, int $limit): int
    {
        try {
            $totalCount = $this->customerSync->getErpCustomerCount();

            $output->writeln("Total de clientes no ERP: <comment>{$totalCount}</comment>");

            if ($limit > 0) {
                $output->writeln("Limite definido: <comment>{$limit}</comment>");
            }

            if ($dryRun) {
                $output->writeln('');
                $output->writeln('<comment>Primeiros 10 clientes que seriam sincronizados:</comment>');

                $customers = $this->customerSync->getErpCustomers(10, 0);
                foreach ($customers as $customer) {
                    $output->writeln(sprintf(
                        '  [%s] %s - %s',
                        $customer['CODIGO'],
                        substr($customer['RAZAO'] ?? '', 0, 40),
                        $customer['CIDADE'] ?? 'N/A'
                    ));
                }

                return Command::SUCCESS;
            }

            $output->writeln('');
            $output->writeln('<info>Iniciando sincronização...</info>');
            $output->writeln('');

            $startTime = microtime(true);
            $result = $this->customerSync->syncAll($limit > 0 ? $limit : null);
            $totalTime = round(microtime(true) - $startTime, 2);

            $output->writeln('');
            $output->writeln('<info>Sincronização concluída!</info>');
            $output->writeln('');
            $output->writeln("  Criados:     <info>{$result['created']}</info>");
            $output->writeln("  Atualizados: <info>{$result['updated']}</info>");
            $output->writeln("  Ignorados:   <comment>{$result['skipped']}</comment>");
            $output->writeln("  Erros:       " . ($result['errors'] > 0 ? "<error>{$result['errors']}</error>" : "0"));
            $output->writeln("  Tempo:       {$totalTime}s");

            return $result['errors'] > 0 ? Command::FAILURE : Command::SUCCESS;
        } catch (\Exception $e) {
            $output->writeln('<error>Erro: ' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }
    }
}
