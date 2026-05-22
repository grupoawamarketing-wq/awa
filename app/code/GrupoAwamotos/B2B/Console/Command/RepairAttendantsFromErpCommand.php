<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Console\Command;

use GrupoAwamotos\B2B\Cron\SyncAttendantFromErp;
use GrupoAwamotos\B2B\Model\Attendant\AttendantManager;
use GrupoAwamotos\B2B\Model\ResourceModel\Attendant\CollectionFactory as AttendantCollectionFactory;
use GrupoAwamotos\ERPIntegration\Api\ConnectionInterface as ErpConnectionInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\App\State;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Corrige atendentes importados com nomes de clientes (JOIN errado em FN_FORNECEDORES).
 *
 * Usage:
 *   php bin/magento b2b:attendant:repair-from-erp --dry-run
 *   php bin/magento b2b:attendant:repair-from-erp
 *   php bin/magento b2b:attendant:repair-from-erp --resync
 */
class RepairAttendantsFromErpCommand extends Command
{
    /**
     * attendant_id => erp_seller_code correto em FN_VENDEDORES.
     *
     * @var array<int, int>
     */
    private const INTERNAL_ERP_CODE_FIXES = [
        2 => 115, // Adrielly → SUPORTE 2
    ];

    public function __construct(
        private readonly ErpConnectionInterface $erpConnection,
        private readonly AttendantManager $attendantManager,
        private readonly AttendantCollectionFactory $attendantCollectionFactory,
        private readonly ResourceConnection $resourceConnection,
        private readonly SyncAttendantFromErp $syncAttendantFromErp,
        private readonly State $state,
        private readonly LoggerInterface $logger,
        ?string $name = null
    ) {
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this->setName('b2b:attendant:repair-from-erp')
            ->setDescription('Corrige nomes/codigos ERP dos atendentes e opcionalmente re-sincroniza carteiras')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Simula sem gravar alteracoes')
            ->addOption('resync', null, InputOption::VALUE_NONE, 'Executa sync VENDPREF apos correcoes')
            ->addOption(
                'skip-internal-codes',
                null,
                InputOption::VALUE_NONE,
                'Nao corrige mapeamentos erp_seller_code da equipe interna AWA'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->state->setAreaCode(\Magento\Framework\App\Area::AREA_ADMINHTML);
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            $this->logger->warning($e->getMessage());
        }

        $dryRun = (bool) $input->getOption('dry-run');
        $resync = (bool) $input->getOption('resync');
        $skipInternalCodes = (bool) $input->getOption('skip-internal-codes');

        if ($dryRun) {
            $output->writeln('<comment>[DRY-RUN] Nenhuma alteracao sera salva.</comment>');
        }

        if (!$this->erpConnection->hasAvailableDriver()) {
            $output->writeln('<error>Driver SQL Server indisponivel.</error>');

            return Command::FAILURE;
        }

        $erpNames = $this->loadErpSellerNames();
        if ($erpNames === []) {
            $output->writeln('<error>Nenhum vendedor encontrado em FN_VENDEDORES.</error>');

            return Command::FAILURE;
        }

        $namesFixed = $this->repairNames($erpNames, $dryRun, $output);

        $codesFixed = 0;
        if (!$skipInternalCodes) {
            $codesFixed = $this->repairInternalErpCodes($erpNames, $dryRun, $output);
        }

        if ($resync && !$dryRun) {
            $output->writeln('<info>Re-sincronizando carteiras (VENDPREF)...</info>');
            $this->syncAttendantFromErp->execute();
            $this->attendantManager->recalculateAllCounts();
            $output->writeln('<info>Sync concluido.</info>');
        }

        $output->writeln(sprintf(
            '<info>Reparo concluido: %d nome(s) corrigido(s), %d codigo(s) interno(s) ajustado(s).</info>',
            $namesFixed,
            $codesFixed
        ));

        return Command::SUCCESS;
    }

    /**
     * @return array<int, string>
     */
    private function loadErpSellerNames(): array
    {
        $rows = $this->erpConnection->query(
            'SELECT CODIGO, RAZAO, FANTASIA FROM dbo.FN_VENDEDORES WHERE CODIGO > 0'
        );

        $result = [];
        foreach ($rows as $row) {
            $code = (int) ($row['CODIGO'] ?? 0);
            if ($code <= 0) {
                continue;
            }

            $fantasia = trim((string) ($row['FANTASIA'] ?? ''));
            $razao = trim((string) ($row['RAZAO'] ?? ''));
            $result[$code] = $fantasia ?: $razao ?: ('Vendedor ' . $code);
        }

        return $result;
    }

