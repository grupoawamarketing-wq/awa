<?php

declare(strict_types=1);

namespace GrupoAwamotos\CatalogFix\Plugin;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\CustomerData\LastOrderedItems;
use Psr\Log\LoggerInterface;

/**
 * Prevent NoSuchEntityException from propagating when a previously ordered
 * product has been deleted or disabled from the catalog.
 */
final class LastOrderedItemsPlugin
{
    public function __construct(
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @param LastOrderedItems $subject
     * @param callable $proceed
     * @return array<string, mixed>
     */
    public function aroundGetSectionData(
        LastOrderedItems $subject,
        callable $proceed
    ): array {
        try {
            return $proceed();
        } catch (NoSuchEntityException $e) {
            $this->logger->debug(
                '[CatalogFix] LastOrderedItems: skipped deleted product',
                ['message' => $e->getMessage()]
            );
            return ['items' => []];
        }
    }
}
