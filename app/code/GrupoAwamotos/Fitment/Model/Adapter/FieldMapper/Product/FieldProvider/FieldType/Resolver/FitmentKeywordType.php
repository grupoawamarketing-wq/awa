<?php

declare(strict_types=1);

namespace GrupoAwamotos\Fitment\Model\Adapter\FieldMapper\Product\FieldProvider\FieldType\Resolver;

use Magento\Elasticsearch\Model\Adapter\FieldMapper\Product\AttributeAdapter;
use Magento\Elasticsearch\Model\Adapter\FieldMapper\Product\FieldProvider\FieldType\ResolverInterface;

/**
 * Resolves fitment attributes (marca_moto, modelo_moto, ano_moto) as keyword type
 * to enable filtering/aggregation in OpenSearch while maintaining searchability.
 *
 * Magento's default KeywordType resolver skips attributes that are both searchable AND filterable,
 * mapping them as text type. This causes OpenSearch BadRequest400Exception when aggregations
 * are attempted on text fields.
 *
 * Note: We return the literal string 'keyword' instead of using ConverterInterface because
 * Magento's base di.xml preference maps ConverterInterface to the legacy Converter
 * (module-elasticsearch/Model/.../Converter.php) which maps INTERNAL_DATA_TYPE_KEYWORD
 * to 'string' (ES 2.x era type). OpenSearch 2.x does not support 'string' type
 * and throws mapper_parsing_exception. The correct ElasticAdapter Converter maps
 * keyword→'keyword' but is not set as the default preference.
 */
class FitmentKeywordType implements ResolverInterface
{
    /**
     * OpenSearch/Elasticsearch 7+ keyword field type
     */
    private const ES_TYPE_KEYWORD = 'keyword';

    /**
     * Fitment attribute codes that need keyword mapping
     */
    private const FITMENT_ATTRIBUTES = [
        'marca_moto',
        'modelo_moto',
        'ano_moto',
    ];

    /**
     * Returns keyword type for fitment attributes regardless of searchable flag.
     *
     * @param AttributeAdapter $attribute
     * @return string|null
     */
    public function getFieldType(AttributeAdapter $attribute): ?string
    {
        if (in_array($attribute->getAttributeCode(), self::FITMENT_ATTRIBUTES, true)) {
            return self::ES_TYPE_KEYWORD;
        }

        return null;
    }
}
