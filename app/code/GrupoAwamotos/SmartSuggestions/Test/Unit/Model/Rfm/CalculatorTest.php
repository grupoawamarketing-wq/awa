<?php
declare(strict_types=1);

namespace GrupoAwamotos\SmartSuggestions\Test\Unit\Model\Rfm;

use GrupoAwamotos\SmartSuggestions\Model\Rfm\Calculator;
use GrupoAwamotos\ERPIntegration\Model\Rfm\Calculator as ErpRfmCalculator;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Unit tests for RFM Calculator (Adapter)
 *
 * Tests that the SmartSuggestions Calculator correctly delegates to
 * ERPIntegration Calculator and adapts the output format.
 *
 * @covers \GrupoAwamotos\SmartSuggestions\Model\Rfm\Calculator
 */
class CalculatorTest extends TestCase
{
    private Calculator $subject;
    private ErpRfmCalculator&MockObject $erpCalculatorMock;
    private LoggerInterface&MockObject $loggerMock;

    protected function setUp(): void
    {
        $this->erpCalculatorMock = $this->createMock(ErpRfmCalculator::class);
        $this->loggerMock = $this->createMock(LoggerInterface::class);

        $this->subject = new Calculator(
            $this->erpCalculatorMock,
            $this->loggerMock
        );
    }

    /**
     * Get sample data in ERPIntegration Calculator output format (pre-scored)
     *
     * @return array<array<string, mixed>>
     */
    private function getErpSampleData(): array
    {
        return [
            [
                'customer_id' => 1,
                'customer_name' => 'Cliente Champions',
                'trade_name' => 'Champions LTDA',
                'cnpj' => '12345678000100',
                'city' => 'Araraquara',
                'state' => 'SP',
                'email' => 'champ@test.com',
                'phone' => '16999990001',
                'recency' => 5,
                'frequency' => 50,
                'monetary' => 100000.00,
                'r_score' => 5,
                'f_score' => 5,
                'm_score' => 5,
                'rfm_score' => '555',
                'total_score' => 15,
                'segment' => 'champions',
                'last_purchase' => '2026-02-15',
            ],
            [
                'customer_id' => 2,
                'customer_name' => 'Cliente Loyal',
                'trade_name' => 'Loyal ME',
                'cnpj' => '98765432000100',
                'city' => 'São Paulo',
                'state' => 'SP',
                'email' => 'loyal@test.com',
                'phone' => '16999990002',
                'recency' => 15,
                'frequency' => 30,
                'monetary' => 50000.00,
                'r_score' => 4,
                'f_score' => 4,
                'm_score' => 4,
                'rfm_score' => '444',
                'total_score' => 12,
                'segment' => 'loyal',
                'last_purchase' => '2026-02-01',
            ],
            [
                'customer_id' => 3,
                'customer_name' => 'Cliente Lost',
                'trade_name' => 'Lost EPP',
                'cnpj' => '11111111000100',
                'city' => 'Campinas',
                'state' => 'SP',
                'email' => 'lost@test.com',
                'phone' => '16999990003',
                'recency' => 300,
                'frequency' => 2,
                'monetary' => 500.00,
                'r_score' => 1,
                'f_score' => 1,
                'm_score' => 1,
                'rfm_score' => '111',
                'total_score' => 3,
                'segment' => 'lost',
                'last_purchase' => '2025-04-01',
            ],
            [
                'customer_id' => 4,
                'customer_name' => 'Cliente At Risk',
                'trade_name' => 'Risk SA',
                'cnpj' => '22222222000100',
                'city' => 'Ribeirão Preto',
                'state' => 'SP',
                'email' => 'risk@test.com',
                'phone' => '16999990004',
                'recency' => 200,
                'frequency' => 25,
                'monetary' => 40000.00,
                'r_score' => 2,
                'f_score' => 4,
                'm_score' => 4,
                'rfm_score' => '244',
                'total_score' => 10,
                'segment' => 'at_risk',
                'last_purchase' => '2025-08-01',
            ],
            [
                'customer_id' => 5,
                'customer_name' => 'Cliente New',
                'trade_name' => 'New EIRELI',
                'cnpj' => '33333333000100',
                'city' => 'Santos',
                'state' => 'SP',
                'email' => 'new@test.com',
                'phone' => '16999990005',
                'recency' => 3,
                'frequency' => 1,
                'monetary' => 200.00,
                'r_score' => 5,
                'f_score' => 1,
                'm_score' => 1,
                'rfm_score' => '511',
                'total_score' => 7,
                'segment' => 'new_customers',
                'last_purchase' => '2026-02-17',
            ],
        ];
    }

