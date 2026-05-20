<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Platform\Dashboard\Export;

/**
 * Export CSV read-only do snapshot de KPIs do dashboard.
 */
class ExecutiveDashboardCsvExporter
{
    private const CSV_DELIMITER = ';';
    private const CSV_ENCLOSURE = '"';
    private const CSV_ESCAPE = '\\';

    /**
     * @param array<string, mixed> $dashboard
     */
    public function export(array $dashboard): string
    {
        $handle = fopen('php://temp', 'r+');
        if ($handle === false) {
            return '';
        }

        $this->putCsvRow($handle, ['KPI', 'Valor', 'Disponível']);

        $rows = $this->flattenKpis($dashboard);
        foreach ($rows as $row) {
            $this->putCsvRow($handle, $row);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv !== false ? $csv : '';
    }

    /**
     * @param array<string, mixed> $dashboard
     * @return list<array{0: string, 1: string, 2: string}>
     */
    private function flattenKpis(array $dashboard): array
    {
        $rows = [];
        $sections = [
            'orders' => [
                'orders_today' => 'Pedidos B2B hoje',
                'orders_period' => 'Pedidos B2B no período',
                'revenue_period' => 'Receita Magento bruta no período',
                'avg_ticket' => 'Ticket médio B2B',
            ],
            'sectra' => [
                'awaiting_erp' => 'Pedidos aguardando ERP/Sectra',
                'blocked' => 'Pedidos bloqueados',
                'non_importable' => 'Pedidos não importáveis',
                'ready_for_import' => 'Prontos para Sectra',
                'imported' => 'Importados ERP',
                'import_failed' => 'Erro importação',
            ],
            'customers' => [
                'pending_erp_validation' => 'Clientes pending_erp_validation',
                'validated_in_erp' => 'Clientes validated_in_erp',
                'commercial_pending' => 'Pendências comerciais',
                'new_customers_period' => 'Clientes novos no período',
            ],
            'commercial' => [
                'open_tasks' => 'Tarefas abertas',
                'active_attendants' => 'Atendentes ativos',
                'abandoned_carts' => 'Carrinhos abandonados',
                'pending_b2b_approval' => 'Aprovação cadastro pendente',
            ],
            'health' => [
                'no_phone' => 'Clientes sem telefone',
                'no_razao_social' => 'Sem razão social',
                'no_erp_status' => 'Sem status ERP',
                'total_with_cnpj' => 'Total com CNPJ',
            ],
        ];

        foreach ($sections as $sectionKey => $kpis) {
            $section = $dashboard[$sectionKey] ?? [];
            foreach ($kpis as $key => $label) {
                $kpi = $section[$key] ?? null;
                if (!is_array($kpi)) {
                    continue;
                }
                $rows[] = [
                    $label,
                    (string) ($kpi['formatted'] ?? $kpi['value'] ?? ''),
                    !empty($kpi['available']) ? 'sim' : 'não',
                ];
            }
        }

        $filters = $dashboard['filters'] ?? [];
        $rows[] = ['Período de', (string) ($filters['date_from'] ?? ''), ''];
        $rows[] = ['Período até', (string) ($filters['date_to'] ?? ''), ''];
        $meta = $dashboard['meta'] ?? [];
        $rows[] = ['Gerado em', (string) ($meta['generated_at'] ?? ''), ''];

        return $rows;
    }

    /**
     * @param resource $handle
     * @param list<string> $row
     */
    private function putCsvRow($handle, array $row): void
    {
        fputcsv(
            $handle,
            $row,
            self::CSV_DELIMITER,
            self::CSV_ENCLOSURE,
            self::CSV_ESCAPE
        );
    }
}
