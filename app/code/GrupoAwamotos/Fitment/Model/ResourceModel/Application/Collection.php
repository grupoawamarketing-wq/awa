<?php

declare(strict_types=1);

namespace GrupoAwamotos\Fitment\Model\ResourceModel\Application;

use GrupoAwamotos\Fitment\Model\Application as Model;
use GrupoAwamotos\Fitment\Model\ResourceModel\Application as ResourceModel;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

/**
 * Application Collection
 */
class Collection extends AbstractCollection
{
    /**
     * @var string
     */
    protected $_eventPrefix = 'grupoawamotos_fitment_application_collection';

    /**
     * @var string
     */
    protected $_idFieldName = 'application_id';

    /**
     * @inheritdoc
     */
    protected function _construct(): void
    {
        $this->_init(Model::class, ResourceModel::class);
    }

    /**
     * Filter by product ID
     *
     * @param int $productId
     * @return $this
     */
    public function addProductFilter(int $productId): self
    {
        $this->addFieldToFilter('product_id', (string)$productId);
        return $this;
    }

    /**
     * Filter by model ID
     *
     * @param int $modelId
     * @return $this
     */
    public function addModelFilter(int $modelId): self
    {
        $this->addFieldToFilter('model_id', (string)$modelId);
        return $this;
    }

    /**
     * Join model and brand data for display
     *
     * @return $this
     */
    public function joinModelAndBrand(): self
    {
        $this->getSelect()->joinLeft(
            ['model' => $this->getTable('grupoawamotos_fitment_model')],
            'main_table.model_id = model.model_id',
            [
                'model_name' => 'name',
                'model_year_from' => 'year_from',
                'model_year_to' => 'year_to',
                'engine_cc',
                'category',
            ]
        )->joinLeft(
            ['brand' => $this->getTable('grupoawamotos_fitment_brand')],
            'model.brand_id = brand.brand_id',
            ['brand_name' => 'brand.name', 'brand_id' => 'brand.brand_id']
        )->where('model.is_active = ?', 1)
        ->where('brand.is_active = ?', 1);

        return $this;
    }

    /**
     * Set default order
     *
     * @return $this
     */
    public function setDefaultOrder(): self
    {
        $this->getSelect()
            ->order('brand.sort_order ASC')
            ->order('brand.name ASC')
            ->order('model.sort_order ASC')
            ->order('model.name ASC');
        return $this;
    }

    /**
     * Get applications grouped by brand for frontend display
     *
     * @return array
     */
    public function getGroupedByBrand(): array
    {
        $this->joinModelAndBrand();
        $this->setDefaultOrder();

        $grouped = [];
        foreach ($this as $application) {
            $brandId = (int) $application->getData('brand_id');
            $brandName = $application->getData('brand_name') ?? 'Sem Marca';

            if (!isset($grouped[$brandId])) {
                $grouped[$brandId] = [
                    'brand_id' => $brandId,
                    'brand_name' => $brandName,
                    'models' => [],
                ];
            }

            $yearFrom = $application->getYearFrom() ?? $application->getData('model_year_from');
            $yearTo = $application->getYearTo() ?? $application->getData('model_year_to');

            $formattedYears = $this->formatYears($yearFrom, $yearTo);

            $grouped[$brandId]['models'][] = [
                'model_name' => $application->getData('model_name'),
                'years' => $formattedYears,
                'engine_cc' => $application->getData('engine_cc'),
                'position' => $application->getPosition(),
                'notes' => $application->getNotes(),
                'is_oem' => $application->getIsOem(),
                'oem_code' => $application->getOemCode(),
            ];
        }

        return array_values($grouped);
    }

    /**
     * Format years for display
     *
     * @param int|null $from
     * @param int|null $to
     * @return string
     */
    private function formatYears(?int $from, ?int $to): string
    {
        if ($from === null && $to === null) {
            return 'Todos os anos';
        }

        if ($from !== null && $to === null) {
            return "{$from}-Atual";
        }

        if ($from === null && $to !== null) {
            return "Até {$to}";
        }

        if ($from === $to) {
            return (string) $from;
        }

        return "{$from}-{$to}";
    }
}
