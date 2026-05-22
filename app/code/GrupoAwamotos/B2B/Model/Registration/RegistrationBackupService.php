<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Model\Registration;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Filesystem;

/**
 * Logical backup of customer EAV fields before bulk apply operations.
 */
class RegistrationBackupService
{
    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly Filesystem $filesystem
    ) {
    }

    /**
     * @param list<int> $customerIds
     * @param list<string> $attributeCodes
     * @return array{path: string, rows: int}
     */
    public function exportCustomersBackup(string $type, array $customerIds, array $attributeCodes): array
    {
        if ($customerIds === []) {
            throw new \InvalidArgumentException('Nenhum customer_id para backup.');
        }

        $connection = $this->resourceConnection->getConnection();
        $attrIds = $this->resolveAttributeIds($attributeCodes);
        $timestamp = date('Ymd_His');
        $relativePath = sprintf('export/b2b_backup_%s_%s.csv', $type, $timestamp);

        $directory = $this->filesystem->getDirectoryWrite(DirectoryList::VAR_DIR);
        $directory->create('export');
        $absolutePath = $directory->getAbsolutePath($relativePath);

        $handle = fopen($absolutePath, 'wb');
        if ($handle === false) {
            throw new \RuntimeException('Não foi possível criar backup: ' . $absolutePath);
        }

        $headers = array_merge(['entity_id', 'email'], $attributeCodes);
        fputcsv($handle, $headers, ';', '"', '\\');

        $varcharTable = $this->resourceConnection->getTableName('customer_entity_varchar');
        $entityTable = $this->resourceConnection->getTableName('customer_entity');

        foreach (array_chunk($customerIds, 500) as $chunk) {
            $select = $connection->select()
                ->from(['ce' => $entityTable], ['entity_id', 'email'])
                ->where('ce.entity_id IN (?)', $chunk)
                ->order('ce.entity_id ASC');

            $customers = $connection->fetchAll($select);

            foreach ($customers as $customer) {
                $customerId = (int) $customer['entity_id'];
                $row = [$customerId, (string) $customer['email']];

                foreach ($attributeCodes as $code) {
                    $attrId = $attrIds[$code] ?? 0;
                    $value = '';
                    if ($attrId > 0) {
                        $dbValue = $connection->fetchOne(
                            "SELECT value FROM {$varcharTable} WHERE entity_id = ? AND attribute_id = ?",
                            [$customerId, $attrId]
                        );
                        $value = $dbValue !== false ? (string) $dbValue : '';
                    }
                    $row[] = $value;
                }

                fputcsv($handle, $row, ';', '"', '\\');
            }
        }

        fclose($handle);

        return [
            'path' => $absolutePath,
            'rows' => count($customerIds),
        ];
    }

    /**
     * @param list<string> $codes
     * @return array<string, int>
     */
    private function resolveAttributeIds(array $codes): array
    {
        $connection = $this->resourceConnection->getConnection();
        $result = [];

        foreach ($codes as $code) {
            $result[$code] = (int) $connection->fetchOne(
                "SELECT ea.attribute_id FROM eav_attribute ea
                 INNER JOIN eav_entity_type et ON et.entity_type_id = ea.entity_type_id
                 WHERE ea.attribute_code = ? AND et.entity_type_code = 'customer'",
                [$code]
            );
        }

        return $result;
    }
}
