<?php

declare(strict_types=1);

namespace GrupoAwamotos\CatalogFix\Model\GraphQl\Scalar;

use GraphQL\Language\AST\StringValueNode;
use Magento\Framework\GraphQl\Schema\Type\Scalar\CustomScalarInterface;

class StringScalar implements CustomScalarInterface
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
        return $valueNode instanceof StringValueNode ? (string) $valueNode->value : null;
    }
}
