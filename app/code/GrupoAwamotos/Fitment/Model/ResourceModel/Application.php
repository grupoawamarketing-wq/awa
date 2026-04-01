<?php

declare(strict_types=1);

namespace GrupoAwamotos\Fitment\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

/**
 * Application Resource Model
 */
class Application extends AbstractDb
{
    /**
     * @var string
     */
    protected $_eventPrefix = 'grupoawamotos_fitment_application_resource';

    /**
     * @inheritdoc
     */
    protected function _construct(): void
    {
        $this->_init('grupoawamotos_fitment_application', 'application_id');
    }

    /**
     * Get applications by product ID with model and brand data joined
     *
     * @param int $productId
     * @return array
     */
    public function getApplicationsByProduct(int $productId): array
    {
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from(['app' => $this->getMainTable()])
            ->joinLeft(
                ['model' => $this->getTable('grupoawamotos_fitment_model')],
                'app.model_id = model.model_id',
                ['model_name' => 'name', 'model_year_from' => 'year_from', 'model_year_to' => 'year_to', 'engine_cc', 'category']
            )
            ->joinLeft(
                ['brand' => $this->getTable('grupoawamotos_fitment_brand')],
                'model.brand_id = brand.brand_id',
                ['brand_name' => 'name', 'brand_code' => 'code', 'brand_logo' => 'logo']
            )
            ->where('app.product_id = ?', $productId)
            ->where('model.is_active = ?', 1)
            ->where('brand.is_active = ?', 1)
            ->order(['brand.sort_order ASC', 'brand.name ASC', 'model.sort_order ASC', 'model.name ASC']);

        return $connection->fetchAll($select);
    }

    /**
     * Get products by model ID
     *
     * @param int $modelId
     * @return array Product IDs
     */
    public function getProductsByModel(int $modelId): array
    {
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->getMainTable(), ['product_id'])
            ->where('model_id = ?', $modelId);

        return $connection->fetchCol($select);
    }
}
