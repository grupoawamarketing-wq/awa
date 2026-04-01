<?php

declare(strict_types=1);

namespace GrupoAwamotos\Fitment\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Store\Model\ScopeInterface;

class Config extends AbstractHelper
{
    public const XML_PATH_SKU_WEIGHT = 'grupoawamotos_fitment/fallback/sku_weight';
    public const XML_PATH_META_KEYWORD_WEIGHT = 'grupoawamotos_fitment/fallback/meta_keyword_weight';
    public const XML_PATH_SYNONYMS = 'grupoawamotos_fitment/fallback/synonyms';

    public function getSkuWeight(?int $storeId = null): int
    {
        $v = (int)$this->scopeConfig->getValue(self::XML_PATH_SKU_WEIGHT, ScopeInterface::SCOPE_STORE, $storeId);
        return $v > 0 ? $v : 3;
    }

    public function getMetaKeywordWeight(?int $storeId = null): int
    {
        $v = (int)$this->scopeConfig->getValue(self::XML_PATH_META_KEYWORD_WEIGHT, ScopeInterface::SCOPE_STORE, $storeId);
        return $v > 0 ? $v : 2;
    }

    public function getSynonymGroups(?int $storeId = null): array
    {
        $raw = (string)$this->scopeConfig->getValue(self::XML_PATH_SYNONYMS, ScopeInterface::SCOPE_STORE, $storeId);
        if (trim($raw) === '') {
            return [];
        }
        $lines = preg_split('/\r?\n/', trim($raw));
        $groups = [];
        foreach ($lines as $line) {
            $parts = array_filter(array_map(fn($p)=>trim(strtolower($p)), explode(',', $line)), fn($p)=>$p !== '');
            if (count($parts) > 1) {
                $groups[] = array_unique($parts);
            }
        }
        return $groups;
    }
}
