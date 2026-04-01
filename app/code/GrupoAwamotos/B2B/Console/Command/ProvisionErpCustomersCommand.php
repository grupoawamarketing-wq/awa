<?php

/**
 * CLI Command: Provision ERP customers with password, b2b_cnpj and approval status
 *
 * Usage: bin/magento b2b:provision-erp-customers
 */

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Console\Command;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Encryption\EncryptorInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ProvisionErpCustomersCommand extends Command
{
    private const DEFAULT_PASSWORD = '123awa';
    private const BATCH_SIZE = 500;

    private ResourceConnection $resource;
    private EncryptorInterface $encryptor;

    public function __construct(
        ResourceConnection $resource,
        EncryptorInterface $encryptor,
        ?string $name = null
    ) {
        $this->resource = $resource;
        $this->encryptor = $encryptor;
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this->setName('b2b:provision-erp-customers')
            ->setDescription('Set default password and populate b2b_cnpj for all ERP customers')
            ->addOption('password', 'p', InputOption::VALUE_OPTIONAL, 'Password to set', self::DEFAULT_PASSWORD)
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show what would be done without changes');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $password = $input->getOption('password');
        $dryRun = $input->getOption('dry-run');

        $conn = $this->resource->getConnection();

        $output->writeln('<info>Provisionamento de clientes ERP para acesso B2B</info>');
        $output->writeln('');

        // Attribute IDs
        $b2bCnpjAttrId = (int) $conn->fetchOne(
            "SELECT attribute_id FROM eav_attribute WHERE attribute_code = 'b2b_cnpj' AND entity_type_id = 1"
        );
        $approvalAttrId = (int) $conn->fetchOne(
            "SELECT attribute_id FROM eav_attribute WHERE attribute_code = 'b2b_approval_status' AND entity_type_id = 1"
        );

        if (!$b2bCnpjAttrId || !$approvalAttrId) {
            $output->writeln('<error>Atributos b2b_cnpj ou b2b_approval_status não encontrados</error>');
            return Command::FAILURE;
        }

        $output->writeln("b2b_cnpj attribute_id: {$b2bCnpjAttrId}");
        $output->writeln("b2b_approval_status attribute_id: {$approvalAttrId}");

        // Get all ERP customers with their taxvat
        $erpCustomers = $conn->fetchAll("
            SELECT em.erp_code, em.magento_entity_id, ce.email, ce.taxvat, ce.password_hash
            FROM grupoawamotos_erp_entity_map em
            INNER JOIN customer_entity ce ON em.magento_entity_id = ce.entity_id
            WHERE em.entity_type = 'customer'
        ");

        $total = count($erpCustomers);
        $output->writeln("Total clientes ERP: {$total}");
        $output->writeln('');

        if ($dryRun) {
            $output->writeln('<comment>[DRY RUN] Nenhuma alteração será feita</comment>');
        }

        // Generate password hash
        $passwordHash = $this->encryptor->getHash($password, true);
        $output->writeln("Password hash gerado para: {$password}");

        $stats = [
            'password_set' => 0,
            'password_skipped' => 0,
            'cnpj_set' => 0,
            'cnpj_skipped' => 0,
            'approval_set' => 0,
            'approval_skipped' => 0,
            'no_taxvat' => 0,
        ];

        $batched = array_chunk($erpCustomers, self::BATCH_SIZE);
        $processed = 0;

        foreach ($batched as $batch) {
            if (!$dryRun) {
                $conn->beginTransaction();
            }

            try {
                foreach ($batch as $customer) {
                    $entityId = (int) $customer['magento_entity_id'];
                    $taxvat = trim((string) ($customer['taxvat'] ?? ''));

                    // 1. Set password
                    if (empty($customer['password_hash'])) {
                        if (!$dryRun) {
                            $conn->update(
                                'customer_entity',
                                ['password_hash' => $passwordHash],
                                ['entity_id = ?' => $entityId]
                            );
                        }
                        $stats['password_set']++;
                    } else {
                        $stats['password_skipped']++;
                    }

                    // 2. Set b2b_cnpj from taxvat (only for 14-digit CNPJ)
                    $cnpjDigits = preg_replace('/\D/', '', $taxvat);
                    if (strlen($cnpjDigits) === 14) {
                        // Check if already set
                        $existing = $conn->fetchOne(
                            "SELECT value FROM customer_entity_varchar WHERE entity_id = ? AND attribute_id = ?",
                            [$entityId, $b2bCnpjAttrId]
                        );

                        if (empty($existing)) {
                            if (!$dryRun) {
                                $conn->insertOnDuplicate(
                                    'customer_entity_varchar',
                                    [
                                        'entity_id' => $entityId,
                                        'attribute_id' => $b2bCnpjAttrId,
                                        'value' => $cnpjDigits,
                                    ],
                                    ['value']
                                );
                            }
                            $stats['cnpj_set']++;
                        } else {
                            $stats['cnpj_skipped']++;
                        }
                    } else {
                        $stats['no_taxvat']++;
                    }

                    // 3. Set b2b_approval_status to 'approved'
                    $existingApproval = $conn->fetchOne(
                        "SELECT value FROM customer_entity_varchar WHERE entity_id = ? AND attribute_id = ?",
                        [$entityId, $approvalAttrId]
                    );

                    if ($existingApproval !== 'approved') {
                        if (!$dryRun) {
                            $conn->insertOnDuplicate(
                                'customer_entity_varchar',
                                [
                                    'entity_id' => $entityId,
                                    'attribute_id' => $approvalAttrId,
                                    'value' => 'approved',
                                ],
                                ['value']
                            );
                        }
                        $stats['approval_set']++;
                    } else {
                        $stats['approval_skipped']++;
                    }
                }

                if (!$dryRun) {
                    $conn->commit();
                }
            } catch (\Exception $e) {
                if (!$dryRun) {
                    $conn->rollBack();
                }
                $output->writeln("<error>Erro no batch: {$e->getMessage()}</error>");
            }

            $processed += count($batch);
            $output->writeln("Progresso: {$processed}/{$total}");
        }

        // Summary
        $output->writeln('');
        $output->writeln('<info>=== Resumo ===</info>');
        $output->writeln("Senhas definidas:      {$stats['password_set']}");
        $output->writeln("Senhas já existentes:  {$stats['password_skipped']}");
        $output->writeln("CNPJ preenchidos:      {$stats['cnpj_set']}");
        $output->writeln("CNPJ já existentes:    {$stats['cnpj_skipped']}");
        $output->writeln("Sem CNPJ (CPF/vazio):  {$stats['no_taxvat']}");
        $output->writeln("Approval → approved:   {$stats['approval_set']}");
        $output->writeln("Approval já approved:  {$stats['approval_skipped']}");

        if ($dryRun) {
            $output->writeln('');
            $output->writeln('<comment>[DRY RUN] Nenhuma alteração foi feita. Remova --dry-run para executar.</comment>');
        }

        return Command::SUCCESS;
    }
}
