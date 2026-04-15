<?php

declare(strict_types=1);

namespace GrupoAwamotos\CatalogFix\Model\GraphQl\Scalar;

use GraphQL\Language\AST\BooleanValueNode;
use Magento\Framework\GraphQl\Schema\Type\Scalar\CustomScalarInterface;

class BooleanScalar implements CustomScalarInterface
{
    public function serialize($value)
    {
        return (bool) $value;
    }

    public function parseValue($value)
    {
        return (bool) $value;
    }

    public function parseLiteral($valueNode, ?array $variables = null)
    {
        return $valueNode instanceof BooleanValueNode ? (bool) $valueNode->value : null;
    }
}
