<?php
declare(strict_types=1);

namespace GrupoAwamotos\CatalogFix\Plugin\Interception;

use Magento\Framework\Interception\Config\CacheManager;

/**
 * Fix PHP 8 TypeError em CacheManager::load() durante race condition de compile.
 *
 * Problema: CacheManager::isCompiled() verifica file_exists() e depois
 * compiledLoader->load() faz include() do mesmo arquivo. Se o arquivo for
 * deletado entre as duas chamadas (ex: durante setup:di:compile), o include()
 * retorna false. Como load() tem return type ?array, o PHP 8 lanca TypeError.
 *
 * Solucao: afterLoad plugin converte false -> null (cache miss legitimo).
 *
 * @see vendor/magento/framework/Interception/Config/CacheManager.php
 * @see vendor/magento/framework/App/ObjectManager/ConfigLoader/Compiled.php
 */
final class CacheManagerPlugin
{
    /**
     * Converte retorno false (race condition) para null (?array compativel).
     *
     * @param CacheManager $subject
     * @param array|false|null $result
     * @return array|null
     */
    public function afterLoad(CacheManager $subject, $result): ?array
    {
        return $result === false ? null : $result;
    }
}
