<?php
declare(strict_types=1);

namespace GrupoAwamotos\Theme\Block;

/**
 * Carrossel de produtos filtrado por categoria(s) — compatível com bestseller.phtml
 *
 * Uso no phtml:
 *   $b = $layout->createBlock(CategoryProductCarousel::class);
 *   $b->setCategoryIds('46,75,76');   // IDs separados por vírgula
 *   $b->setQty(12);                   // opcional, default 12
 *   $b->setTemplate('Rokanthemes_BestsellerProduct::bestseller.phtml');
 *   echo $b->toHtml();
 */
class CategoryProductCarousel extends \Rokanthemes\BestsellerProduct\Block\Bestseller
{
    private const DEFAULT_QTY = 12;

    /**
     * Retorna produtos das categorias definidas em category_ids.
     * Sem category_ids definido, delega para o pai (mais vendidos globais).
     *
     * @return \Magento\Catalog\Model\ResourceModel\Product\Collection
     */
    public function getProducts(): \Magento\Catalog\Model\ResourceModel\Product\Collection
    {
        $rawIds = $this->getData('category_ids');

        if (empty($rawIds)) {
            return parent::getProducts();
        }

        $ids = array_filter(
            array_map('intval', explode(',', (string) $rawIds)),
            static fn(int $id): bool => $id > 0
        );

        if (empty($ids)) {
            return parent::getProducts();
        }

        $storeId = $this->storeManager->getStore()->getId();
        $collection = $this->productCollectionFactory->create()->setStoreId($storeId);

        $collection
            ->addAttributeToSelect($this->catalogConfig->getProductAttributes())
            ->addCategoriesFilter(['in' => $ids])
            ->addMinimalPrice()
            ->addFinalPrice()
            ->addTaxPercents()
            ->addUrlRewrite()
            ->setVisibility($this->productVisibility->getVisibleInCatalogIds())
            ->addAttributeToSort('entity_id', 'desc');

        $qty = (int) ($this->getData('qty') ?: $this->getConfig('qty') ?: self::DEFAULT_QTY);
        $collection->setPageSize($qty)->setCurPage(1);

        $this->_eventManager->dispatch(
            'catalog_block_product_list_collection',
            ['collection' => $collection]
        );

        return $collection;
    }

    /**
     * Garante que getConfig('enabled') retorna 1 para este bloco,
     * mesmo que o módulo bestsellerproduct esteja desabilitado no admin.
     */
    public function getConfig($att): mixed
    {
        if ($att === 'enabled') {
            return 1;
        }

        $override = $this->getData($att);
        if ($override !== null) {
            return $override;
        }

        return parent::getConfig($att);
    }
}
