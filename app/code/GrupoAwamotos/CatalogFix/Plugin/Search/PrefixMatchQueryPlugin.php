<?php

declare(strict_types=1);

namespace GrupoAwamotos\CatalogFix\Plugin\Search;

use Magento\Elasticsearch\SearchAdapter\Query\Builder\MatchQuery;
use Magento\Framework\Search\Request\QueryInterface as RequestQueryInterface;

/**
 * SRCH-001: Add match_phrase_prefix clause alongside the default match query.
 *
 * Magento only sends a `match` query to OpenSearch. Single-word partial terms
 * like "retr", "retrovis", "protetor" return 0 results because the match query
 * requires an exact token match. Adding a `match_phrase_prefix` with lower boost
 * allows prefix matching without degrading relevance scoring for exact matches.
 *
 * Only applies to single-word queries without spaces.
 */
class PrefixMatchQueryPlugin
{
    private const MAX_EXPANSIONS = 50;
    private const PREFIX_BOOST_FACTOR = 0.5;

    /**
     * After the default match query is built, inject a match_phrase_prefix clause
     * into the bool->should array for single-word search terms.
     *
     * @param MatchQuery $subject
     * @param array $result
     * @param array $selectQuery
     * @param RequestQueryInterface $requestQuery
     * @param string $conditionType
     * @return array
     */
    public function afterBuild(
        MatchQuery $subject,
        array $result,
        array $selectQuery,
        RequestQueryInterface $requestQuery,
        string $conditionType
    ): array {
        if (!$requestQuery instanceof \Magento\Framework\Search\Request\Query\MatchQuery) {
            return $result;
        }
        $queryValue = trim($requestQuery->getValue());

        // Only apply for single-word queries (no spaces) shorter than 12 chars.
        if (str_contains($queryValue, ' ') || strlen($queryValue) >= 12) {
            return $result;
        }

        // Only inject into "should" conditions
        if (!isset($result['bool']['should'])) {
            return $result;
        }

        $requestQueryBoost = $requestQuery->getBoost() ?: 1;
        $added = false;

        foreach ($requestQuery->getMatches() as $match) {
            if ($added) {
                break;
            }
            $field = '_search';
            $result['bool']['should'][] = [
                'match_phrase_prefix' => [
                    $field => [
                        'query'          => $queryValue,
                        'boost'          => ($requestQueryBoost + ($match['boost'] ?? 1)) * self::PREFIX_BOOST_FACTOR,
                        'max_expansions' => self::MAX_EXPANSIONS,
                    ],
                ],
            ];
            $added = true;
        }

        return $result;
    }
}
