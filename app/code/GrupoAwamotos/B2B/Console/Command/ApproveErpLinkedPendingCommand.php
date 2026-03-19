<?php
declare(strict_types=1);

namespace GrupoAwamotos\B2B\Console\Command;

use GrupoAwamotos\B2B\Api\CustomerApprovalInterface;
use Magento\Framework\App\Area;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\App\State;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ApproveErpLinkedPendingCommand extends Command
{
    private const OPTION_DRY_RUN = 'dry-run';
    private const OPTION_LIMIT = 'limit';
    private const OPTION_REJECT_DUPLICATES = 'reject-duplicates';

    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly CustomerApprovalInterface $customerApproval,
        private readonly State $appState,
        ?string $name = null
    ) {
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this->setName('b2b:approve-erp-linked-pending')
            ->setDescription('Approves pending B2B customers already linked to ERP using the standard approval flow')
            ->addOption(self::OPTION_DRY_RUN, null, InputOption::VALUE_NONE, 'Only show what would be approved')
            ->addOption(self::OPTION_REJECT_DUPLICATES, null, InputOption::VALUE_NONE, 'Reject duplicate CNPJ cases instead of reporting them as failures')
            ->addOption(self::OPTION_LIMIT, null, InputOption::VALUE_REQUIRED, 'Limit number of customers processed', '0');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->initializeAreaCode();

        $dryRun = (bool) $input->getOption(self::OPTION_DRY_RUN);
        $limit = max(0, (int) $input->getOption(self::OPTION_LIMIT));
        $rejectDuplicates = (bool) $input->getOption(self::OPTION_REJECT_DUPLICATES);

        $customerIds = $this->getPendingErpLinkedCustomerIds($limit);
        $total = count($customerIds);

        $output->writeln('<info>Aprovação de clientes pendentes já vinculados ao ERP</info>');
        $output->writeln(sprintf('Clientes elegíveis: %d', $total));

        if ($total === 0) {
            $output->writeln('<comment>Nenhum cliente pendente vinculado ao ERP foi encontrado.</comment>');
            return Command::SUCCESS;
        }

        if ($dryRun) {
            foreach ($customerIds as $customerId) {
                $duplicateConflict = $this->getDuplicateCnpjConflict($customerId);

                if ($duplicateConflict !== null) {
                    $output->writeln(sprintf(
                        ' - customer_id=%d [duplicidade CNPJ com customer_id=%d %s]',
                        $customerId,
                        $duplicateConflict['customer_id'],
                        $duplicateConflict['email'] !== '' ? $duplicateConflict['email'] : 'sem-email'
                    ));
                    continue;
                }

                $output->writeln(sprintf(' - customer_id=%d', $customerId));
            }

            if ($dryRun) {
                $output->writeln('<comment>[DRY RUN] Nenhuma alteração foi aplicada.</comment>');
            }

            return Command::SUCCESS;
        }

        $approved = 0;
        $rejectedDuplicates = 0;
        $failed = 0;

        foreach ($customerIds as $customerId) {
            $duplicateConflict = $this->getDuplicateCnpjConflict($customerId);
            if ($duplicateConflict !== null) {
                $reason = sprintf(
                    'Cadastro duplicado: CNPJ %s ja vinculado ao cliente %d (%s).',
                    $duplicateConflict['cnpj'],
                    $duplicateConflict['customer_id'],
                    $duplicateConflict['email']
                );

                if ($rejectDuplicates) {
                    $result = $this->customerApproval->rejectCustomer($customerId, null, $reason);

                    if ($result) {
                        $rejectedDuplicates++;
                        $output->writeln(sprintf(
                            '<comment>Rejeitado por duplicidade:</comment> customer_id=%d conflito_com=%d',
                            $customerId,
                            $duplicateConflict['customer_id']
                        ));
                        continue;
                    }
                }

                $failed++;
                $output->writeln(sprintf(
                    '<error>Falha por duplicidade:</error> customer_id=%d conflito_com=%d',
                    $customerId,
                    $duplicateConflict['customer_id']
                ));
                continue;
            }

            $result = $this->customerApproval->approveCustomer(
                $customerId,
                null,
                'Aprovação em lote: cliente já vinculado ao ERP'
            );

            if ($result) {
                $approved++;
                $output->writeln(sprintf('<info>Aprovado:</info> customer_id=%d', $customerId));
                continue;
            }

            $failed++;
            $output->writeln(sprintf('<error>Falha:</error> customer_id=%d', $customerId));
        }

        $output->writeln('');
        $output->writeln('<info>=== Resumo ===</info>');
        $output->writeln(sprintf('Aprovados: %d', $approved));
        $output->writeln(sprintf('Rejeitados por duplicidade: %d', $rejectedDuplicates));
        $output->writeln(sprintf('Falhas: %d', $failed));

        return $failed > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    /**
     * @return int[]
     */
    private function getPendingErpLinkedCustomerIds(int $limit): array
    {
        $connection = $this->resourceConnection->getConnection();
        $entityTable = $this->resourceConnection->getTableName('customer_entity');
        $entityMapTable = $this->resourceConnection->getTableName('grupoawamotos_erp_entity_map');
        $customerVarcharTable = $this->resourceConnection->getTableName('customer_entity_varchar');
        $eavTable = $this->resourceConnection->getTableName('eav_attribute');

        $approvalAttributeId = (int) $connection->fetchOne(
            "SELECT attribute_id FROM {$eavTable} WHERE attribute_code = 'b2b_approval_status' AND entity_type_id = 1"
        );

        $select = $connection->select()
            ->distinct()
            ->from(['ce' => $entityTable], ['entity_id'])
            ->join(
                ['em' => $entityMapTable],
                "em.magento_entity_id = ce.entity_id AND em.entity_type = 'customer'",
                []
            )
            ->joinLeft(
                ['appr' => $customerVarcharTable],
                sprintf('appr.entity_id = ce.entity_id AND appr.attribute_id = %d', $approvalAttributeId),
                []
            )
            ->where('ce.group_id IN (?)', [4, 5, 6, 7])
            ->where('appr.value = ?', 'pending')
            ->order('ce.entity_id ASC');

        if ($limit > 0) {
            $select->limit($limit);
        }

        return array_map('intval', $connection->fetchCol($select));
    }

    private function initializeAreaCode(): void
    {
        try {
            $this->appState->getAreaCode();
        } catch (\Magento\Framework\Exception\LocalizedException) {
            $this->appState->setAreaCode(Area::AREA_ADMINHTML);
        }
    }

    /**
     * @return array{customer_id:int,email:string,cnpj:string}|null
     */
    private function getDuplicateCnpjConflict(int $customerId): ?array
    {
        $connection = $this->resourceConnection->getConnection();
        $entityTable = $this->resourceConnection->getTableName('customer_entity');
        $customerVarcharTable = $this->resourceConnection->getTableName('customer_entity_varchar');

        $cnpjAttributeId = $this->getCustomerAttributeId('b2b_cnpj');
        if ($cnpjAttributeId <= 0) {
            return null;
        }

        $customerCnpj = (string) $connection->fetchOne(
            $connection->select()
                ->from(['cev' => $customerVarcharTable], ['value'])
                ->where('cev.entity_id = ?', $customerId)
                ->where('cev.attribute_id = ?', $cnpjAttributeId)
                ->limit(1)
        );

        $customerCnpjDigits = $this->normalizeDocument($customerCnpj);
        if ($customerCnpjDigits === '') {
            return null;
        }

        $formattedCnpj = $this->formatCnpj($customerCnpjDigits);

        $select = $connection->select()
            ->from(['ce' => $entityTable], ['customer_id' => 'entity_id', 'email'])
            ->join(
                ['cev' => $customerVarcharTable],
                'cev.entity_id = ce.entity_id',
                ['cnpj' => 'value']
            )
            ->where('ce.entity_id != ?', $customerId)
            ->where('cev.attribute_id = ?', $cnpjAttributeId)
            ->where('cev.value IN (?)', [$customerCnpjDigits, $formattedCnpj]);

        $conflicts = $connection->fetchAll($select);

        foreach ($conflicts as $conflict) {
            $conflictCnpjDigits = $this->normalizeDocument((string) ($conflict['cnpj'] ?? ''));
            if ($conflictCnpjDigits !== $customerCnpjDigits) {
                continue;
            }

            return [
                'customer_id' => (int) $conflict['customer_id'],
                'email' => $this->maskEmail((string) ($conflict['email'] ?? '')),
                'cnpj' => $formattedCnpj,
            ];
        }

        return null;
    }

    private function getCustomerAttributeId(string $attributeCode): int
    {
        $connection = $this->resourceConnection->getConnection();
        $eavTable = $this->resourceConnection->getTableName('eav_attribute');

        return (int) $connection->fetchOne(
            "SELECT attribute_id FROM {$eavTable} WHERE attribute_code = ? AND entity_type_id = 1",
            [$attributeCode]
        );
    }

    private function normalizeDocument(string $value): string
    {
        return (string) preg_replace('/\D+/', '', $value);
    }

    private function formatCnpj(string $cnpjDigits): string
    {
        if (strlen($cnpjDigits) !== 14) {
            return $cnpjDigits;
        }

        return substr($cnpjDigits, 0, 2)
            . '.' . substr($cnpjDigits, 2, 3)
            . '.' . substr($cnpjDigits, 5, 3)
            . '/' . substr($cnpjDigits, 8, 4)
            . '-' . substr($cnpjDigits, 12, 2);
    }

    private function maskEmail(string $email): string
    {
        $email = trim($email);
        if ($email === '' || strpos($email, '@') === false) {
            return $email;
        }

        [$localPart, $domain] = explode('@', $email, 2);
        if ($localPart === '') {
            return '***@' . $domain;
        }

        return substr($localPart, 0, 1)
            . str_repeat('*', max(2, strlen($localPart) - 1))
            . '@' . $domain;
    }
}
