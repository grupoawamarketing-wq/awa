<?php
declare(strict_types=1);

namespace GrupoAwamotos\ERPIntegration\Console\Command;

use GrupoAwamotos\ERPIntegration\Api\CustomerSyncInterface;
use GrupoAwamotos\ERPIntegration\Model\B2BClientRegistration;
use GrupoAwamotos\ERPIntegration\Model\ResourceModel\SyncLog as SyncLogResource;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\Table;

/**
 * CLI command to manage orders held by the ERP pull system.
 *
 * Actions:
 *  --action=list        List held orders with reason and customer details (default)
 *  --action=resolve     Try to resolve erp_code for held orders via CPF/CNPJ lookup
 *  --action=generate-sql Generate SQL for GR_INTEGRACAOVALIDADOR registration
 */
class HeldOrdersCommand extends Command
{
    private OrderCollectionFactory $orderCollectionFactory;
    private CustomerRepositoryInterface $customerRepository;
    private CustomerSyncInterface $customerSync;
    private B2BClientRegistration $b2bRegistration;
    private SyncLogResource $syncLogResource;

    public function __construct(
        OrderCollectionFactory $orderCollectionFactory,
        CustomerRepositoryInterface $customerRepository,
        CustomerSyncInterface $customerSync,
        B2BClientRegistration $b2bRegistration,
        SyncLogResource $syncLogResource
    ) {
        parent::__construct();
        $this->orderCollectionFactory = $orderCollectionFactory;
        $this->customerRepository = $customerRepository;
        $this->customerSync = $customerSync;
        $this->b2bRegistration = $b2bRegistration;
        $this->syncLogResource = $syncLogResource;
    }

    protected function configure(): void
    {
        $this->setName('erp:orders:held')
            ->setDescription('Gerencia pedidos retidos por falta de vinculação ERP (erp_code ou GR_INTEGRACAOVALIDADOR)')
            ->addOption('action', 'a', InputOption::VALUE_OPTIONAL, 'Ação: list, resolve, generate-sql', 'list')
            ->addOption('limit', 'l', InputOption::VALUE_OPTIONAL, 'Limite de pedidos a processar', '100');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $action = $input->getOption('action');
        $limit = (int) $input->getOption('limit');

        switch ($action) {
            case 'list':
                return $this->listHeldOrders($output, $limit);
            case 'resolve':
                return $this->resolveHeldOrders($output, $limit);
            case 'generate-sql':
                return $this->generateSql($output, $limit);
            default:
                $output->writeln(sprintf('<error>Ação desconhecida: %s. Use: list, resolve, generate-sql</error>', $action));
                return Command::FAILURE;
        }
    }

    /**
     * List all held orders with diagnostic information.
     */
    private function listHeldOrders(OutputInterface $output, int $limit): int
    {
        $orders = $this->getHeldOrders($limit);

        if (empty($orders)) {
            $output->writeln('<info>Nenhum pedido retido encontrado.</info>');
            return Command::SUCCESS;
        }

        $output->writeln(sprintf('<info>Pedidos retidos: %d</info>', count($orders)));
        $output->writeln('');

        $table = new Table($output);
        $table->setHeaders(['Pedido', 'Status', 'Cliente', 'CPF/CNPJ', 'ERP Code', 'Motivo', 'Data']);

        foreach ($orders as $orderInfo) {
            $table->addRow([
                $orderInfo['increment_id'],
                $orderInfo['status'],
                $orderInfo['customer_name'],
                $orderInfo['taxvat_masked'],
                $orderInfo['erp_code'] ?: '<fg=red>NENHUM</>',
                $orderInfo['reason'],
                $orderInfo['created_at'],
            ]);
        }

        $table->render();

        // Summary
        $output->writeln('');
        $withCode = count(array_filter($orders, fn($o) => !empty($o['erp_code'])));
        $withoutCode = count($orders) - $withCode;
        $output->writeln(sprintf(
            '<comment>Resumo: %d com erp_code (precisam registro no Sectra), %d sem erp_code (precisam vinculação)</comment>',
            $withCode,
            $withoutCode
        ));

        if ($withoutCode > 0) {
            $output->writeln('<comment>Dica: Execute "erp:orders:held --action=resolve" para tentar vincular por CPF/CNPJ</comment>');
        }
        if ($withCode > 0) {
            $output->writeln('<comment>Dica: Execute "erp:orders:held --action=generate-sql" para gerar SQL de registro no Sectra</comment>');
        }

        return Command::SUCCESS;
    }

