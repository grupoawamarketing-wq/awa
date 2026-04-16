<?php

declare(strict_types=1);

namespace GrupoAwamotos\ERPIntegration\Model;

use GrupoAwamotos\ERPIntegration\Api\ConnectionInterface;
use GrupoAwamotos\ERPIntegration\Api\ColorNormalizationInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Psr\Log\LoggerInterface;

class ProductAttributeBackfill
{
    private ConnectionInterface $connection;
    private ProductRepositoryInterface $productRepository;
    private LoggerInterface $logger;
    private ColorNormalizationInterface $colorNormalization;

    public function __construct(
        ConnectionInterface $connection,
        ProductRepositoryInterface $productRepository,
        LoggerInterface $logger,
        ColorNormalizationInterface $colorNormalization
    ) {
        $this->connection = $connection;
        $this->productRepository = $productRepository;
        $this->logger = $logger;
        $this->colorNormalization = $colorNormalization;
    }

    /**
     * @return array{total:int,updated:int,unchanged:int,not_found:int,skipped:int,errors:int}
     */
    public function backfill(?string $sku = null, int $limit = 0): array
    {
        $result = [
            'total' => 0,
            'updated' => 0,
            'unchanged' => 0,
            'not_found' => 0,
            'skipped' => 0,
            'errors' => 0,
        ];

        $rows = $this->getErpRows($sku, $limit);
        $result['total'] = count($rows);

        foreach ($rows as $row) {
            $targetSku = trim((string) ($row['CODIGO'] ?? ''));
            if ($targetSku === '') {
                $result['skipped']++;
                continue;
            }

            $attributes = $this->extractAttributes($row);
            if (empty($attributes)) {
                $result['skipped']++;
                continue;
            }

            try {
                $product = $this->productRepository->get($targetSku, false, 0);
            } catch (NoSuchEntityException $exception) {
                $result['not_found']++;
                continue;
            }

            $hasChanges = false;
            foreach ($attributes as $attributeCode => $attributeValue) {
                $currentValue = $product->getCustomAttribute($attributeCode)?->getValue();
                if ((string) $currentValue === $attributeValue) {
                    continue;
                }

                $product->setCustomAttribute($attributeCode, $attributeValue);
                $hasChanges = true;
            }

            if (!$hasChanges) {
                $result['unchanged']++;
                continue;
            }

            try {
                $this->productRepository->save($product);
                $result['updated']++;
            } catch (\Exception $exception) {
                $result['errors']++;
                $this->logger->error('[ERP] Product attribute backfill failed', [
                    'sku' => $targetSku,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        $this->logger->info('[ERP] Product attribute backfill completed', $result);

        return $result;
    }

    private function getErpRows(?string $sku, int $limit): array
    {
        $params = [];
        $topClause = $limit > 0 ? 'TOP ' . (int) $limit . ' ' : '';

        $sql = "SELECT {$topClause}
                    m.CODIGO,
                    CAST(m.CODINTERNO AS VARCHAR(255)) AS CODINTERNO,
                    CAST(m.NCM AS VARCHAR(255)) AS NCM,
                    CAST(m.COR AS VARCHAR(255)) AS COR
                FROM MT_MATERIAL m
                WHERE m.CODIGO IS NOT NULL
                  AND LTRIM(RTRIM(CAST(m.CODIGO AS VARCHAR(255)))) != ''
                  AND (
                        (m.CODINTERNO IS NOT NULL AND m.CODINTERNO != 0)
                        OR (m.NCM IS NOT NULL AND LTRIM(RTRIM(CAST(m.NCM AS VARCHAR(255)))) != '')
                        OR (m.COR IS NOT NULL AND LTRIM(RTRIM(CAST(m.COR AS VARCHAR(255)))) != '')
                  )";

        if ($sku !== null && $sku !== '') {
            $sql .= ' AND m.CODIGO = :sku';
            $params[':sku'] = $sku;
        }

        $sql .= ' ORDER BY m.CODIGO';

        return $this->connection->query($sql, $params);
    }

    /**
     * @return array<string, string>
     */
    private function extractAttributes(array $row): array
    {
        $attributes = [];

        $internalCode = $this->normalizeErpValue($row['CODINTERNO'] ?? null);
        if ($internalCode !== null) {
            $attributes['erp_internal_code'] = $internalCode;
        }

        $ncm = $this->normalizeErpValue($row['NCM'] ?? null);
        if ($ncm !== null) {
            $attributes['erp_ncm'] = $ncm;
        }

        $corRaw = $this->normalizeErpValue($row['COR'] ?? null);
        if ($corRaw !== null) {
            $colorOptionId = $this->colorNormalization->resolveOptionId($corRaw);
            if ($colorOptionId !== null) {
                $attributes['color'] = (string) $colorOptionId;
            }
        }

        return $attributes;
    }

    private function normalizeErpValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim((string) $value);
        if ($normalized === '' || $normalized === '0') {
            return null;
        }

        return $normalized;
    }
}
