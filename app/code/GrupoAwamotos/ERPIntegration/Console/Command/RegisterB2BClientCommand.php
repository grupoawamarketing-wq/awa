<?php

declare(strict_types=1);

namespace GrupoAwamotos\ERPIntegration\Console\Command;

use GrupoAwamotos\ERPIntegration\Model\B2BClientRegistration;
use GrupoAwamotos\ERPIntegration\Model\ResourceModel\SyncLog as SyncLogResource;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class RegisterB2BClientCommand extends Command
{
    private const COMMAND_NAME = 'erp:client:register';
    private const COMMAND_ALIAS = 'erp:b2b:register';

    private B2BClientRegistration $b2bRegistration;
    private SyncLogResource $syncLogResource;
    private OrderRepositoryInterface $orderRepository;
    private SearchCriteriaBuilder $searchCriteriaBuilder;

    public function __construct(
        B2BClientRegistration $b2bRegistration,
        SyncLogResource $syncLogResource,
        OrderRepositoryInterface $orderRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder
    ) {
        parent::__construct();
        $this->b2bRegistration = $b2bRegistration;
        $this->syncLogResource = $syncLogResource;
        $this->orderRepository = $orderRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
    }

    protected function configure(): void
    {
        $this->setName(self::COMMAND_NAME)
            ->setAliases([self::COMMAND_ALIAS])
            ->setDescription('Registra clientes B2B no validador Sectra (GR_INTEGRACAOVALIDADOR)')
            ->addArgument('erp_codes', InputArgument::OPTIONAL, 'Codigos ERP separados por virgula (ex: 2541,699)')
            ->addOption('pending', 'p', InputOption::VALUE_NONE, 'Registrar clientes de todos os pedidos pendentes')
            ->addOption('check', 'c', InputOption::VALUE_NONE, 'Apenas verificar sem registrar')
            ->addOption('generate-sql', 's', InputOption::VALUE_NONE, 'Gerar SQL para execucao manual')
            ->addOption('all', null, InputOption::VALUE_NONE, 'Processar TODOS os clientes B2B nao registrados no Sectra')
            ->addOption('batch-size', null, InputOption::VALUE_OPTIONAL, 'Tamanho do lote para processamento em massa', '500')
            ->addOption('save', null, InputOption::VALUE_NONE, 'Salvar SQL gerado em var/log/ (use com --generate-sql)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $erpCodes = [];

        // Collect ERP codes from argument
        $codesArg = $input->getArgument('erp_codes');
        if ($codesArg) {
            $erpCodes = array_map('intval', explode(',', $codesArg));
        }

        // --all: carrega todos os clientes B2B nao registrados no Sectra
        if ($input->getOption('all')) {
            $batchSize = max(1, (int) $input->getOption('batch-size'));
            $allCodes = $this->getAllUnregisteredB2BCodes($batchSize);
            $erpCodes = array_unique(array_merge($erpCodes, $allCodes));
        } elseif ($input->getOption('pending') || empty($erpCodes)) {
            // Collect ERP codes from pending orders
            $pendingCodes = $this->getClientCodesFromPendingOrders();
            $erpCodes = array_unique(array_merge($erpCodes, $pendingCodes));
        }

        if (empty($erpCodes)) {
            $output->writeln('<info>Nenhum cliente para processar.</info>');
            return Command::SUCCESS;
        }

        $output->writeln(sprintf('<info>Processando %d cliente(s)...</info>', count($erpCodes)));

        // Check mode
        if ($input->getOption('check')) {
            return $this->checkClients($erpCodes, $output);
        }

        // Generate SQL mode
        if ($input->getOption('generate-sql')) {
            $save = $input->getOption('save');
            return $this->generateSQL($erpCodes, $output, $save);
        }

        // Register mode
        return $this->registerClients($erpCodes, $output);
    }

    private function checkClients(array $erpCodes, OutputInterface $output): int
    {
        $unregistered = $this->b2bRegistration->getUnregisteredClients($erpCodes);

        if (empty($unregistered)) {
            $output->writeln('<info>Todos os clientes ja estao registrados no Sectra.</info>');
            return Command::SUCCESS;
        }

        $output->writeln(sprintf('<comment>%d cliente(s) NAO registrado(s):</comment>', count($unregistered)));
        foreach ($unregistered as $c) {
            $output->writeln(sprintf(
                '  <error>%d</error> - %s (CNPJ: %s)',
                $c['erp_code'],
                $c['razao'],
                $c['cgc']
            ));
        }

        $output->writeln('');
        $output->writeln(sprintf(
            '<comment>Para registrar: bin/magento %s %s</comment>',
            self::COMMAND_NAME,
            implode(',', array_column($unregistered, 'erp_code'))
        ));
        $output->writeln(sprintf(
            '<comment>Para gerar SQL: bin/magento %s --generate-sql %s</comment>',
            self::COMMAND_NAME,
            implode(',', array_column($unregistered, 'erp_code'))
        ));

        return Command::FAILURE;
    }

    private function generateSQL(array $erpCodes, OutputInterface $output, bool $save = false): int
    {
        $sql = $this->b2bRegistration->generateRegistrationSQL($erpCodes);

        if ($save) {
            $dir = BP . '/var/log';
            $file = $dir . '/sectra_register_clients_' . date('Ymd_His') . '.sql';
            $latest = $dir . '/sectra_register_clients_latest.sql';
            file_put_contents($file, $sql);
            file_put_contents($latest, $sql);
            $output->writeln(sprintf('<info>SQL salvo em: %s</info>', $file));
            $output->writeln(sprintf('<info>Link latest: %s</info>', $latest));
        } else {
            $output->writeln($sql);
        }

        return Command::SUCCESS;
    }

    private function registerClients(array $erpCodes, OutputInterface $output): int
    {
        if (!$this->b2bRegistration->hasWriteAccess()) {
            $output->writeln('<error>Conexao de escrita nao disponivel!</error>');
            $output->writeln('<comment>Configure as credenciais de escrita em:</comment>');
            $output->writeln('  Admin > Stores > Configuration > ERP Integration > Conexao de Escrita');
            $output->writeln('');
            $output->writeln('<comment>Ou use --generate-sql para gerar SQL e executar manualmente:</comment>');
            $output->writeln(sprintf(
                '  bin/magento %s --generate-sql %s',
                self::COMMAND_NAME,
                implode(',', $erpCodes)
            ));

            // Fallback: generate SQL
            $output->writeln('');
            $sql = $this->b2bRegistration->generateRegistrationSQL($erpCodes);
            $output->writeln($sql);
            return Command::FAILURE;
        }

        $success = 0;
        $failed = 0;

        foreach ($erpCodes as $code) {
            $code = (int) $code;
            if ($code <= 0) {
                continue;
            }

            if ($this->b2bRegistration->registerClient($code)) {
                $output->writeln(sprintf('<info>  OK: Cliente %d registrado</info>', $code));
                $success++;
            } else {
                $output->writeln(sprintf('<error>  FALHA: Cliente %d</error>', $code));
                $failed++;
            }
        }

        $output->writeln('');
        $output->writeln(sprintf('<info>Resultado: %d registrado(s), %d falha(s)</info>', $success, $failed));

        return $failed > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    /**
     * Get all B2B customer ERP codes that are not yet registered in Sectra.
     *
     * @param int $limit Maximum number to return
     * @return int[]
     */
    private function getAllUnregisteredB2BCodes(int $limit = 500): array
    {
        $connection = $this->syncLogResource->getConnection();

        // Get all B2B customers with ERP codes
        $select = $connection->select()
            ->from(['em' => 'grupoawamotos_erp_entity_map'], ['erp_code'])
            ->where('em.entity_type = ?', 'customer')
            ->where('em.erp_code > ?', '0')
            ->where('em.erp_code != ?', '')
            ->limit($limit * 3); // fetch extra to account for already-registered ones

        $allCodes = array_unique(array_map('intval', $connection->fetchCol($select)));
        $allCodes = array_filter($allCodes, fn($c) => $c > 0);

        // Filter out already registered ones
        $registeredCodes = array_fill_keys($this->b2bRegistration->getRegisteredClientCodes(), true);
        $unregistered = array_filter($allCodes, fn($c) => !isset($registeredCodes[$c]));

        return array_slice(array_values($unregistered), 0, $limit);
    }

    private function getClientCodesFromPendingOrders(): array
    {
        // Get synced order IDs
        $connection = $this->syncLogResource->getConnection();
        $select = $connection->select()
            ->from('grupoawamotos_erp_entity_map', ['magento_entity_id'])
            ->where('entity_type = ?', 'order');
        $syncedIds = array_map('intval', $connection->fetchCol($select));

        // Get pending orders
        $this->searchCriteriaBuilder->addFilter('state', ['new', 'pending_payment', 'processing'], 'in');
        $searchCriteria = $this->searchCriteriaBuilder->create();
        $orders = $this->orderRepository->getList($searchCriteria)->getItems();

        $codes = [];
        foreach ($orders as $order) {
            if (in_array((int) $order->getEntityId(), $syncedIds, true)) {
                continue;
            }

            // Get ERP code from entity_map
            $selectErp = $connection->select()
                ->from('grupoawamotos_erp_entity_map', ['erp_code'])
                ->where('entity_type = ?', 'customer')
                ->where('magento_entity_id = ?', (int) $order->getCustomerId());
            $erpCode = $connection->fetchOne($selectErp);

            if ($erpCode && is_numeric($erpCode)) {
                $codes[] = (int) $erpCode;
            }
        }

        return array_unique($codes);
    }
}