    /**
     * Try to resolve ERP codes for held orders via CPF/CNPJ lookup.
     */
    private function resolveHeldOrders(OutputInterface $output, int $limit): int
    {
        $orders = $this->getHeldOrders($limit);

        if (empty($orders)) {
            $output->writeln('<info>Nenhum pedido retido encontrado.</info>');
            return Command::SUCCESS;
        }

        $output->writeln(sprintf('<info>Tentando resolver %d pedido(s) retido(s)...</info>', count($orders)));

        $resolved = 0;
        $alreadyLinked = 0;
        $notFound = 0;
        $noTaxvat = 0;
        $errors = 0;

        foreach ($orders as $orderInfo) {
            $customerId = $orderInfo['customer_id'];
            $incrementId = $orderInfo['increment_id'];

            if (!$customerId) {
                $output->writeln(sprintf('  [SKIP] #%s — pedido de visitante', $incrementId));
                continue;
            }

            // Already has erp_code, problem is Sectra registration
            if (!empty($orderInfo['erp_code'])) {
                $alreadyLinked++;
                $output->writeln(sprintf(
                    '  [OK]   #%s — já tem erp_code=%s (precisa registro no Sectra)',
                    $incrementId,
                    $orderInfo['erp_code']
                ));
                continue;
            }

            // No taxvat, cannot lookup
            if (empty($orderInfo['taxvat'])) {
                $noTaxvat++;
                $output->writeln(sprintf('  [SKIP] #%s — cliente sem CPF/CNPJ', $incrementId));
                continue;
            }

            try {
                $erpCustomer = $this->customerSync->getErpCustomerByTaxvat($orderInfo['taxvat']);

                if (!$erpCustomer || empty($erpCustomer['CODIGO'])) {
                    $notFound++;
                    $output->writeln(sprintf(
                        '  [MISS] #%s — CPF/CNPJ %s não encontrado no ERP',
                        $incrementId,
                        $orderInfo['taxvat_masked']
                    ));
                    continue;
                }

                $erpCode = (int) $erpCustomer['CODIGO'];
                $linked = $this->customerSync->linkMagentoToErp($customerId, $erpCode);

                if ($linked) {
                    // Stamp on order too
                    $this->stampErpCodeOnOrder($orderInfo['order_entity_id'], $erpCode);

                    $resolved++;
                    $output->writeln(sprintf(
                        '  <info>[LINK]</info> #%s — vinculado a ERP %d (%s)',
                        $incrementId,
                        $erpCode,
                        $erpCustomer['RAZAO'] ?? ''
                    ));
                } else {
                    $errors++;
                    $output->writeln(sprintf('  <error>[ERRO]</error> #%s — falha ao vincular', $incrementId));
                }
            } catch (\Exception $e) {
                $errors++;
                $output->writeln(sprintf('  <error>[ERRO]</error> #%s — %s', $incrementId, $e->getMessage()));
            }
        }

        $output->writeln('');
        $output->writeln(sprintf(
            '<info>Resultado: %d vinculados, %d já tinham código, %d não encontrados no ERP, %d sem CPF/CNPJ, %d erros</info>',
            $resolved,
            $alreadyLinked,
            $notFound,
            $noTaxvat,
            $errors
        ));

        return Command::SUCCESS;
    }

    /**
     * Generate SQL for registering held-order clients in GR_INTEGRACAOVALIDADOR.
     */
    private function generateSql(OutputInterface $output, int $limit): int
    {
        $orders = $this->getHeldOrders($limit);

        if (empty($orders)) {
            $output->writeln('<info>Nenhum pedido retido encontrado.</info>');
            return Command::SUCCESS;
        }

        // Collect unique ERP codes that need Sectra registration
        $erpCodes = [];
        foreach ($orders as $orderInfo) {
            if (!empty($orderInfo['erp_code'])) {
                $erpCodes[] = (int) $orderInfo['erp_code'];
            }
        }
        $erpCodes = array_unique($erpCodes);

        if (empty($erpCodes)) {
            $output->writeln('<comment>Nenhum pedido retido tem erp_code. Execute "--action=resolve" primeiro para vincular por CPF/CNPJ.</comment>');
            return Command::SUCCESS;
        }

        // Filter only unregistered clients
        $unregistered = $this->b2bRegistration->getUnregisteredClients($erpCodes);

        if (empty($unregistered)) {
            $output->writeln('<info>Todos os clientes com erp_code já estão registrados no GR_INTEGRACAOVALIDADOR.</info>');
            return Command::SUCCESS;
        }

        $output->writeln(sprintf('<info>Gerando SQL para %d cliente(s) não registrado(s)...</info>', count($unregistered)));
        $output->writeln('');

        $unregisteredCodes = array_map(fn($c) => (int) $c['erp_code'], $unregistered);
        $sql = $this->b2bRegistration->generateRegistrationSQL($unregisteredCodes);

        // Save to file
        $filePath = BP . '/var/log/erp_register_clients_' . date('Ymd_His') . '.sql';
        file_put_contents($filePath, $sql);

        $output->writeln($sql);
        $output->writeln('');
        $output->writeln(sprintf('<info>SQL salvo em: %s</info>', $filePath));
        $output->writeln('<comment>Envie este arquivo para o DBA do Sectra para execução manual.</comment>');

        return Command::SUCCESS;
    }

