<?php

declare(strict_types=1);

namespace GrupoAwamotos\CatalogFix\Plugin;

use Magento\Framework\Mview\ActionFactory;
use Magento\Framework\Mview\ActionInterface;

/**
 * Prevents InvalidArgumentException when ActionFactory tries to instantiate
 * indexer action classes that don't implement MviewActionInterface.
 *
 * This is a known Magento 2.4.x issue where module-data-exporter's
 * Setup/Recurring.php iterates ALL indexers and calls ActionFactory::get()
 * on classes like customer_grid, design_config_grid, inventory that
 * use action classes not implementing Mview\ActionInterface.
 */
class MviewActionFactoryPlugin
{
    /**
     * Wrap ActionFactory::get() to catch and suppress the InvalidArgumentException
     * for known non-Mview indexer classes, returning a no-op action instead.
     *
     * @param ActionFactory $subject
     * @param callable $proceed
     * @param string $className
     * @return ActionInterface
     * @throws \InvalidArgumentException
     */
    public function aroundGet(
        ActionFactory $subject,
        callable $proceed,
        $className
    ): ActionInterface {
        try {
            return $proceed($className);
        } catch (\InvalidArgumentException $e) {
            // Return a no-op ActionInterface for classes that don't implement it
            return new class implements ActionInterface {
                public function execute($ids): void
                {
                    // No-op: this indexer doesn't support Mview
                }
            };
        }
    }
}
