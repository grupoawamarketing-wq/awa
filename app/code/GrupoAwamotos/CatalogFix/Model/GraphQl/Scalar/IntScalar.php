<?php

declare(strict_types=1);

namespace GrupoAwamotos\CatalogFix\Model\GraphQl\Scalar;

use GraphQL\Language\AST\IntValueNode;
use Magento\Framework\GraphQl\Schema\Type\Scalar\CustomScalarInterface;

class IntScalar implements CustomScalarInterface
{
    public function serialize($value)
    {
        return (int) $value;
    }

    public function parseValue($value)
    {
        return (int) $value;
    }

    public function parseLiteral($valueNode, ?array $variables = null)
    {
        return $valueNode instanceof IntValueNode ? (int) $valueNode->value : null;
    }
}
