<?php

declare(strict_types=1);

namespace GrupoAwamotos\ERPIntegration\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

/**
 * SQL Server Driver Options for Admin Configuration
 */
class Driver implements OptionSourceInterface
{
    /**
     * Get available driver options
     */
    public function toOptionArray(): array
    {
        $options = [
            [
                'value' => 'auto',
                'label' => __('Auto-detectar (Recomendado)')
            ]
        ];

        $pdoDrivers = \PDO::getAvailableDrivers();

        if (in_array('sqlsrv', $pdoDrivers)) {
            $options[] = [
                'value' => 'sqlsrv',
                'label' => __('Microsoft SQL Server Driver (sqlsrv)')
            ];
        }

        if (in_array('dblib', $pdoDrivers)) {
            $options[] = [
                'value' => 'dblib',
                'label' => __('FreeTDS (dblib)')
            ];
        }

        if (in_array('odbc', $pdoDrivers)) {
            $options[] = [
                'value' => 'odbc',
                'label' => __('ODBC Driver')
            ];
        }

        // If no SQL Server drivers available, show warning
        if (count($options) === 1) {
            $options[] = [
                'value' => '',
                'label' => __('⚠️ Nenhum driver SQL Server instalado')
            ];
        }

        return $options;
    }

    /**
     * Get available drivers as flat array
     */
    public function getAvailableDrivers(): array
    {
        $drivers = [];
        $pdoDrivers = \PDO::getAvailableDrivers();

        if (in_array('sqlsrv', $pdoDrivers)) {
            $drivers['sqlsrv'] = 'Microsoft SQL Server Driver';
        }
        if (in_array('dblib', $pdoDrivers)) {
            $drivers['dblib'] = 'FreeTDS (dblib)';
        }
        if (in_array('odbc', $pdoDrivers)) {
            $drivers['odbc'] = 'ODBC';
        }

        return $drivers;
    }

    /**
     * Check if any SQL Server driver is available
     */
    public function hasAvailableDriver(): bool
    {
        $pdoDrivers = \PDO::getAvailableDrivers();
        return in_array('sqlsrv', $pdoDrivers) ||
               in_array('dblib', $pdoDrivers) ||
               in_array('odbc', $pdoDrivers);
    }
}
