<?php

declare(strict_types=1);

namespace GrupoAwamotos\AbandonedCart\Test\Unit\Model;

use GrupoAwamotos\AbandonedCart\Model\CouponGenerator;
use Magento\Customer\Model\Group;
use Magento\Customer\Model\ResourceModel\Group\Collection as GroupCollection;
use Magento\Customer\Model\ResourceModel\Group\CollectionFactory as CustomerGroupCollectionFactory;
use Magento\SalesRule\Model\Coupon;
use Magento\SalesRule\Model\CouponFactory;
use Magento\SalesRule\Model\ResourceModel\Coupon as CouponResource;
use Magento\SalesRule\Model\ResourceModel\Rule as RuleResource;
use Magento\SalesRule\Model\Rule;
use Magento\SalesRule\Model\RuleFactory;
use Magento\Store\Api\Data\WebsiteInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * @covers \GrupoAwamotos\AbandonedCart\Model\CouponGenerator
 */
class CouponGeneratorTest extends TestCase
{
    private CouponGenerator $generator;
    private RuleFactory&MockObject $ruleFactory;
    private CouponFactory&MockObject $couponFactory;
    private RuleResource&MockObject $ruleResource;
    private CouponResource&MockObject $couponResource;
    private StoreManagerInterface&MockObject $storeManager;
    private CustomerGroupCollectionFactory&MockObject $groupCollectionFactory;
    private LoggerInterface&MockObject $logger;

