<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Test\Unit\CommercialPanel\Model\Intelligence;

use GrupoAwamotos\B2B\CommercialPanel\Model\Intelligence\CustomerOrderInsightService;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @covers \GrupoAwamotos\B2B\CommercialPanel\Model\Intelligence\CustomerOrderInsightService
 */
class CustomerOrderInsightServiceTest extends TestCase
{
    public function testGetDaysSinceReturnsNullForEmptyDate(): void
    {
        $service = new CustomerOrderInsightService($this->createMock(ResourceConnection::class));

        $this->assertNull($service->getDaysSince(null));
        $this->assertNull($service->getDaysSince(''));
    }

    public function testGetOrderSummaryReturnsEmptyForNoCustomers(): void
    {
        $service = new CustomerOrderInsightService($this->createMock(ResourceConnection::class));

        $this->assertSame([], $service->getOrderSummaryByCustomer([]));
    }
}
