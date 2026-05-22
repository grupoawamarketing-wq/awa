<?php

declare(strict_types=1);

namespace GrupoAwamotos\ERPIntegration\Model;

use GrupoAwamotos\ERPIntegration\Api\ConnectionInterface;
use Psr\Log\LoggerInterface;

/**
 * Corrige campos de transporte/redespacho em pedidos B2B web já existentes no Sectra.
 *
 * Escopo: VE_PEDIDO.PEDORIGEM = 5 (Pedidos Web / canal B2B Magento).
 */
class OrderTransportRepair
{
    /** Origem Sectra: 5 = Pedidos Web (B2B Magento). */
    private const PEDORIGEM_B2B_WEB = 5;

    public function __construct(
        private readonly ConnectionInterface $connection,
        private readonly CustomerSync $customerSync,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findOrdersNeedingRepair(string $status = 'W', int $limit = 100): array
    {
        if ($limit <= 0) {
            return [];
        }

        $sql = "SELECT TOP {$limit} p.CODIGO AS PEDIDO_ID, p.PEDIDOCLI, p.PEDIDOWEB, p.CLIENTE, p.STATUS, p.USERNAME1,
                       p.PEDORIGEM, p.TRANSPORTADOR, p.RESPFRETE, p.RESPREDESPACHO, p.REDESPACHO, p.VLRFRETE,
                       f.CGC, f.CPF, f.TRANSPPREF, f.RESPFRETE AS C_RESPFRETE, f.RESPREDESPACHO AS C_RESPREDESPACHO,
                       f.REDESPACHO AS C_REDESPACHO,
                       REPLACE(REPLACE(REPLACE(tp.CGC, '.', ''), '/', ''), '-', '') AS TRANSPPREF_CNPJ,
                       tp.CKCLIENTE AS TRANSPPREF_IS_CLIENT,
                       REPLACE(REPLACE(REPLACE(rd.CGC, '.', ''), '/', ''), '-', '') AS REDESPACHO_CNPJ,
                       rd.CKCLIENTE AS REDESPACHO_IS_CLIENT
                FROM VE_PEDIDO p
                INNER JOIN FN_FORNECEDORES f ON f.CODIGO = p.CLIENTE AND f.CKCLIENTE = 'S'
                LEFT JOIN FN_FORNECEDORES tp ON tp.CODIGO = f.TRANSPPREF AND tp.CKTRANSPORTADOR = 'S'
                LEFT JOIN FN_FORNECEDORES rd ON rd.CODIGO = f.REDESPACHO AND rd.CKTRANSPORTADOR = 'S'
                WHERE p.STATUS = :status
                  AND p.PEDORIGEM = :pedorigem
                ORDER BY p.DTPEDIDO DESC";

        $rows = $this->connection->query($sql, [
            ':status' => $status,
            ':pedorigem' => self::PEDORIGEM_B2B_WEB,
        ]);
        $needsRepair = [];

        foreach ($rows as $row) {
            $resolved = $this->customerSync->resolveOrderTransportFields($this->buildErpDataFromRow($row));
            if (!$this->needsRepair($row, $resolved)) {
                continue;
            }

            $needsRepair[] = [
                'pedido_id' => (int) $row['PEDIDO_ID'],
                'pedido_cli' => (string) ($row['PEDIDOCLI'] ?? ''),
                'pedido_web' => (string) ($row['PEDIDOWEB'] ?? ''),
                'cliente' => (int) ($row['CLIENTE'] ?? 0),
                'status' => (string) ($row['STATUS'] ?? $status),
                'username' => (string) ($row['USERNAME1'] ?? ''),
                'current' => $this->extractCurrentTransport($row),
                'resolved' => $resolved,
            ];
        }

        return $needsRepair;
    }

    /**
     * @param array<string, mixed> $item
     */
    public function applyRepair(array $item): bool
    {
        $resolved = $item['resolved'];
        $pedidoId = (int) $item['pedido_id'];
        $status = (string) $item['status'];

        $sql = "UPDATE VE_PEDIDO SET
                    TRANSPORTADOR = :transportador,
                    RESPFRETE = :respfrete,
                    RESPREDESPACHO = :respredespacho,
                    REDESPACHO = :redespacho,
                    VLRREDESPACHO = :vlrredespacho
                WHERE CODIGO = :pedido_id AND STATUS = :status AND PEDORIGEM = :pedorigem";

        $affected = $this->connection->execute($sql, [
            ':transportador' => $resolved['transportador'],
            ':respfrete' => $resolved['respfrete'],
            ':respredespacho' => $resolved['respredespacho'],
            ':redespacho' => $resolved['redespacho'],
            ':vlrredespacho' => $resolved['vlrredespacho'],
            ':pedido_id' => $pedidoId,
            ':status' => $status,
            ':pedorigem' => self::PEDORIGEM_B2B_WEB,
        ]);

        if ($affected > 0) {
            $this->logger->info('[ERP] Order transport repaired', [
                'pedido_id' => $pedidoId,
                'pedido_cli' => $item['pedido_cli'] ?? '',
                'cliente' => $item['cliente'] ?? 0,
                'current' => $item['current'] ?? [],
                'resolved' => $resolved,
            ]);
        }

        return $affected > 0;
    }

    /**
     * @param array<int, array<string, mixed>> $items
     */
    public function buildSqlStatements(array $items): string
    {
        $lines = [
            '-- AWA Motos: correção transporte/redespacho em pedidos B2B web (VE_PEDIDO.PEDORIGEM = 5)',
            '-- Não altera pedidos manuais ou de outros canais (ex.: PEDORIGEM 1, 7)',
            '-- Executar no SQL Server do Sectra com usuário que tenha permissão UPDATE',
            '-- Gerado em ' . date('Y-m-d H:i:s'),
            '',
        ];

        foreach ($items as $item) {
            $resolved = $item['resolved'];
            $redespacho = $resolved['redespacho'] === null ? 'NULL' : (string) (int) $resolved['redespacho'];
            $status = str_replace("'", "''", (string) $item['status']);

            $lines[] = sprintf(
                '-- Pedido ERP %d | PEDIDOWEB %s | PEDIDOCLI %s | Cliente %d',
                (int) $item['pedido_id'],
                (string) ($item['pedido_web'] ?: '-'),
                (string) ($item['pedido_cli'] ?: '-'),
                (int) $item['cliente']
            );
            $lines[] = sprintf(
                'UPDATE VE_PEDIDO SET TRANSPORTADOR = %d, RESPFRETE = %d, RESPREDESPACHO = %d, REDESPACHO = %s, VLRREDESPACHO = %.2f WHERE CODIGO = %d AND STATUS = \'%s\' AND PEDORIGEM = %d;',
                (int) $resolved['transportador'],
                (int) $resolved['respfrete'],
                (int) $resolved['respredespacho'],
                $redespacho,
                (float) $resolved['vlrredespacho'],
                (int) $item['pedido_id'],
                $status,
                self::PEDORIGEM_B2B_WEB
            );
            $lines[] = '';
        }

        return implode(PHP_EOL, $lines);
    }

    /**
     * @return array{needs_repair:int,fixed:int,errors:int,items:array<int,array<string,mixed>>}
     */
    public function run(string $status = 'W', int $limit = 100, bool $dryRun = true): array
    {
        $items = $this->findOrdersNeedingRepair($status, $limit);
        $result = [
            'needs_repair' => count($items),
            'fixed' => 0,
            'errors' => 0,
            'items' => $items,
        ];

        if ($dryRun) {
            return $result;
        }

        foreach ($items as $item) {
            try {
                if ($this->applyRepair($item)) {
                    $result['fixed']++;
                }
            } catch (\Throwable $e) {
                $result['errors']++;
                $this->logger->error('[ERP] Order transport repair failed: ' . $e->getMessage(), [
                    'pedido_id' => $item['pedido_id'] ?? 0,
                ]);
            }
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $row
     * @param array<string, mixed> $resolved
     */
    private function needsRepair(array $row, array $resolved): bool
    {
        $currentRedespacho = $this->normalizeRedespachoValue($row['REDESPACHO'] ?? null);
        $resolvedRedespacho = $this->normalizeRedespachoValue($resolved['redespacho'] ?? null);

        return (int) ($row['TRANSPORTADOR'] ?? 0) !== (int) $resolved['transportador']
            || (int) ($row['RESPFRETE'] ?? 0) !== (int) $resolved['respfrete']
            || (int) ($row['RESPREDESPACHO'] ?? 0) !== (int) $resolved['respredespacho']
            || $currentRedespacho !== $resolvedRedespacho;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, int|null>
     */
    private function extractCurrentTransport(array $row): array
    {
        return [
            'transportador' => (int) ($row['TRANSPORTADOR'] ?? 0),
            'respfrete' => (int) ($row['RESPFRETE'] ?? 0),
            'respredespacho' => (int) ($row['RESPREDESPACHO'] ?? 0),
            'redespacho' => $this->normalizeRedespachoValue($row['REDESPACHO'] ?? null),
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function buildErpDataFromRow(array $row): array
    {
        return [
            'CODIGO' => (int) ($row['CLIENTE'] ?? 0),
            'CGC' => $row['CGC'] ?? '',
            'CPF' => $row['CPF'] ?? '',
            'TRANSPPREF' => (int) ($row['TRANSPPREF'] ?? 0),
            'TRANSPPREF_CNPJ' => $row['TRANSPPREF_CNPJ'] ?? '',
            'TRANSPPREF_IS_CLIENT' => $row['TRANSPPREF_IS_CLIENT'] ?? 'N',
            'RESPFRETE' => $row['C_RESPFRETE'] ?? 0,
            'RESPREDESPACHO' => $row['C_RESPREDESPACHO'] ?? 9,
            'REDESPACHO' => $row['C_REDESPACHO'] ?? null,
            'REDESPACHO_CNPJ' => $row['REDESPACHO_CNPJ'] ?? '',
            'REDESPACHO_IS_CLIENT' => $row['REDESPACHO_IS_CLIENT'] ?? 'N',
        ];
    }

    private function normalizeRedespachoValue(mixed $value): ?int
    {
        if ($value === null || $value === '' || $value === '0' || $value === 0) {
            return null;
        }

        return (int) $value;
    }
}
