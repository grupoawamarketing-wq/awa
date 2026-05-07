<?php

declare(strict_types=1);

namespace GrupoAwamotos\CatalogFix\Plugin\Search;

use GrupoAwamotos\CatalogFix\Model\InstantJsonFixer;
use Magento\CatalogSearch\Model\Indexer\Fulltext;

/**
 * Fix: instant.json store_id=0 fallback uses non-existent OpenSearch index names.
 *
 * Mirasvit's FullReindexPlugin::aroundExecuteFull() calls ConfigMaker::ensure()
 * which regenerates instant.json. The generated store_id=0 elasticsearch7 section
 * maps to magento2_product_0 etc. — indices that are never created.
 *
 * By plugging afterExecuteFull on Magento\CatalogSearch\Model\Indexer\Fulltext
 * (which IS interceptable), we run AFTER FullReindexPlugin's around plugin has
 * already called ensure() and written instant.json.
 */
class InstantJsonStoreZeroFixPlugin
{
    private InstantJsonFixer $fixer;

    public function __construct(InstantJsonFixer $fixer)
    {
        $this->fixer = $fixer;
    }

    /**
     * After the catalogsearch_fulltext reindex completes (which triggers
     * Mirasvit's ConfigMaker::ensure()), fix instant.json store_id=0 indices.
     *
     * @param Fulltext $subject
     * @param mixed    $result
     * @return mixed
     */
    public function afterExecuteFull(Fulltext $subject, $result)
    {
        $this->fixer->fix();
        return $result;
    }
}
