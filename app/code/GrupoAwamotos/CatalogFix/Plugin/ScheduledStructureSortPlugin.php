<?php
declare(strict_types=1);

namespace GrupoAwamotos\CatalogFix\Plugin;

use Magento\Framework\View\Layout\ScheduledStructure;

/**
 * Suppress the "Broken reference" INFO log for product.info.media.
 *
 * The vendor catalog layout defines:
 *   <container name="product.info.media" ... before="product.info.main">
 *
 * This causes Magento to call setElementToSortList('content', 'product.info.media',
 * 'product.info.main', false) — recording a sibling-sort instruction in elementsToSort.
 *
 * The Ayo theme moves product.info.main into product.col.info and product.info.media
 * into product.col.media via <move> elements. However, <move> uses setElementToMove
 * (a separate list), so the original elementsToSort entry is NEVER cleared.
 *
 * At structure-generation time, reorderChildElement('content', 'product.info.media',
 * 'product.info.main', false) is called. Since product.info.main is now in product.col.info
 * (not content), Structure.php emits a "Broken reference" INFO log in developer mode.
 *
 * The actual layout renders correctly — the Ayo theme's <move> instructions position both
 * elements properly. This plugin simply suppresses the now-irrelevant sort instruction.
 */
class ScheduledStructureSortPlugin
{
    /**
     * Skip the sort instruction for product.info.media→product.info.main.
     *
     * The vendor container definition creates this instruction, but the Ayo theme
     * moves both elements to different containers, making the instruction invalid.
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