    /**
     * Gather diagnostic information about held orders.
     *
     * @return array<int, array{
     *   increment_id: string, status: string, customer_id: int,
     *   customer_name: string, taxvat: string, taxvat_masked: string,
     *   erp_code: string, reason: string, created_at: string,
     *   order_entity_id: int
     * }>
     */
    private function getHeldOrders(int $limit): array
    {
        $collection = $this->orderCollectionFactory->create();
        $collection->addFieldToFilter('status', ['in' => ['pending', 'processing', 'new']]);
        $collection->addFieldToFilter('customer_id', ['notnull' => true]);
        $collection->setPageSize($limit);
        $collection->setOrder('created_at', 'ASC');

        $result = [];

        foreach ($collection as $order) {
            $customerId = (int) $order->getCustomerId();
            $erpCode = $order->getData('customer_erp_code');

            // Skip orders that already have erp_code AND client is registered in Sectra
            if (!empty($erpCode) && is_numeric($erpCode) && (int) $erpCode > 0) {
                try {
                    if ($this->b2bRegistration->isClientRegistered((int) $erpCode)) {
                        continue; // Not held — will be pulled by Sectra normally
                    }
                } catch (\Exception $e) {
                    // If check fails, include in list for safety
                }
            }

            // Determine reason
            $reason = 'Sem erp_code';
            if (!empty($erpCode) && (int) $erpCode > 0) {
                $reason = 'Não registrado no Sectra';
            }

            // Get taxvat
            $taxvat = (string) ($order->getCustomerTaxvat() ?: '');
            if (empty($taxvat) && $customerId) {
                try {
                    $customer = $this->customerRepository->getById($customerId);
                    $taxvat = (string) ($customer->getTaxvat() ?: '');

                    // Also check if customer now has erp_code
                    if (empty($erpCode)) {
                        $attr = $customer->getCustomAttribute('erp_code');
                        if ($attr && $attr->getValue() && is_numeric($attr->getValue()) && (int) $attr->getValue() > 0) {
                            $erpCode = $attr->getValue();
                            $reason = 'Não registrado no Sectra';
                        }
                    }
                } catch (\Exception $e) {
                    // Customer may not exist
                }
            }

            $taxvatMasked = '';
            if (!empty($taxvat)) {
                $clean = preg_replace('/[^0-9]/', '', $taxvat);
                if (strlen($clean) === 11) {
                    $taxvatMasked = substr($clean, 0, 3) . '.***.***-' . substr($clean, -2);
                } elseif (strlen($clean) === 14) {
                    $taxvatMasked = substr($clean, 0, 4) . '****/****-' . substr($clean, -2);
                } else {
                    $taxvatMasked = substr($clean, 0, 4) . '***';
                }
            }

            $result[] = [
                'increment_id' => $order->getIncrementId(),
                'status' => $order->getStatus(),
                'customer_id' => $customerId,
                'customer_name' => trim(($order->getCustomerFirstname() ?? '') . ' ' . ($order->getCustomerLastname() ?? '')),
                'taxvat' => $taxvat,
                'taxvat_masked' => $taxvatMasked ?: 'N/A',
                'erp_code' => $erpCode ?: '',
                'reason' => $reason,
                'created_at' => $order->getCreatedAt(),
                'order_entity_id' => (int) $order->getEntityId(),
            ];
        }

        return $result;
    }

    /**
     * Stamp erp_code on an order by entity_id.
     */
    private function stampErpCodeOnOrder(int $entityId, int $erpCode): void
    {
        try {
            $collection = $this->orderCollectionFactory->create();
            $collection->addFieldToFilter('entity_id', ['eq' => $entityId]);
            $order = $collection->getFirstItem();

            if ($order && $order->getId()) {
                $order->setData('customer_erp_code', (string) $erpCode);
                $order->addCommentToStatusHistory(
                    __('[ERP CLI] Código ERP %1 vinculado manualmente via erp:orders:held --action=resolve', $erpCode)
                );
                $order->save();
            }
        } catch (\Exception $e) {
            // Non-critical, log and continue
        }
    }
}