    /**
     * @param array<int, string> $erpNames
     */
    private function repairNames(array $erpNames, bool $dryRun, OutputInterface $output): int
    {
        $collection = $this->attendantCollectionFactory->create();
        $collection->addFieldToFilter('is_active', 1);
        $collection->addFieldToFilter('erp_seller_code', ['notnull' => true]);

        $fixed = 0;

        foreach ($collection as $attendant) {
            if (!empty($attendant->getData('admin_user_id'))) {
                continue;
            }

            $erpCode = (int) $attendant->getData('erp_seller_code');
            if ($erpCode <= 0 || !isset($erpNames[$erpCode])) {
                continue;
            }

            $correctName = $erpNames[$erpCode];
            $currentName = trim((string) $attendant->getData('name'));

            if ($currentName === $correctName) {
                continue;
            }

            $output->writeln(sprintf(
                '  Nome #%d (ERP %d): "%s" → "%s"',
                (int) $attendant->getId(),
                $erpCode,
                mb_strimwidth($currentName, 0, 40, '...'),
                mb_strimwidth($correctName, 0, 40, '...')
            ));

            if (!$dryRun) {
                $this->attendantManager->saveAttendant([
                    'attendant_id' => (int) $attendant->getId(),
                    'name' => $correctName,
                    'email' => (string) $attendant->getData('email'),
                    'phone' => $attendant->getData('phone'),
                    'whatsapp' => $attendant->getData('whatsapp'),
                    'department' => (string) $attendant->getData('department'),
                    'is_active' => 1,
                    'max_customers' => (int) $attendant->getData('max_customers'),
                    'erp_seller_code' => (string) $erpCode,
                    'chatwoot_agent_id' => $attendant->getData('chatwoot_agent_id'),
                ]);
            }

            $fixed++;
        }

        return $fixed;
    }

    /**
     * @param array<int, string> $erpNames
     */
    private function repairInternalErpCodes(array $erpNames, bool $dryRun, OutputInterface $output): int
    {
        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName('grupoawamotos_b2b_attendants');
        $fixed = 0;

        foreach (self::INTERNAL_ERP_CODE_FIXES as $attendantId => $targetErpCode) {
            $attendant = $this->attendantManager->getAttendantById($attendantId);
            if ($attendant === null || empty($attendant['admin_user_id'])) {
                continue;
            }

            $currentCode = (int) ($attendant['erp_seller_code'] ?? 0);
            if ($currentCode === $targetErpCode) {
                continue;
            }

            $duplicateCollection = $this->attendantCollectionFactory->create();
            $duplicateCollection->addFieldToFilter('erp_seller_code', $targetErpCode);
            $duplicateCollection->addFieldToFilter('is_active', 1);
            $duplicateCollection->addFieldToFilter('attendant_id', ['neq' => $attendantId]);
            $duplicateAttendant = $duplicateCollection->getFirstItem();

            if ($duplicateAttendant->getId()) {
                $duplicateId = (int) $duplicateAttendant->getId();
                $output->writeln(sprintf(
                    '  Desativando duplicata #%d (ERP %d) e transferindo clientes para #%d',
                    $duplicateId,
                    $targetErpCode,
                    $attendantId
                ));

                if (!$dryRun) {
                    $this->attendantManager->deactivateAttendant($duplicateId, $attendantId);
                }
            }

            $targetName = $erpNames[$targetErpCode] ?? (string) $attendant['name'];
            $output->writeln(sprintf(
                '  Codigo interno #%d (%s): ERP %d → %d (%s)',
                $attendantId,
                $attendant['name'],
                $currentCode,
                $targetErpCode,
                $targetName
            ));

            if (!$dryRun) {
                $connection->update(
                    $table,
                    [
                        'erp_seller_code' => (string) $targetErpCode,
                        'updated_at' => date('Y-m-d H:i:s'),
                    ],
                    ['attendant_id = ?' => $attendantId]
                );
            }

            $fixed++;
        }

        return $fixed;
    }
}
