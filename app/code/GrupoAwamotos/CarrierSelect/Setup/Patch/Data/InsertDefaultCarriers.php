<?php

/**
 * Data Patch para inserir transportadoras iniciais
 */

declare(strict_types=1);

namespace GrupoAwamotos\CarrierSelect\Setup\Patch\Data;

use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

class InsertDefaultCarriers implements DataPatchInterface
{
    /**
     * @var ModuleDataSetupInterface
     */
    private ModuleDataSetupInterface $moduleDataSetup;

    /**
     * @param ModuleDataSetupInterface $moduleDataSetup
     */
    public function __construct(ModuleDataSetupInterface $moduleDataSetup)
    {
        $this->moduleDataSetup = $moduleDataSetup;
    }

    /**
     * @inheritdoc
     */
    public function apply()
    {
        $this->moduleDataSetup->startSetup();

        $connection = $this->moduleDataSetup->getConnection();
        $tableName = $this->moduleDataSetup->getTable('grupoawamotos_carriers');

        // Transportadoras padrão - podem ser alteradas via admin ou importação
        $carriers = [
            [
                'name' => 'Correios - PAC',
                'code' => 'correios_pac',
                'contact_phone' => '0800 725 7282',
                'contact_email' => null,
                'website' => 'https://www.correios.com.br',
                'regions' => json_encode(['BR']),
                'notes' => 'Entrega econômica dos Correios',
                'is_active' => 1,
                'sort_order' => 1
            ],
            [
                'name' => 'Correios - SEDEX',
                'code' => 'correios_sedex',
                'contact_phone' => '0800 725 7282',
                'contact_email' => null,
                'website' => 'https://www.correios.com.br',
                'regions' => json_encode(['BR']),
                'notes' => 'Entrega expressa dos Correios',
                'is_active' => 1,
                'sort_order' => 2
            ],
            [
                'name' => 'Jadlog',
                'code' => 'jadlog',
                'contact_phone' => '(11) 3563-2000',
                'contact_email' => 'sac@jadlog.com.br',
                'website' => 'https://www.jadlog.com.br',
                'regions' => json_encode(['BR']),
                'notes' => null,
                'is_active' => 1,
                'sort_order' => 3
            ],
            [
                'name' => 'Total Express',
                'code' => 'total_express',
                'contact_phone' => '(11) 3327-5300',
                'contact_email' => null,
                'website' => 'https://www.totalexpress.com.br',
                'regions' => json_encode(['BR']),
                'notes' => null,
                'is_active' => 1,
                'sort_order' => 4
            ],
            [
                'name' => 'Braspress',
                'code' => 'braspress',
                'contact_phone' => '(11) 2188-9000',
                'contact_email' => null,
                'website' => 'https://www.braspress.com',
                'regions' => json_encode(['BR']),
                'notes' => 'Especializada em encomendas urgentes',
                'is_active' => 1,
                'sort_order' => 5
            ],
            [
                'name' => 'Transportadora Local',
                'code' => 'local',
                'contact_phone' => null,
                'contact_email' => null,
                'website' => null,
                'regions' => json_encode(['SP', 'RJ', 'MG']),
                'notes' => 'Entrega local para região Sudeste',
                'is_active' => 1,
                'sort_order' => 6
            ],
            [
                'name' => 'Retirar na Loja',
                'code' => 'retirada',
                'contact_phone' => null,
                'contact_email' => null,
                'website' => null,
                'regions' => json_encode(['BR']),
                'notes' => 'Cliente retira o produto na loja',
                'is_active' => 1,
                'sort_order' => 0
            ],
            [
                'name' => 'Motoboy',
                'code' => 'motoboy',
                'contact_phone' => null,
                'contact_email' => null,
                'website' => null,
                'regions' => json_encode(['SP']),
                'notes' => 'Entrega por motoboy - região metropolitana de SP',
                'is_active' => 1,
                'sort_order' => 7
            ],
        ];

        foreach ($carriers as $carrier) {
            $connection->insertOnDuplicate($tableName, $carrier, ['name', 'is_active', 'sort_order']);
        }

        $this->moduleDataSetup->endSetup();

        return $this;
    }

    /**
     * @inheritdoc
     */
    public static function getDependencies(): array
    {
        return [];
    }

    /**
     * @inheritdoc
     */
    public function getAliases(): array
    {
        return [];
    }
}
