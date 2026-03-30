<?php
declare(strict_types=1);

namespace GrupoAwamotos\LayoutFix\Plugin;

use Magento\Framework\View\Layout\ScheduledStructure;

/**
 * Suppress the "Broken reference" INFO log for product.info.media.
 *
 * The vendor catalog layout defines:
 *   <container name="product.info.media" ... before="product.info.main">
 *
 * The Ayo theme moves product.info.main into product.col.info and product.info.media
 * into product.col.media via <move> elements, but the original sort instruction in
 * elementsToSort is never cleared, causing a "Broken reference" log on every PDP view.
 *
 * This plugin suppresses the now-irrelevant sort instruction.
 */
class ScheduledStructureSortPlugin
{
    /**
     * Skip the sort instruction for product.info.media→product.info.main.
     *
     * @param ScheduledStructure $subject
     * @param \Closure $proceed
     * @param string $parentName
     * @param string $elementName
     * @param string|int|null $offsetOrSibling
     * @param bool $isAfter
     * @return void
     */
    public function aroundSetElementToSortList(
        ScheduledStructure $subject,
        \Closure $proceed,
        $parentName,
        $elementName,
        $offsetOrSibling,
        $isAfter = true
    ): void {
        if ($elementName === 'product.info.media' && $offsetOrSibling === 'product.info.main') {
            return;
        }
        $proceed($parentName, $elementName, $offsetOrSibling, $isAfter);
    }
}
