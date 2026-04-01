<?php

declare(strict_types=1);

namespace GrupoAwamotos\Fitment\Plugin;

use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Mirasvit\Search\Index\Magento\Catalog\Product\InstantProvider;
use Psr\Log\LoggerInterface;

/**
 * Enrich Mirasvit autocomplete product results with fitment compatibility label.
 *
 * Targets TWO methods on InstantProvider:
 *  - afterMap()      → injects fitment into _instant during reindex (fast mode)
 *  - afterGetItems() → injects fitment at runtime (non-fast fallback)
 */
class SearchAutocompleteProductPlugin
{
    /**
     * @var string[]
     */
    private const FITMENT_ATTRIBUTES = ['marca_moto', 'modelo_moto', 'ano_moto'];

    public function __construct(
        private readonly CollectionFactory $productCollectionFactory,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * After map — inject fitment into _instant so fast mode includes it.
     *
     * Called during catalog reindex. Adds fitment label to each product's
     * _instant data that gets stored in OpenSearch.
     *
     * @param InstantProvider $subject
     * @param array $result       Return value of map()
     * @param mixed ...$args      Original method arguments (documentData, storeId)
     * @return array
     */
    public function afterMap(InstantProvider $subject, array $result, mixed ...$args): array
    {
        $entityIds = $this->extractEntityIds($result);
        if (empty($entityIds)) {
            return $result;
        }

        $storeId = $this->extractStoreId($args);

        try {
            $fitmentMap = $this->loadFitmentMap($entityIds, $storeId);

            foreach ($result as $productId => &$data) {
                if (!isset($data['_instant']) || !is_array($data['_instant'])) {
                    $data['_instant'] = [];
                }

                $data['_instant']['fitment'] = $fitmentMap[(int)$productId] ?? '';
            }
            unset($data);
        } catch (\Throwable $e) {
            $this->logger->error('[Fitment] afterMap plugin error: ' . $e->getMessage());
        }

        return $result;
    }

    /**
     * After getItems — inject fitment at runtime (non-fast mode fallback).
     *
     * @param InstantProvider $subject
     * @param array $result
     * @param mixed ...$args Original method arguments (storeId, limit, page)
     * @return array
     */
    public function afterGetItems(InstantProvider $subject, array $result, mixed ...$args): array
    {
        if (empty($result)) {
            return $result;
        }

        if (!$this->hasItemsMissingFitment($result)) {
            return $result;
        }

        $storeId = $this->extractStoreId($args);
        $skus = $this->extractSkus($result);

        if (empty($skus)) {
            return $result;
        }

        try {
            $collection = $this->productCollectionFactory->create();
            $collection->addAttributeToFilter('sku', ['in' => $skus])
                ->addAttributeToSelect(self::FITMENT_ATTRIBUTES)
                ->addStoreFilter($storeId);

            $fitmentBySku = [];
            foreach ($collection as $product) {
                $label = $this->buildFitmentLabel($product);
                if ($label !== '') {
                    $fitmentBySku[(string)$product->getSku()] = $label;
                }
            }

            foreach ($result as &$item) {
                $sku = isset($item['sku']) ? trim((string)$item['sku']) : '';
                if ($sku === '') {
                    $item['fitment'] = $item['fitment'] ?? '';
                    continue;
                }

                $existingFitment = isset($item['fitment']) ? trim((string)$item['fitment']) : '';
                $item['fitment'] = $existingFitment !== ''
                    ? $existingFitment
                    : ($fitmentBySku[$sku] ?? '');
            }
            unset($item);
        } catch (\Throwable $e) {
            $this->logger->error('[Fitment] afterGetItems plugin error: ' . $e->getMessage());
        }

        return $result;
    }

    /**
     * Load fitment labels for a batch of product entity IDs.
     *
     * @param array<int|string> $entityIds
     * @param int $storeId
     * @return array<int, string> productId => label
     */
    private function loadFitmentMap(array $entityIds, int $storeId): array
    {
        $collection = $this->productCollectionFactory->create();
        $collection->addAttributeToFilter('entity_id', ['in' => $entityIds])
            ->addAttributeToSelect(self::FITMENT_ATTRIBUTES)
            ->addStoreFilter($storeId);

        $map = [];
        foreach ($collection as $product) {
            $label = $this->buildFitmentLabel($product);
            if ($label !== '') {
                $map[(int)$product->getId()] = $label;
            }
        }

        return $map;
    }

    /**
     * Build "Marca · Modelo · Ano" label from product attributes.
     *
     * @param \Magento\Catalog\Model\Product $product
     * @return string
     */
    private function buildFitmentLabel(\Magento\Catalog\Model\Product $product): string
    {
        $marca  = trim((string)$product->getData('marca_moto'));
        $modelo = trim((string)$product->getData('modelo_moto'));
        $ano    = trim((string)$product->getData('ano_moto'));

        $parts = array_filter([$marca, $modelo, $ano], fn(string $v) => $v !== '');

        return implode(' · ', $parts);
    }

    /**
     * @param array<int|string, mixed> $result
     * @return int[]
     */
    private function extractEntityIds(array $result): array
    {
        $entityIds = [];

        foreach (array_keys($result) as $productId) {
            if (is_numeric($productId)) {
                $entityIds[] = (int)$productId;
            }
        }

        return array_values(array_unique(array_filter($entityIds, static fn(int $productId): bool => $productId > 0)));
    }

    /**
     * @param mixed[] $args
     */
    private function extractStoreId(array $args): int
    {
        foreach ($args as $arg) {
            if (is_int($arg)) {
                return $arg;
            }
        }

        return 0;
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return string[]
     */
    private function extractSkus(array $items): array
    {
        $skus = [];

        foreach ($items as $item) {
            if (!is_array($item) || !isset($item['sku'])) {
                continue;
            }

            $sku = trim((string)$item['sku']);
            if ($sku !== '') {
                $skus[] = $sku;
            }
        }

        return array_values(array_unique($skus));
    }

    /**
     * @param array<int, array<string, mixed>> $items
     */
    private function hasItemsMissingFitment(array $items): bool
    {
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            if (!isset($item['fitment']) || trim((string)$item['fitment']) === '') {
                return true;
            }
        }

        return false;
    }
}
