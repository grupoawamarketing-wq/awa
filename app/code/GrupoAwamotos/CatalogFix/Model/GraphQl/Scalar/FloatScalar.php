<?php

declare(strict_types=1);

namespace GrupoAwamotos\CatalogFix\Model\GraphQl\Scalar;

use GraphQL\Language\AST\FloatValueNode;
use GraphQL\Language\AST\IntValueNode;
use Magento\Framework\GraphQl\Schema\Type\Scalar\CustomScalarInterface;

class FloatScalar implements CustomScalarInterface
{
    public function serialize($value)
    {
        return (float) $value;
    }

    public function parseValue($value)
    {
        return (float) $value;
    }

    public function parseLiteral($valueNode, ?array $variables = null)
    {
        if ($valueNode instanceof FloatValueNode || $valueNode instanceof IntValueNode) {
            return (float) $valueNode->value;
        }
        return null;
    }
}
