<?php
/**
 * Mirasvit
 *
 * This source file is subject to the Mirasvit Software License, which is available at https://mirasvit.com/license/.
 * Do not edit or add to this file if you wish to upgrade the to newer versions in the future.
 * If you wish to customize this module for your needs.
 * Please refer to http://www.magentocommerce.com for more information.
 *
 * @category  Mirasvit
 * @package   mirasvit/module-search-ultimate
 * @version   2.2.70
 * @copyright Copyright (C) 2024 Mirasvit (https://mirasvit.com/)
 */


declare(strict_types=1);

namespace Mirasvit\SearchGraphQl\Model\Resolver\Magento\Catalog;

use Magento\Catalog\Model\Layer\Resolver as LayerResolver;
use Magento\CatalogGraphQl\DataProvider\Product\LayeredNavigation\LayerBuilder;
use Magento\CatalogGraphQl\Model\Resolver\Products\Query\ProductQueryInterface;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Search\Model\QueryFactory;
use Mirasvit\Misspell\Model\GraphQL\Suggester;
use Mirasvit\Search\Repository\IndexRepository;
use Mirasvit\Search\Model\Index\Context as IndexContext;

class Product implements ResolverInterface
{
    private $layerResolver;

    private $indexContext;

    private $layerBuilder;

    private $searchQuery;

    private $queryFactory;

    private $suggester;

    private $defaultParams
        = [
            'sort'   =>
                ['relevance' => 'DESC'],
            'filter' => [],
        ];

    public function __construct(
        LayerResolver         $layerResolver,
        IndexContext          $indexContext,
        LayerBuilder          $layerBuilder,
        ProductQueryInterface $searchQuery,
        QueryFactory          $queryFactory,
        Suggester             $suggester
    ) {
        $this->layerResolver = $layerResolver;
        $this->indexContext  = $indexContext;
        $this->layerBuilder  = $layerBuilder;
        $this->searchQuery   = $searchQuery;
        $this->queryFactory  = $queryFactory;
        $this->suggester     = $suggester;
    }

    public function resolve(Field $field, $context, ResolveInfo $info, ?array $value = null, ?array $args = null)
    {
        foreach ($this->defaultParams as $parameter => $defaultValue) {
            if (!isset($args[$parameter])) {
                $args[$parameter] = $defaultValue;
            }
        }

        $query     = $this->queryFactory->get();
        $queryText = $this->suggester->suggest();

        $args['search'] = $query->getQueryText();
        if ($queryText) {
            $args['search'] = $queryText;
        }

        /** @var \Magento\CatalogGraphQl\Model\Resolver\Products\SearchResult $searchResult */
        $searchResult = $this->searchQuery->getResult($args, $info, $context);

        $result = $value['catalogsearch_fulltext'] ?? null;

//        print_r($searchResult->getSearchAggregation());die();

        return [
            ...$result,
            'items'        => $searchResult->getProductsSearchResult(),
            'total_count'  => $searchResult->getTotalCount(),
            'aggregations' => $this->getAggregations($context, $searchResult->getSearchAggregation()),
            'page_info'    => [
                'total_pages'  => $searchResult->getTotalPages(),
                'page_size'    => $args['pageSize'],
                'current_page' => $args['currentPage'],
            ],
        ];
    }


    private function getAggregations($context, $aggregations)
    {
        if ($aggregations) {
            $store   = $context->getExtensionAttributes()->getStore();
            $storeId = (int)$store->getId();

            return $this->layerBuilder->build($aggregations, $storeId);
        } else {
            return [];
        }
    }
}
