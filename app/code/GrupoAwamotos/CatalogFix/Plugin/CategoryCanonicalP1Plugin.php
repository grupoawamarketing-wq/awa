<?php

declare(strict_types=1);

namespace GrupoAwamotos\CatalogFix\Plugin;

use Magento\Catalog\Helper\Category;

/**
 * SEO-001: Strip ?p=1 from category canonical URL.
 *
 * Magento core Category::getCanonicalUrl() always appends ?p=N when the
 * request has a page parameter — including ?p=1, which is semantically
 * identical to the base category URL. This creates duplicate canonicals
 * (/category.html and /category.html?p=1) for the first page.
 *
 * This plugin strips ?p=1 (only page 1) so it canonicalises to the clean URL.
 * Pages ?p=2+ are left untouched.
 */
class CategoryCanonicalP1Plugin
{
    /**
     * @param Category $subject
     * @param string $result
     * @return string
     */
    public function afterGetCanonicalUrl(Category $subject, string $result): string
    {
        return preg_replace('/\?p=1$/', '', $result) ?? $result;
    }
}
