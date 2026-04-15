<?php

declare(strict_types=1);

namespace GrupoAwamotos\CatalogFix\Model\GraphQl\Scalar;

use GraphQL\Language\AST\IntValueNode;
use GraphQL\Language\AST\StringValueNode;
use Magento\Framework\GraphQl\Schema\Type\Scalar\CustomScalarInterface;

class IdScalar implements CustomScalarInterface
{
    public function serialize($value)
    {
        return (string) $value;
    }

    public function parseValue($value)
    {
        return (string) $value;
    }

    public function parseLiteral($valueNode, ?array $variables = null)
    {
        if ($valueNode instanceof StringValueNode || $valueNode instanceof IntValueNode) {
            return (string) $valueNode->value;
        }
        return null;
    }
}
