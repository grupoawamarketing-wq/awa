<?php

declare(strict_types=1);

namespace GrupoAwamotos\CatalogFix\Block\Brand;

/**
 * Override of Rokanthemes\Brand\Block\Brand\View to fix undefined variable $page_title.
 *
 * In PHP 8+, accessing an uninitialized variable is a Warning (logged as ERROR
 * by Magento's error handler). The original _prepareLayout() sets $page_title
 * only inside an if-block, leaving it undefined when the brand has no name.
 */
class View extends \Rokanthemes\Brand\Block\Brand\View
{
    /**
     * @return \Magento\Framework\View\Element\AbstractBlock
     */
    protected function _prepareLayout()
    {
        $brand = $this->getCurrentBrand();
        $page_title = null;
        if ($brand->getName()) {
            $page_title = $brand->getName();
        }
        $meta_description = $brand->getMetaDescription();
        $meta_keywords = $brand->getMetaKeywords();
        $this->_addBreadcrumbs();
        if ($page_title) {
            $this->pageConfig->getTitle()->set($page_title);
        }
        if ($meta_keywords) {
            $this->pageConfig->setKeywords($meta_keywords);
        }
        if ($meta_description) {
            $this->pageConfig->setDescription($meta_description);
        }
        return \Magento\Framework\View\Element\Template::_prepareLayout();
    }
}
