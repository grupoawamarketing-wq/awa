<?php

declare(strict_types=1);

namespace GrupoAwamotos\CatalogFix\Plugin;

use Magento\Elasticsearch\Model\Adapter\FieldMapper\Product\AttributeAdapter;
use Magento\Elasticsearch\SearchAdapter\Query\Builder\Sort\Position;
use Magento\Framework\Search\RequestInterface;

/**
 * Fix OpenSearch "No mapping found for [position_category_XX]" error.
 *
 * When a category has no products with an explicitly set position,
 * the field `position_category_XX` does not exist in the OpenSearch
 * mapping, causing a 400 error when sorting by position.
 *
 * This plugin adds `unmapped_type: integer` to the sort parameters,
 * telling OpenSearch to treat unmapped fields as integers (default 0)
 * instead of throwing an exception.
 *
 * @see \Magento\Elasticsearch\SearchAdapter\Query\Builder\Sort\Position::build()
 */
class PositionSortUnmappedPlugin
{
    /**
     * Add unmapped_type to position sort to prevent errors on missing fields
     *
     * @param Position $subject
     * @param array $result
     * @param AttributeAdapter $attribute
     * @param string $direction
     * @param RequestInterface $request
     * @return array
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterBuild(
        Position $subject,
        array $result,
        AttributeAdapter $attribute,
        string $direction,
        RequestInterface $request
    ): array {
        foreach ($result as $fieldName => &$sortParams) {
            // Only patch position_category_* fields (not _script sorts)
            if ($fieldName !== '_script' && str_starts_with($fieldName, 'position_category_')) {
                if (!isset($sortParams['unmapped_type'])) {
                    $sortParams['unmapped_type'] = 'integer';
                }
            }
        }
        unset($sortParams);

        return $result;
    }
}
