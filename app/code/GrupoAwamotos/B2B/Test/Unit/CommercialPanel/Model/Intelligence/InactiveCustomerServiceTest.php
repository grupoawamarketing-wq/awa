<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Test\Unit\CommercialPanel\Model\Intelligence;

use GrupoAwamotos\B2B\CommercialPanel\Api\PortfolioScopeInterface;
use GrupoAwamotos\B2B\CommercialPanel\Model\Config\TaskConfig;
use GrupoAwamotos\B2B\CommercialPanel\Model\Intelligence\CustomerOrderInsightService;
use GrupoAwamotos\B2B\CommercialPanel\Model\Intelligence\InactiveCustomerService;
use GrupoAwamotos\B2B\Model\ResourceModel\CustomerAttendant\Collection as CustomerAttendantCollection;
use GrupoAwamotos\B2B\Model\ResourceModel\CustomerAttendant\CollectionFactory as CustomerAttendantCollectionFactory;
use Magento\Customer\Model\ResourceModel\Customer\CollectionFactory as CustomerCollectionFactory;
use Magento\Framework\App\ResourceConnection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @covers \GrupoAwamotos\B2B\CommercialPanel\Model\Intelligence\InactiveCustomerService
 */
class InactiveCustomerServiceTest extends TestCase
{
    private PortfolioScopeInterface&MockObject $portfolioScope;
    private CustomerAttendantCollectionFactory&MockObject $mappingFactory;
    private CustomerCollectionFactory&MockObject $customerCollectionFactory;
    private CustomerOrderInsightService&MockObject $orderInsight;

    protected function setUp(): void
    {
        $this->portfolioScope = $this->createMock(PortfolioScopeInterface::class);
        $this->mappingFactory = $this->createMock(CustomerAttendantCollectionFactory::class);
        $this->customerCollectionFactory = $this->createMock(CustomerCollectionFactory::class);
        $this->orderInsight = $this->createMock(CustomerOrderInsightService::class);
    }

    public function testMarkInProgressDeniedOutsidePortfolio(): void
    {
        $this->portfolioScope->method('canAccessCustomer')->with(99)->willReturn(false);

        $service = new InactiveCustomerService(
            $this->portfolioScope,
            $this->mappingFactory,
            $this->customerCollectionFactory,
            $this->orderInsight,
            $this->createMock(ResourceConnection::class)
        );

        $this->assertFalse($service->markInProgress(99));
    }

    public function testEmptyPortfolioReturnsNoInactiveCustomers(): void
    {
        $this->portfolioScope->method('getVisibleCustomerIds')->willReturn([]);

        $service = new InactiveCustomerService(
            $this->portfolioScope,
            $this->mappingFactory,
            $this->customerCollectionFactory,
            $this->orderInsight,
            $this->createMock(ResourceConnection::class)
        );

        $this->assertSame([], $service->getInactiveCustomers(30));
    }
}
