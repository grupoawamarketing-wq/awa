<?php

declare(strict_types=1);

namespace GrupoAwamotos\CatalogFix\Plugin\Search;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;
use Magento\Framework\Serialize\Serializer\Json;
use Mirasvit\SearchAutocomplete\InstantProvider\ConfigMaker;

/**
 * Fix: instant.json store_id=0 uses non-existent index names.
 *
 * Mirasvit generates instant.json with store_id=0 entries (e.g. magento2_product_0)
 * that are never created by the indexer. When a request omits the store_id parameter,
 * ConfigProvider defaults to 0 and the OpenSearch query fails with 404 (caught silently
 * resulting in 0 results).
 *
 * After ensure() writes instant.json, this plugin copies the real store_id=1
 * elasticsearch7 index names into the store_id=0 section so that the fallback
 * always hits valid indices.
 */
class InstantJsonStoreZeroFixPlugin
{
    private Filesystem $filesystem;

    private Json $serializer;

    public function __construct(Filesystem $filesystem, Json $serializer)
    {
        $this->filesystem = $filesystem;
        $this->serializer = $serializer;
    }

    public function afterEnsure(ConfigMaker $subject): void
    {
        $dir  = $this->filesystem->getDirectoryWrite(DirectoryList::CONFIG);
        $path = $dir->getRelativePath('instant.json');

        if (!$dir->isExist($path)) {
            return;
        }

        $raw    = $dir->readFile($path);
        $config = $this->serializer->unserialize($raw);

        if (!is_array($config)) {
            return;
        }

        // Find the first real store's elasticsearch7 section (store_id >= 1)
        $sourceEngine = null;
        for ($storeId = 1; $storeId <= 10; $storeId++) {
            $key = "{$storeId}/elasticsearch7";
            if (isset($config[$key]) && is_array($config[$key])) {
                $sourceEngine = $config[$key];
                break;
            }
        }

        if ($sourceEngine === null || !isset($config['0/elasticsearch7'])) {
            return;
        }

        $indexKeys = [
            'magento_catalog_product',
            'magento_catalog_category',
            'magento_cms_page',
            'mst_misspell_index',
        ];

        $changed = false;
        foreach ($indexKeys as $indexKey) {
            if (isset($sourceEngine[$indexKey])
                && isset($config['0/elasticsearch7'][$indexKey])
                && $config['0/elasticsearch7'][$indexKey] !== $sourceEngine[$indexKey]
            ) {
                $config['0/elasticsearch7'][$indexKey] = $sourceEngine[$indexKey];
                $changed = true;
            }
        }

        if (!$changed) {
            return;
        }

        $tmp = $path . '.tmp.' . uniqid('', true);
        $dir->writeFile($tmp, $this->serializer->serialize($config));
        $dir->renameFile($tmp, $path);
    }
}
