<?php
declare(strict_types=1);

namespace GrupoAwamotos\CatalogFix\Plugin;

use Magento\Config\Model\Config\Structure;
use Magento\Config\Model\Config\Structure\ElementInterface;
use Magento\Paypal\Model\Config\StructurePlugin;

/**
 * Hotfix PHP 8.4: StructurePlugin do PayPal acessa $pathParts[0] sem verificar se o array
 * está vazio, causando Warning "Undefined array key 0" no PHP 8.x quando $pathParts=[].
 *
 * Referência: vendor/magento/module-paypal/Model/Config/StructurePlugin.php:103
 */
class PaypalStructurePluginFix extends StructurePlugin
{
    /**
     * Garante que $pathParts não está vazio antes de acessar $pathParts[0].
     *
     * @param Structure $subject
     * @param \Closure  $proceed
     * @param array     $pathParts
     * @return ElementInterface|null
     */
    public function aroundGetElementByPathParts(
        Structure $subject,
        \Closure $proceed,
        array $pathParts
    ): ?ElementInterface {
        if (empty($pathParts)) {
            return $proceed($pathParts);
        }

        return parent::aroundGetElementByPathParts($subject, $proceed, $pathParts);
    }
}