    public function testCalculateAllReturnsArrayOfScoredCustomers(): void
    {
        $this->erpCalculatorMock
            ->method('calculateForAllCustomers')
            ->willReturn($this->getErpSampleData());

        $result = $this->subject->calculateAll();

        $this->assertNotEmpty($result);
        $this->assertCount(5, $result);
    }

    public function testCalculateAllContainsRequiredKeys(): void
    {
        $this->erpCalculatorMock
            ->method('calculateForAllCustomers')
            ->willReturn($this->getErpSampleData());

        $result = $this->subject->calculateAll();
        $firstCustomer = $result[0];

        $requiredKeys = [
            'customer_id', 'customer_name', 'trade_name', 'cnpj',
            'city', 'state', 'recency_days', 'frequency', 'monetary',
            'r_score', 'f_score', 'm_score', 'rfm_score', 'rfm_total',
            'segment', 'last_purchase'
        ];

        foreach ($requiredKeys as $key) {
            $this->assertArrayHasKey($key, $firstCustomer, "Missing key: {$key}");
        }
    }

    public function testCalculateAllScoresAreBetween1And5(): void
    {
        $this->erpCalculatorMock
            ->method('calculateForAllCustomers')
            ->willReturn($this->getErpSampleData());

        $result = $this->subject->calculateAll();

        foreach ($result as $customer) {
            $this->assertGreaterThanOrEqual(1, $customer['r_score']);
            $this->assertLessThanOrEqual(5, $customer['r_score']);
            $this->assertGreaterThanOrEqual(1, $customer['f_score']);
            $this->assertLessThanOrEqual(5, $customer['f_score']);
            $this->assertGreaterThanOrEqual(1, $customer['m_score']);
            $this->assertLessThanOrEqual(5, $customer['m_score']);
        }
    }

    public function testCalculateAllRfmTotalIsSumOfScores(): void
    {
        $this->erpCalculatorMock
            ->method('calculateForAllCustomers')
            ->willReturn($this->getErpSampleData());

        $result = $this->subject->calculateAll();

        foreach ($result as $customer) {
            $expectedTotal = $customer['r_score'] + $customer['f_score'] + $customer['m_score'];
            $this->assertEquals($expectedTotal, $customer['rfm_total']);
        }
    }

    public function testCalculateAllRfmScoreIsConcatenation(): void
    {
        $this->erpCalculatorMock
            ->method('calculateForAllCustomers')
            ->willReturn($this->getErpSampleData());

        $result = $this->subject->calculateAll();

        foreach ($result as $customer) {
            $expected = "{$customer['r_score']}{$customer['f_score']}{$customer['m_score']}";
            $this->assertEquals($expected, $customer['rfm_score']);
        }
    }

    public function testCalculateAllSortsByRfmTotalDescending(): void
    {
        $this->erpCalculatorMock
            ->method('calculateForAllCustomers')
            ->willReturn($this->getErpSampleData());

        $result = $this->subject->calculateAll();

        for ($i = 0; $i < count($result) - 1; $i++) {
            $this->assertGreaterThanOrEqual(
                $result[$i + 1]['rfm_total'],
                $result[$i]['rfm_total'],
                'Results should be sorted by rfm_total descending'
            );
        }
    }

