<?php

declare(strict_types=1);

namespace GrupoAwamotos\ERPIntegration\Test\Unit\Model\Validator;

use GrupoAwamotos\ERPIntegration\Model\Validator\StockValidator;
use Magento\CatalogInventory\Api\Data\StockItemInterface;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class StockValidatorTest extends TestCase
{
    private StockRegistryInterface&MockObject $stockRegistry;
    private LoggerInterface&MockObject $logger;
    private StockValidator $validator;

    protected function setUp(): void
    {
        $this->stockRegistry = $this->createMock(StockRegistryInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->validator = new StockValidator($this->stockRegistry, $this->logger);
    }

    public function testValidateDoesNotTriggerAnomalyLookup(): void
    {
        $this->stockRegistry->expects($this->never())->method('getStockItemBySku');

        $result = $this->validator->validate([
            'MATERIAL' => 'SKU-001',
            'QTDE' => 250,
        ]);

        $this->assertTrue($result->isValid());
        $this->assertFalse($result->getField('anomaly_detected', false));
    }

    public function testDetectAnomalyUsesProvidedBaselineWithoutRegistryLookup(): void
    {
        $this->stockRegistry->expects($this->never())->method('getStockItemBySku');

        $result = $this->validator->detectAnomaly('SKU-001', 40.0, 500.0);

        $this->assertTrue($result->getField('anomaly_detected', false));
        $this->assertSame(500.0, $result->getField('previous_quantity'));
        $this->assertSame(-92.0, $result->getField('anomaly_percent_change'));
    }

    public function testDetectAnomalyFallsBackToMagentoQtyWhenBaselineMissing(): void
    {
        $stockItem = $this->createMock(StockItemInterface::class);
        $stockItem->method('getQty')->willReturn(50.0);

        $this->stockRegistry->expects($this->once())
            ->method('getStockItemBySku')
            ->with('SKU-002')
            ->willReturn($stockItem);

        $result = $this->validator->detectAnomaly('SKU-002', 30.0);

        $this->assertFalse($result->getField('anomaly_detected', false));
    }
}
