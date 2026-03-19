<?php
declare(strict_types=1);

namespace GrupoAwamotos\ERPIntegration\Test\Unit\Cron;

use ArrayIterator;
use GrupoAwamotos\B2B\Helper\Data as B2BHelper;
use GrupoAwamotos\B2B\Model\Customer\Attribute\Source\ApprovalStatus;
use GrupoAwamotos\ERPIntegration\Api\CustomerSyncInterface;
use GrupoAwamotos\ERPIntegration\Cron\ResolveCustomerErpCodes;
use GrupoAwamotos\ERPIntegration\Helper\Data as Helper;
use GrupoAwamotos\ERPIntegration\Model\CnpjResolver;
use GrupoAwamotos\ERPIntegration\Model\ResourceModel\SyncLog as SyncLogResource;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\ResourceModel\Customer\Collection;
use Magento\Customer\Model\ResourceModel\Customer\CollectionFactory;
use Magento\Framework\App\Area;
use Magento\Framework\App\State as AppState;
use Magento\Framework\DataObject;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class ResolveCustomerErpCodesTest extends TestCase
{
    private ResolveCustomerErpCodes $subject;
    private CustomerSyncInterface|MockObject $customerSync;
    private CollectionFactory|MockObject $customerCollectionFactory;
    private CustomerRepositoryInterface|MockObject $customerRepository;
    private SyncLogResource|MockObject $syncLogResource;
    private B2BHelper|MockObject $b2bHelper;
    private CnpjResolver $cnpjResolver;
    private Helper|MockObject $helper;
    private LoggerInterface|MockObject $logger;
    private AppState|MockObject $appState;

    protected function setUp(): void
    {
        $this->customerSync = $this->createMock(CustomerSyncInterface::class);
        $this->customerCollectionFactory = $this->createMock(CollectionFactory::class);
        $this->customerRepository = $this->createMock(CustomerRepositoryInterface::class);
        $this->syncLogResource = $this->createMock(SyncLogResource::class);
        $this->b2bHelper = $this->createMock(B2BHelper::class);
        $this->cnpjResolver = new CnpjResolver();
        $this->helper = $this->createMock(Helper::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->appState = $this->createMock(AppState::class);

        $this->subject = new ResolveCustomerErpCodes(
            $this->customerSync,
            $this->customerCollectionFactory,
            $this->customerRepository,
            $this->syncLogResource,
            $this->b2bHelper,
            $this->cnpjResolver,
            $this->helper,
            $this->logger,
            $this->appState
        );
    }

    public function testExecuteLinksOnlyApprovedB2BCustomersWithValidCnpj(): void
    {
        $collection = $this->createMock(Collection::class);
        $filters = [];

        $eligibleCustomer = new DataObject([
            'id' => 10,
            'entity_id' => 10,
            'b2b_cnpj' => '12.345.678/0001-90',
            'taxvat' => '',
        ]);
        $cpfOnlyCustomer = new DataObject([
            'id' => 11,
            'entity_id' => 11,
            'b2b_cnpj' => '',
            'taxvat' => '123.456.789-01',
        ]);

        $this->helper->method('isEnabled')->willReturn(true);
        $this->helper->method('isCustomerSyncEnabled')->willReturn(true);
        $this->appState->expects($this->once())
            ->method('setAreaCode')
            ->with(Area::AREA_ADMINHTML);

        $this->customerCollectionFactory->expects($this->once())
            ->method('create')
            ->willReturn($collection);

        $collection->expects($this->once())
            ->method('addAttributeToSelect')
            ->with(['b2b_cnpj', 'taxvat', 'erp_code', 'b2b_approval_status'])
            ->willReturnSelf();

        $collection->expects($this->exactly(3))
            ->method('addAttributeToFilter')
            ->willReturnCallback(function ($attribute, $condition) use (&$filters, $collection) {
                $filters[] = [$attribute, $condition];
                return $collection;
            });

        $this->b2bHelper->expects($this->exactly(3))
            ->method('getGroupIdByCode')
            ->willReturnMap([
                ['b2b_atacado', B2BHelper::GROUP_B2B_ATACADO],
                ['b2b_vip', B2BHelper::GROUP_B2B_VIP],
                ['b2b_revendedor', B2BHelper::GROUP_B2B_REVENDEDOR],
            ]);

        $collection->expects($this->once())
            ->method('addFieldToFilter')
            ->with('group_id', ['in' => [
                B2BHelper::GROUP_B2B_ATACADO,
                B2BHelper::GROUP_B2B_VIP,
                B2BHelper::GROUP_B2B_REVENDEDOR,
            ]])
            ->willReturnSelf();

        $collection->expects($this->once())
            ->method('setPageSize')
            ->with(200)
            ->willReturnSelf();

        $collection->expects($this->once())
            ->method('setCurPage')
            ->with(1)
            ->willReturnSelf();

        $collection->method('getIterator')
            ->willReturn(new ArrayIterator([$eligibleCustomer, $cpfOnlyCustomer]));

        $this->customerSync->expects($this->once())
            ->method('getErpCustomerByCnpj')
            ->with('12345678000190')
            ->willReturn([
                'CODIGO' => 501,
                'RAZAO' => 'Cliente B2B Teste',
            ]);

        $this->customerSync->expects($this->once())
            ->method('linkMagentoToErp')
            ->with(10, 501)
            ->willReturn(true);

        $this->syncLogResource->expects($this->once())
            ->method('addLog')
            ->with(
                'customer_auto_link',
                'sync',
                'success',
                $this->stringContains('1 processados, 1 vinculados')
            );

        $this->subject->execute();

        $this->assertSame(
            [
                [
                    [
                        ['attribute' => 'b2b_cnpj', 'notnull' => true],
                        ['attribute' => 'taxvat', 'notnull' => true],
                    ],
                    null,
                ],
                ['b2b_approval_status', ['eq' => ApprovalStatus::STATUS_APPROVED]],
                [
                    [
                        ['attribute' => 'erp_code', 'null' => true],
                        ['attribute' => 'erp_code', 'eq' => ''],
                        ['attribute' => 'erp_code', 'eq' => '0'],
                    ],
                    null,
                ],
            ],
            $filters
        );
    }

    public function testExecuteReturnsEarlyWhenCustomerSyncIsDisabled(): void
    {
        $this->helper->method('isEnabled')->willReturn(true);
        $this->helper->method('isCustomerSyncEnabled')->willReturn(false);

        $this->customerCollectionFactory->expects($this->never())->method('create');
        $this->syncLogResource->expects($this->never())->method('addLog');

        $this->subject->execute();

        $this->assertTrue(true);
    }
}