    public function testCalculateAllAdaptsFieldNamesCorrectly(): void
    {
        // Verify the adapter maps ERP field names to SmartSuggestions format:
        // recency → recency_days, total_score → rfm_total, segment → Title Case
        $erpData = [
            [
                'customer_id' => 1,
                'customer_name' => 'Recent',
                'trade_name' => 'Recent',
                'cnpj' => '111',
                'city' => 'SP',
                'state' => 'SP',
                'email' => 'r@test.com',
                'phone' => '11999',
                'recency' => 5,
                'frequency' => 10,
                'monetary' => 5000.00,
                'r_score' => 5,
                'f_score' => 3,
                'm_score' => 3,
                'rfm_score' => '533',
                'total_score' => 11,
                'segment' => 'at_risk',
                'last_purchase' => '2026-02-19',
            ],
        ];

        $this->erpCalculatorMock
            ->method('calculateForAllCustomers')
            ->willReturn($erpData);

        $result = $this->subject->calculateAll();

        $this->assertCount(1, $result);
        $customer = $result[0];

        // Field name mappings
        $this->assertArrayHasKey('recency_days', $customer);
        $this->assertArrayNotHasKey('recency', $customer);
        $this->assertEquals(5, $customer['recency_days']);

        $this->assertArrayHasKey('rfm_total', $customer);
        $this->assertArrayNotHasKey('total_score', $customer);
        $this->assertEquals(11, $customer['rfm_total']);

        // Segment name translation (snake_case → Title Case)
        $this->assertEquals('At Risk', $customer['segment']);
    }

