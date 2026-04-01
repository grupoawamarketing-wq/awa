<?php

declare(strict_types=1);

namespace GrupoAwamotos\Fitment\Block;

use GrupoAwamotos\Fitment\Model\ResourceModel\Application\CollectionFactory;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Registry;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;

/**
 * Block to display product fitment/application list on PDP
 *
 * Shows which motorcycles the product is compatible with
 */
class ApplicationList extends Template
{
    /**
     * @var string
     */
    protected $_template = 'GrupoAwamotos_Fitment::product/application_list.phtml';

    private CollectionFactory $collectionFactory;
    private Registry $registry;
    private ProductRepositoryInterface $productRepository;
    private ?array $applications = null;

    public function __construct(
        Context $context,
        CollectionFactory $collectionFactory,
        Registry $registry,
        ProductRepositoryInterface $productRepository,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->collectionFactory = $collectionFactory;
        $this->registry = $registry;
        $this->productRepository = $productRepository;
    }

    /**
     * Get current product
     *
     * @return \Magento\Catalog\Model\Product|null
     */
    public function getProduct(): ?\Magento\Catalog\Model\Product
    {
        return $this->registry->registry('current_product');
    }

    /**
     * Check if fitment data exists for current product
     *
     * @return bool
     */
    public function hasApplications(): bool
    {
        $applications = $this->getApplications();
        return !empty($applications);
    }

    /**
     * Get applications grouped by brand for current product
     *
     * @return array
     */
    public function getApplications(): array
    {
        if ($this->applications === null) {
            $product = $this->getProduct();
            if (!$product) {
                $this->applications = [];
                return $this->applications;
            }

            $collection = $this->collectionFactory->create();
            $collection->addProductFilter((int) $product->getId());
            $this->applications = $collection->getGroupedByBrand();
        }

        return $this->applications;
    }

    /**
     * Get formatted motorcycle info for display
     *
     * @param array $model
     * @return string
     */
    public function getMotorcycleLabel(array $model): string
    {
        $label = $model['model_name'];

        if (!empty($model['years'])) {
            $label .= ' (' . $model['years'] . ')';
        }

        if (!empty($model['engine_cc'])) {
            $label .= ' ' . $model['engine_cc'];
        }

        return $label;
    }

    /**
     * Get position badge HTML if position is set
     *
     * @param array $model
     * @return string
     */
    public function getPositionBadge(array $model): string
    {
        if (empty($model['position'])) {
            return '';
        }

        return '<span class="fitment-position">' . $this->escapeHtml($model['position']) . '</span>';
    }

    /**
     * Get OEM badge HTML if is OEM part
     *
     * @param array $model
     * @return string
     */
    public function getOemBadge(array $model): string
    {
        if (empty($model['is_oem'])) {
            return '';
        }

        $code = !empty($model['oem_code']) ? ' (' . $this->escapeHtml($model['oem_code']) . ')' : '';
        return '<span class="fitment-oem">OEM' . $code . '</span>';
    }

    /**
     * Get notes if set
     *
     * @param array $model
     * @return string
     */
    public function getNotes(array $model): string
    {
        if (empty($model['notes'])) {
            return '';
        }

        return '<span class="fitment-notes">' . $this->escapeHtml($model['notes']) . '</span>';
    }

    /**
     * Get section title
     *
     * @return string
     */
    public function getSectionTitle(): string
    {
        return (string) __('Compatibilidade / Aplicação');
    }

    /**
     * Get empty message when no applications found
     *
     * @return string
     */
    public function getEmptyMessage(): string
    {
        return (string) __('Não há informações de compatibilidade disponíveis para este produto.');
    }
}
