<?php

declare(strict_types=1);

namespace GrupoAwamotos\CatalogFix\Plugin\LayeredAjax;

use Magento\Catalog\Controller\Category\View as CategoryView;
use Magento\Framework\View\Result\Page;

/**
 * Fix: Rokanthemes\LayeredAjax\Plugins\Controller\Category\View::afterExecute() chama
 * $page->getLayout() sem verificar se $page é um Page result. Quando o request é
 * interceptado anteriormente e retorna um Raw result, getLayout() não existe e lança
 * CRITICAL: Call to undefined method ...Raw\Interceptor::getLayout().
 *
 * Solução: Preference que substitui o plugin original com a verificação de tipo adequada.
 */
class CategoryViewPlugin
{
    private \Rokanthemes\LayeredAjax\Helper\Data $moduleHelper;
    private \Magento\Framework\Json\Helper\Data $jsonHelper;

    public function __construct(
        \Magento\Framework\Json\Helper\Data $jsonHelper,
        \Rokanthemes\LayeredAjax\Helper\Data $moduleHelper
    ) {
        $this->jsonHelper   = $jsonHelper;
        $this->moduleHelper = $moduleHelper;
    }

    /**
     * Substitui afterExecute original com verificação de tipo antes de chamar getLayout().
     */
    public function afterExecute(CategoryView $action, mixed $page): mixed
    {
        if (!$this->moduleHelper->isEnabled() || !$action->getRequest()->getParam('isAjax')) {
            return $page;
        }

        // Guard: só chamar getLayout() em Page results — Raw results não possuem layout.
        if (!$page instanceof Page) {
            return $page;
        }

        $navigation = $page->getLayout()->getBlock('catalog.leftnav');
        $products   = $page->getLayout()->getBlock('category.products');
        $result     = [
            'products'   => $products ? $products->toHtml() : '',
            'navigation' => $navigation ? $navigation->toHtml() : '',
        ];
        $action->getResponse()->representJson($this->jsonHelper->jsonEncode($result));

        return $page;
    }
}
