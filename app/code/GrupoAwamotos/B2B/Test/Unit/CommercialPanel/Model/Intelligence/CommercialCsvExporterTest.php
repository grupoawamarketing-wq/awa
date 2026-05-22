<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Test\Unit\CommercialPanel\Model\Intelligence;

use GrupoAwamotos\B2B\CommercialPanel\Model\Intelligence\CommercialCsvExporter;
use GrupoAwamotos\B2B\CommercialPanel\Model\Intelligence\CommercialReportService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @covers \GrupoAwamotos\B2B\CommercialPanel\Model\Intelligence\CommercialCsvExporter
 */
class CommercialCsvExporterTest extends TestCase
{
    public function testExportContainsSummaryHeaders(): void
    {
        /** @var CommercialReportService&MockObject $reportService */
        $reportService = $this->createMock(CommercialReportService::class);
        $reportService->method('buildReport')->willReturn([
            'filters' => ['date_from' => '2026-05-01', 'date_to' => '2026-05-19'],
            'summary' => [
                'contacts' => 5,
                'tasks' => 3,
                'inactive_customers' => 2,
                'reactivated_customers' => 1,
                'abandoned_carts_treated' => 0,
                'orders_generated' => 4,
            ],
            'goal_progress' => [],
        ]);

        $exporter = new CommercialCsvExporter($reportService);
        $csv = $exporter->exportReportCsv([]);

        $this->assertStringContainsString('Relatório Comercial AWA', $csv);
        $this->assertStringContainsString('Contatos', $csv);
        $this->assertStringContainsString('"5"', $csv);
    }
}