    protected function setUp(): void
    {
        $this->ruleFactory = $this->createMock(RuleFactory::class);
        $this->couponFactory = $this->createMock(CouponFactory::class);
        $this->ruleResource = $this->createMock(RuleResource::class);
        $this->couponResource = $this->createMock(CouponResource::class);
        $this->storeManager = $this->createMock(StoreManagerInterface::class);
        $this->groupCollectionFactory = $this->createMock(CustomerGroupCollectionFactory::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->generator = new CouponGenerator(
            $this->ruleFactory,
            $this->couponFactory,
            $this->ruleResource,
            $this->couponResource,
            $this->storeManager,
            $this->logger,
            $this->groupCollectionFactory
        );
    }

    // ====================================================================
    // generate — percent
    // ====================================================================

    public function testGeneratePercentCouponReturnsCode(): void
    {
        $this->setupWebsitesAndGroups();

        $rule = $this->createRuleMock();
        $this->ruleFactory->method('create')->willReturn($rule);
        $this->ruleResource->expects($this->once())->method('save')->with($rule);

        $code = $this->generator->generate(10.0, 'percent', 42, 'test@test.com');

        $this->assertStringStartsWith('VOLTA', $code);
        $this->assertStringEndsWith('42', $code);
    }

    // ====================================================================
    // generate — fixed
    // ====================================================================

    public function testGenerateFixedCouponReturnsCode(): void
    {
        $this->setupWebsitesAndGroups();

        $rule = $this->createRuleMock();
        $this->ruleFactory->method('create')->willReturn($rule);
        $this->ruleResource->expects($this->once())->method('save');

        $code = $this->generator->generate(25.0, 'fixed', 100);

        $this->assertStringStartsWith('VOLTA', $code);
        $this->assertStringEndsWith('100', $code);
    }

    // ====================================================================
    // generate — exception
    // ====================================================================

    public function testGenerateThrowsWhenRuleSaveFails(): void
    {
        $this->setupWebsitesAndGroups();

        $rule = $this->createRuleMock();
        $this->ruleFactory->method('create')->willReturn($rule);
        $this->ruleResource->method('save')
            ->willThrowException(new \Exception('DB error'));

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('DB error');

        $this->generator->generate(10.0, 'percent', 42);
    }

    // ====================================================================
    // generate — no customer groups
    // ====================================================================

    public function testGenerateThrowsWhenNoCustomerGroups(): void
    {
        $website = $this->createMock(WebsiteInterface::class);
        $website->method('getId')->willReturn(1);
        $this->storeManager->method('getWebsites')->willReturn([$website]);

        // Empty group collection
        $collection = $this->createMock(GroupCollection::class);
        $collection->method('getItems')->willReturn([]);
        $this->groupCollectionFactory->method('create')->willReturn($collection);

        $rule = $this->createRuleMock();
        $this->ruleFactory->method('create')->willReturn($rule);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Nenhum grupo de cliente');

        $this->generator->generate(10.0, 'percent', 42);
    }

    // ====================================================================
    // getCouponByCode — found
    // ====================================================================

    public function testGetCouponByCodeReturnsDataWhenFound(): void
    {
        $coupon = $this->createMock(Coupon::class);
        $coupon->method('getId')->willReturn(10);
        $coupon->method('getRuleId')->willReturn(5);
        $coupon->method('getCode')->willReturn('VOLTA123');
        $coupon->method('getUsageLimit')->willReturn(1);
        $coupon->method('getTimesUsed')->willReturn(0);
        $coupon->method('getExpirationDate')->willReturn('2026-03-01');

        $this->couponFactory->method('create')->willReturn($coupon);
        $this->couponResource->method('load')->with($coupon, 'VOLTA123', 'code');

        $result = $this->generator->getCouponByCode('VOLTA123');

        $this->assertIsArray($result);
        $this->assertSame(10, $result['coupon_id']);
        $this->assertSame(5, $result['rule_id']);
        $this->assertSame('VOLTA123', $result['code']);
        $this->assertSame(1, $result['usage_limit']);
        $this->assertSame(0, $result['times_used']);
        $this->assertSame('2026-03-01', $result['expiration_date']);
    }

    // ====================================================================
    // getCouponByCode — not found
    // ====================================================================

    public function testGetCouponByCodeReturnsNullWhenNotFound(): void
    {
        $coupon = $this->createMock(Coupon::class);
        $coupon->method('getId')->willReturn(null);

        $this->couponFactory->method('create')->willReturn($coupon);

        $this->assertNull($this->generator->getCouponByCode('INVALID'));
    }

    // ====================================================================
    // getCouponByCode — exception
    // ====================================================================

    public function testGetCouponByCodeReturnsNullOnException(): void
    {
        $this->couponFactory->method('create')
            ->willThrowException(new \Exception('error'));

        $this->assertNull($this->generator->getCouponByCode('VOLTA123'));
    }

    // ====================================================================
    // invalidateCoupon — success
    // ====================================================================

    public function testInvalidateCouponReturnsTrueWhenSuccess(): void
    {
        $coupon = $this->createMock(Coupon::class);
        $coupon->method('getId')->willReturn(10);
        $coupon->method('getRuleId')->willReturn(5);

        $this->couponFactory->method('create')->willReturn($coupon);

        $rule = $this->createRuleMock();
        $rule->method('getId')->willReturn(5);

        $this->ruleFactory->method('create')->willReturn($rule);
        $this->ruleResource->expects($this->atLeastOnce())->method('save');

        $this->assertTrue($this->generator->invalidateCoupon('VOLTA123'));
    }

    // ====================================================================
    // invalidateCoupon — not found
    // ====================================================================

    public function testInvalidateCouponReturnsFalseWhenNotFound(): void
    {
        $coupon = $this->createMock(Coupon::class);
        $coupon->method('getId')->willReturn(null);

        $this->couponFactory->method('create')->willReturn($coupon);

        $this->assertFalse($this->generator->invalidateCoupon('INVALID'));
    }

    // ====================================================================
    // invalidateCoupon — exception
    // ====================================================================

    public function testInvalidateCouponReturnsFalseOnException(): void
    {
        $this->couponFactory->method('create')
            ->willThrowException(new \Exception('error'));

        $this->assertFalse($this->generator->invalidateCoupon('VOLTA123'));
    }

    // ====================================================================
    // Helpers
    // ====================================================================

    private function setupWebsitesAndGroups(): void
    {
        $website = $this->createMock(WebsiteInterface::class);
        $website->method('getId')->willReturn(1);
        $this->storeManager->method('getWebsites')->willReturn([$website]);

        $group1 = $this->createMock(Group::class);
        $group1->method('getId')->willReturn(0);
        $group2 = $this->createMock(Group::class);
        $group2->method('getId')->willReturn(1);

        $collection = $this->createMock(GroupCollection::class);
        $collection->method('getItems')->willReturn([$group1, $group2]);
        $this->groupCollectionFactory->method('create')->willReturn($collection);
    }

    /**
     * Create a Rule mock with magic setter methods added via addMethods().
     * SalesRule\Model\Rule inherits from DataObject which uses __call() for setters.
     */
    private function createRuleMock(): Rule&MockObject
    {
        $rule = $this->getMockBuilder(Rule::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getId'])
            ->addMethods([
                'setName',
                'setDescription',
                'setIsActive',
                'setWebsiteIds',
                'setCustomerGroupIds',
                'setCouponType',
                'setCouponCode',
                'setUsesPerCoupon',
                'setUsesPerCustomer',
                'setFromDate',
                'setToDate',
                'setSortOrder',
                'setStopRulesProcessing',
                'setSimpleAction',
                'setDiscountAmount',
                'setDiscountQty',
                'setDiscountStep',
                'setSimpleFreeShipping',
                'setApplyToShipping',
            ])
            ->getMock();

        $rule->method('setName')->willReturnSelf();
        $rule->method('setDescription')->willReturnSelf();
        $rule->method('setIsActive')->willReturnSelf();
        $rule->method('setWebsiteIds')->willReturnSelf();
        $rule->method('setCustomerGroupIds')->willReturnSelf();
        $rule->method('setCouponType')->willReturnSelf();
        $rule->method('setCouponCode')->willReturnSelf();
        $rule->method('setUsesPerCoupon')->willReturnSelf();
        $rule->method('setUsesPerCustomer')->willReturnSelf();
        $rule->method('setFromDate')->willReturnSelf();
        $rule->method('setToDate')->willReturnSelf();
        $rule->method('setSortOrder')->willReturnSelf();
        $rule->method('setStopRulesProcessing')->willReturnSelf();
        $rule->method('setSimpleAction')->willReturnSelf();
        $rule->method('setDiscountAmount')->willReturnSelf();
        $rule->method('setDiscountQty')->willReturnSelf();
        $rule->method('setDiscountStep')->willReturnSelf();
        $rule->method('setSimpleFreeShipping')->willReturnSelf();
        $rule->method('setApplyToShipping')->willReturnSelf();

        return $rule;
    }
}
