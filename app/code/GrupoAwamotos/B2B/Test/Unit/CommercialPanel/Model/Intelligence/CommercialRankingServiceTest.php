<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Test\Unit\CommercialPanel\Model\Intelligence;

use GrupoAwamotos\B2B\CommercialPanel\Api\PortfolioScopeInterface;
use GrupoAwamotos\B2B\CommercialPanel\Model\Intelligence\CommercialRankingService;
use GrupoAwamotos\B2B\CommercialPanel\Model\Intelligence\CommercialGoalProgressService;
use GrupoAwamotos\B2B\CommercialPanel\Model\Config\TaskConfig;
use GrupoAwamotos\B2B\CommercialPanel\Model\ResourceModel\CommercialTask\CollectionFactory as TaskCollectionFactory;
use GrupoAwamotos\B2B\CommercialPanel\Model\ResourceModel\ContactLog\CollectionFactory as ContactLogCollectionFactory;
use GrupoAwamotos\B2B\Model\ResourceModel\CustomerAttendant\CollectionFactory as CustomerAttendantCollectionFactory;
use Magento\Framework\App\ResourceConnection;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @covers \GrupoAwamotos\B2B\CommercialPanel\Model\Intelligence\CommercialRankingService
 */
class CommercialRankingServiceTest extends TestCase
{
    public function testSellerCannotAccessRanking(): void
    {
        /** @var PortfolioScopeInterface&MockObject $scope */
        $scope = $this->createMock(PortfolioScopeInterface::class);
        $scope->method('canViewAllPortfolios')->willReturn(false);
        $scope->method('canBypassPortfolioScope')->willReturn(false);

        $service = new CommercialRankingService(
            $scope,
            $this->createMock(TaskCollectionFactory::class),
            $this->createMock(ContactLogCollectionFactory::class),
            $this->createMock(OrderCollectionFactory::class),
            $this->createMock(CustomerAttendantCollectionFactory::class),
            $this->createMock(CommercialGoalProgressService::class),
            $this->createMock(ResourceConnection::class),
            $this->createMock(TaskConfig::class)
        );

        $this->assertFalse($service->isRankingAllowed());
        $this->assertSame([], $service->getRanking());
    }

    public function testSupervisorCanAccessRanking(): void
    {
        /** @var PortfolioScopeInterface&MockObject $scope */
        $scope = $this->createMock(PortfolioScopeInterface::class);
        $scope->method('canViewAllPortfolios')->willReturn(true);
        $scope->method('canBypassPortfolioScope')->willReturn(false);
        $scope->method('getVisibleAttendantIds')->willReturn([]);

        $service = new CommercialRankingService(
            $scope,
            $this->createMock(TaskCollectionFactory::class),
            $this->createMock(ContactLogCollectionFactory::class),
            $this->createMock(OrderCollectionFactory::class),
            $this->createMock(CustomerAttendantCollectionFactory::class),
            $this->createMock(CommercialGoalProgressService::class),
            $this->createMock(ResourceConnection::class),
            $this->createMock(TaskConfig::class)
        );

        $this->assertTrue($service->isRankingAllowed());
    }
}