    public function testCalculateAllReturnsEmptyArrayWhenNoData(): void
    {
        $this->erpCalculatorMock
            ->method('calculateForAllCustomers')
            ->willReturn([]);

        $result = $this->subject->calculateAll();

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testCalculateAllCachesResults(): void
    {
        $this->erpCalculatorMock
            ->expects($this->once())
            ->method('calculateForAllCustomers')
            ->willReturn($this->getErpSampleData());

        // Call twice
        $result1 = $this->subject->calculateAll();
        $result2 = $this->subject->calculateAll();

        $this->assertEquals($result1, $result2);
    }

    public function testCalculateAllReturnsEmptyOnException(): void
    {
        $this->erpCalculatorMock
            ->method('calculateForAllCustomers')
            ->willThrowException(new \RuntimeException('DB Error'));

        $this->loggerMock
            ->expects($this->once())
            ->method('error')
            ->with($this->stringContains('Error delegating to ERP Calculator'));

        $result = $this->subject->calculateAll();

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testCalculateForCustomerReturnsCustomerData(): void
    {
        $erpCustomer = $this->getErpSampleData()[0]; // Champions

        $this->erpCalculatorMock
            ->method('getCustomerRfm')
            ->with(1)
            ->willReturn($erpCustomer);

        $result = $this->subject->calculateForCustomer(1);

        $this->assertNotNull($result);
        $this->assertEquals(1, $result['customer_id']);
        $this->assertEquals('Champions LTDA', $result['trade_name']);
        $this->assertEquals('Champions', $result['segment']);
        $this->assertEquals(5, $result['recency_days']);
        $this->assertEquals(15, $result['rfm_total']);
    }

    public function testCalculateForCustomerReturnsNullWhenNotFound(): void
    {
        $this->erpCalculatorMock
            ->method('getCustomerRfm')
            ->with(9999)
            ->willReturn(null);

        $result = $this->subject->calculateForCustomer(9999);

        $this->assertNull($result);
    }

    public function testGetSegmentStatisticsReturnsGroupedData(): void
    {
        $this->erpCalculatorMock
            ->method('calculateForAllCustomers')
            ->willReturn($this->getErpSampleData());

        $result = $this->subject->getSegmentStatistics();

        $this->assertNotEmpty($result);

        foreach ($result as $stat) {
            $this->assertArrayHasKey('segment', $stat);
            $this->assertArrayHasKey('count', $stat);
            $this->assertArrayHasKey('total_revenue', $stat);
            $this->assertArrayHasKey('total_orders', $stat);
            $this->assertArrayHasKey('avg_revenue', $stat);
            $this->assertArrayHasKey('avg_orders', $stat);
            $this->assertArrayHasKey('color', $stat);
            $this->assertArrayHasKey('priority', $stat);
            $this->assertGreaterThan(0, $stat['count']);
        }
    }

    public function testGetSegmentStatisticsSumEqualsTotal(): void
    {
        $this->erpCalculatorMock
            ->method('calculateForAllCustomers')
            ->willReturn($this->getErpSampleData());

        $result = $this->subject->getSegmentStatistics();

        $totalCount = array_sum(array_column($result, 'count'));
        $this->assertEquals(5, $totalCount, 'Sum of segment counts should equal total customers');
    }

    public function testGetCustomersBySegmentFiltersCorrectly(): void
    {
        $this->erpCalculatorMock
            ->method('calculateForAllCustomers')
            ->willReturn($this->getErpSampleData());

        // First get all to know what segments exist
        $all = $this->subject->calculateAll();
        $firstSegment = $all[0]['segment'];

        $result = $this->subject->getCustomersBySegment($firstSegment);

        foreach ($result as $customer) {
            $this->assertEquals($firstSegment, $customer['segment']);
        }
    }

    public function testGetCustomersBySegmentRespectsLimit(): void
    {
        $this->erpCalculatorMock
            ->method('calculateForAllCustomers')
            ->willReturn($this->getErpSampleData());

        $result = $this->subject->getCustomersBySegment('Lost', 1);

        $this->assertLessThanOrEqual(1, count($result));
    }

    public function testGetCustomersBySegmentReturnsEmptyForNonexistentSegment(): void
    {
        $this->erpCalculatorMock
            ->method('calculateForAllCustomers')
            ->willReturn($this->getErpSampleData());

        $result = $this->subject->getCustomersBySegment('NonExistentSegment');

        $this->assertEmpty($result);
    }

    /**
     * @dataProvider segmentRecommendationsProvider
     */
    public function testGetRecommendationsReturnsValidStructure(string $segment): void
    {
        $result = $this->subject->getRecommendations($segment);

        $this->assertArrayHasKey('action', $result);
        $this->assertArrayHasKey('priority', $result);
        $this->assertArrayHasKey('strategies', $result);
        $this->assertArrayHasKey('channels', $result);
        $this->assertArrayHasKey('discount_range', $result);
        $this->assertIsArray($result['strategies']);
        $this->assertIsArray($result['channels']);
        $this->assertNotEmpty($result['strategies']);
        $this->assertNotEmpty($result['channels']);
    }

    /**
     * @return array<string, array<string>>
     */
    public static function segmentRecommendationsProvider(): array
    {
        return [
            'Champions' => ['Champions'],
            'Loyal' => ['Loyal'],
            'Potential Loyalist' => ['Potential Loyalist'],
            'New Customers' => ['New Customers'],
            'Promising' => ['Promising'],
            'Need Attention' => ['Need Attention'],
            'At Risk' => ['At Risk'],
            "Can't Lose" => ["Can't Lose"],
            'Hibernating' => ['Hibernating'],
            'Lost' => ['Lost'],
        ];
    }

    public function testGetRecommendationsReturnsDefaultForUnknownSegment(): void
    {
        $result = $this->subject->getRecommendations('UnknownSegment');

        $this->assertEquals('Engajamento geral', $result['action']);
        $this->assertEquals('Média', $result['priority']);
    }

    public function testSegmentAssignmentIsConsistent(): void
    {
        $this->erpCalculatorMock
            ->method('calculateForAllCustomers')
            ->willReturn($this->getErpSampleData());

        $result = $this->subject->calculateAll();

        $validSegments = [
            'Champions', 'Loyal', 'Potential Loyalist', 'New Customers',
            'Promising', 'Need Attention', 'At Risk', "Can't Lose",
            'Hibernating', 'Lost', 'Other'
        ];

        foreach ($result as $customer) {
            $this->assertContains(
                $customer['segment'],
                $validSegments,
                "Invalid segment: {$customer['segment']}"
            );
        }
    }

    public function testCustomerIdIsInteger(): void
    {
        $this->erpCalculatorMock
            ->method('calculateForAllCustomers')
            ->willReturn($this->getErpSampleData());

        $result = $this->subject->calculateAll();

        foreach ($result as $customer) {
            $this->assertIsInt($customer['customer_id']);
            $this->assertIsInt($customer['recency_days']);
            $this->assertIsInt($customer['frequency']);
            $this->assertIsFloat($customer['monetary']);
        }
    }
}
