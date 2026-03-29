<?php
declare(strict_types=1);

namespace GrupoAwamotos\Fitment\Plugin;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Mirasvit\Search\Index\Magento\Catalog\Product\InstantProvider;
use Psr\Log\LoggerInterface;

/**
 * Enrich Mirasvit autocomplete product results with fitment compatibility label.
 */
class SearchAutocompleteProductPlugin
{
    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * After getItems — inject fitment compatibility label into each product item.
     *
     * @param InstantProvider $subject
     * @param array $result
     * @param int $storeId
     * @param int $limit
     * @param int $page
     * @return array
     */
    public function afterGetItems(InstantProvider $subject, array $result, int $storeId, int $limit, int $page = 1): array
    {
        $this->logger->info('[Fitment Plugin] afterGetItems called, items: ' . count($result));

        if (empty($result)) {
            return $result;
        }

        $skus = [];
        foreach ($result as $item) {
            if (!empty($item['sku'])) {
                $skus[] = $item['sku'];
            }
        }

        if (empty($skus)) {
            return $result;
        }

        try {
            $criteria = $this->searchCriteriaBuilder
                ->addFilter('sku', $skus, 'in')
                ->create();

            $products = $this->productRepository->getList($criteria)->getItems();

            $fitmentMap = [];
            foreach ($products as $product) {
                $marca  = $product->getData('marca_moto');
                $modelo = $product->getData('modelo_moto');
                $ano    = $product->getData('ano_moto');

                $parts = array_filter([$marca, $modelo, $ano]);
                if (!empty($parts)) {
                    $fitmentMap[$product->getSku()] = implode(' · ', $parts);
                }
            }

            foreach ($result as &$item) {
                $sku = $item['sku'] ?? '';
                $item['fitment'] = $fitmentMap[$sku] ?? '';
            }
            unset($item);
        } catch (\Throwable $e) {
            $this->logger->error('Fitment autocomplete plugin error: ' . $e->getMessage());
        }

        return $result;
    }
}
