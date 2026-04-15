<?php

declare(strict_types=1);

namespace GrupoAwamotos\CatalogFix\Plugin\GraphQl;

use Magento\Framework\GraphQl\Config;
use Magento\Framework\GraphQl\Config\ConfigElementInterface;
use Magento\Framework\GraphQl\Config\Element\Scalar;

class ConfigScalarFallbackPlugin
{
    /** @var array<string, string> */
    private const SCALAR_IMPLEMENTATIONS = [
        'Int' => \GrupoAwamotos\CatalogFix\Model\GraphQl\Scalar\IntScalar::class,
        'Float' => \GrupoAwamotos\CatalogFix\Model\GraphQl\Scalar\FloatScalar::class,
        'String' => \GrupoAwamotos\CatalogFix\Model\GraphQl\Scalar\StringScalar::class,
        'Boolean' => \GrupoAwamotos\CatalogFix\Model\GraphQl\Scalar\BooleanScalar::class,
        'ID' => \GrupoAwamotos\CatalogFix\Model\GraphQl\Scalar\IdScalar::class,
    ];

    public function aroundGetConfigElement(
        Config $subject,
        callable $proceed,
        string $configElementName
    ): ConfigElementInterface {
        try {
            return $proceed($configElementName);
        } catch (\LogicException $exception) {
            if (!isset(self::SCALAR_IMPLEMENTATIONS[$configElementName])) {
                throw $exception;
            }

            return new Scalar(
                $configElementName,
                sprintf('Fallback scalar for %s', $configElementName),
                self::SCALAR_IMPLEMENTATIONS[$configElementName]
            );
        }
    }
}
