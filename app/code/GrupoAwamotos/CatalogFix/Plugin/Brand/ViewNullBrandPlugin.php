<?php

declare(strict_types=1);

namespace GrupoAwamotos\CatalogFix\Plugin\Brand;

use Rokanthemes\Brand\Block\Brand\View;
use Rokanthemes\Brand\Model\BrandFactory;

/**
 * Guard against null brand in Brand\View block to prevent TypeError.
 *
 * When a brand URL is accessed but no brand exists in registry (deleted/disabled),
 * getCurrentBrand() returns null. This causes TypeError in _prepareLayout() and
 * _addBreadcrumbs() which call $brand->getName() on null.
 *
 * Returns an empty Brand model instance to prevent the crash.
 */
class ViewNullBrandPlugin
{
    public function __construct(
        private readonly BrandFactory $brandFactory,
    ) {
    }

    /**
     * @param View $subject
     * @param mixed $result
     * @return \Rokanthemes\Brand\Model\Brand
     */
    public function afterGetCurrentBrand(View $subject, $result)
    {
        if ($result === null) {
            return $this->brandFactory->create();
        }

        return $result;
    }
}
