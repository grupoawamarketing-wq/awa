<?php
declare(strict_types=1);

namespace GrupoAwamotos\ERPIntegration\Cron;

use GrupoAwamotos\B2B\Helper\Data as B2BHelper;
use GrupoAwamotos\B2B\Model\Customer\Attribute\Source\ApprovalStatus;
use GrupoAwamotos\ERPIntegration\Api\CustomerSyncInterface;
use GrupoAwamotos\ERPIntegration\Helper\Data as Helper;
use GrupoAwamotos\ERPIntegration\Model\CnpjResolver;
use GrupoAwamotos\ERPIntegration\Model\ResourceModel\SyncLog as SyncLogResource;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\ResourceModel\Customer\CollectionFactory;
use Magento\Framework\App\State as AppState;
use Psr\Log\LoggerInterface;

/**
 * Cron: Preventive auto-link of Magento B2B customers to ERP by CNPJ.
 *
 * Finds customers that have a valid CNPJ in b2b_cnpj/taxvat but no erp_code
 * attribute, looks them up in Sectra's FN_FORNECEDORES (read-only) and
 * persists the mapping in entity_map + customer attribute for future order
 * placement.
 *
 * Schedule: every 3 hours (0 * /3 * * *)
 * Batch: 200 customers per execution
 */
class ResolveCustomerErpCodes
{
    private const BATCH_SIZE = 200;

    private CustomerSyncInterface $customerSync;
    private CollectionFactory $customerCollectionFactory;
    private CustomerRepositoryInterface $customerRepository;
    private SyncLogResource $syncLogResource;
    private B2BHelper $b2bHelper;
    private CnpjResolver $cnpjResolver;
    private Helper $helper;
    private LoggerInterface $logger;
    private AppState $appState;

    public function __construct(
        CustomerSyncInterface $customerSync,
        CollectionFactory $customerCollectionFactory,
        CustomerRepositoryInterface $customerRepository,
        SyncLogResource $syncLogResource,
        B2BHelper $b2bHelper,
        CnpjResolver $cnpjResolver,
        Helper $helper,
        LoggerInterface $logger,
        AppState $appState
    ) {
        $this->customerSync = $customerSync;
        $this->customerCollectionFactory = $customerCollectionFactory;
        $this->customerRepository = $customerRepository;
        $this->syncLogResource = $syncLogResource;
        $this->b2bHelper = $b2bHelper;
        $this->cnpjResolver = $cnpjResolver;
        $this->helper = $helper;
        $this->logger = $logger;
        $this->appState = $appState;
    }

    public function execute(): void
    {
        if (!$this->helper->isEnabled() || !$this->helper->isCustomerSyncEnabled()) {
            return;
        }

        try {
            $this->appState->setAreaCode(\Magento\Framework\App\Area::AREA_ADMINHTML);
        } catch (\Exception $e) {
            // Area already set
        }

        $this->logger->info('[ERP Cron] Starting customer ERP code resolution (auto-link by CNPJ)');

        $customers = $this->getCustomersWithoutErpCode();
        $total = count($customers);

        if ($total === 0) {
            $this->logger->info('[ERP Cron] No customers without erp_code found');
            return;
        }

        $linked = 0;
        $notFound = 0;
        $errors = 0;

        foreach ($customers as $customerData) {
            $customerId = (int) $customerData['entity_id'];
            $cnpj = (string) $customerData['cnpj'];

            try {
                $erpCustomer = $this->customerSync->getErpCustomerByCnpj($cnpj);

                if (!$erpCustomer || empty($erpCustomer['CODIGO'])) {
                    $notFound++;
                    continue;
                }

                $erpCode = (int) $erpCustomer['CODIGO'];
                $result = $this->customerSync->linkMagentoToErp($customerId, $erpCode);

                if ($result) {
                    $linked++;
                    $this->logger->info('[ERP Cron] Auto-linked customer', [
                        'customer_id' => $customerId,
                        'erp_code' => $erpCode,
                        'razao' => $erpCustomer['RAZAO'] ?? '',
                    ]);
                } else {
                    $errors++;
                }
            } catch (\Exception $e) {
                $errors++;
                $this->logger->warning('[ERP Cron] Auto-link failed for customer ' . $customerId, [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->logger->info('[ERP Cron] Customer ERP code resolution completed', [
            'total_processed' => $total,
            'linked' => $linked,
            'not_found_in_erp' => $notFound,
            'errors' => $errors,
        ]);

        $this->syncLogResource->addLog(
            'customer_auto_link',
            'sync',
            $errors > 0 ? 'partial' : 'success',
            sprintf(
                'Auto-link por CNPJ: %d processados, %d vinculados, %d não encontrados, %d erros',
                $total,
                $linked,
                $notFound,
                $errors
            )
        );
    }

    /**
     * Find customers that have a valid CNPJ but no erp_code attribute.
     * Uses raw collection to avoid loading full customer objects.
     *
     * @return array<int, array{entity_id: int, cnpj: string}>
     */
    private function getCustomersWithoutErpCode(): array
    {
        $collection = $this->customerCollectionFactory->create();
        $collection->addAttributeToSelect(['b2b_cnpj', 'taxvat', 'erp_code', 'b2b_approval_status']);
        $collection->addAttributeToFilter(
            [
                ['attribute' => 'b2b_cnpj', 'notnull' => true],
                ['attribute' => 'taxvat', 'notnull' => true],
            ]
        );
        $collection->addAttributeToFilter('b2b_approval_status', ['eq' => ApprovalStatus::STATUS_APPROVED]);
        $collection->addFieldToFilter('group_id', ['in' => $this->getEligibleB2BGroupIds()]);

        // Filter: erp_code is null or empty or zero
        $collection->addAttributeToFilter(
            [
                ['attribute' => 'erp_code', 'null' => true],
                ['attribute' => 'erp_code', 'eq' => ''],
                ['attribute' => 'erp_code', 'eq' => '0'],
            ]
        );

        $collection->setPageSize(self::BATCH_SIZE);
        $collection->setCurPage(1);

        $result = [];
        foreach ($collection as $customer) {
            $cnpj = $this->cnpjResolver->resolveFromValues(
                (string) $customer->getData('b2b_cnpj'),
                (string) $customer->getData('taxvat')
            );
            if ($cnpj !== '') {
                $result[] = [
                    'entity_id' => (int) $customer->getId(),
                    'cnpj' => $cnpj,
                ];
            }
        }

        return $result;
    }

    /**
     * @return int[]
     */
    private function getEligibleB2BGroupIds(): array
    {
        $groupIds = [
            $this->b2bHelper->getGroupIdByCode('b2b_atacado'),
            $this->b2bHelper->getGroupIdByCode('b2b_vip'),
            $this->b2bHelper->getGroupIdByCode('b2b_revendedor'),
        ];

        $groupIds = array_values(array_unique(array_filter($groupIds, static fn ($groupId): bool => is_int($groupId) && $groupId > 0)));

        if ($groupIds !== []) {
            return $groupIds;
        }

        return [
            B2BHelper::GROUP_B2B_ATACADO,
            B2BHelper::GROUP_B2B_VIP,
            B2BHelper::GROUP_B2B_REVENDEDOR,
        ];
    }
}
