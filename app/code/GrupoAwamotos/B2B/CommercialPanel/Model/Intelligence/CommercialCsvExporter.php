<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\CommercialPanel\Model\Intelligence;

/**
 * Exportação CSV de relatórios comerciais respeitando escopo já aplicado pelo ReportService.
 */
class CommercialCsvExporter
{
    public function __construct(
        private readonly CommercialReportService $reportService
    ) {
    }

    /**
     * @param array<string, mixed> $filters
     */
    public function exportReportCsv(array $filters = []): string
    {
        $report = $this->reportService->buildReport($filters);
        $lines = [];
        $lines[] = $this->csvLine(['Relatório Comercial AWA']);
        $lines[] = $this->csvLine(['Período', ($report['filters']['date_from'] ?? '') . ' a ' . ($report['filters']['date_to'] ?? '')]);
        $lines[] = '';
        $lines[] = $this->csvLine(['Indicador', 'Valor']);
        foreach ($report['summary'] as $key => $value) {
            $lines[] = $this->csvLine([$this->label($key), (string) $value]);
        }

        if (!empty($report['goal_progress'])) {
            $lines[] = '';
            $lines[] = $this->csvLine([
                'Vendedora', 'Mês', 'Meta Fatur.', 'Realiz. Fatur.', '% Fatur.',
                'Meta Contatos', 'Realiz. Contatos', 'Meta Reativ.', 'Realiz. Reativ.',
            ]);
            foreach ($report['goal_progress'] as $row) {
                $lines[] = $this->csvLine([
                    $row['attendant_name'] ?? '',
                    $row['period_month'] ?? '',
                    (string) ($row['revenue_goal'] ?? 0),
                    (string) ($row['revenue_actual'] ?? 0),
                    (string) ($row['revenue_pct'] ?? 0),
                    (string) ($row['contacts_goal'] ?? 0),
                    (string) ($row['contacts_actual'] ?? 0),
                    (string) ($row['reactivated_goal'] ?? 0),
                    (string) ($row['reactivated_actual'] ?? 0),
                ]);
            }
        }

        return implode("\n", $lines) . "\n";
    }

    /**
     * @param string[] $fields
     */
    private function csvLine(array $fields): string
    {
        return implode(';', array_map(static function (string $field): string {
            $field = str_replace('"', '""', $field);

            return '"' . $field . '"';
        }, $fields));
    }

    private function label(string $key): string
    {
        return match ($key) {
            'contacts' => 'Contatos',
            'tasks' => 'Tarefas',
            'inactive_customers' => 'Clientes parados (30d+)',
            'reactivated_customers' => 'Clientes reativados',
            'abandoned_carts_treated' => 'Carrinhos tratados',
            'orders_generated' => 'Pedidos gerados',
            default => $key,
        };
    }
}
