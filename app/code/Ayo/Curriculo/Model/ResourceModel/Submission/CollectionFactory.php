<?php

declare(strict_types=1);

namespace Ayo\Curriculo\Model\ResourceModel\Submission;

use Magento\Framework\ObjectManagerInterface;

/**
 * Collection factory.
 *
 * Observação: em Magento, factories de Collection costumam ser geradas em tempo de build (setup:di:compile).
 * Este arquivo existe para dar suporte a análise estática/IDE no workspace e manter o DI explícito.
 */
class CollectionFactory
{
    public function __construct(
        private readonly ObjectManagerInterface $objectManager
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data = []): Collection
    {
        /** @var Collection $collection */
        $collection = $this->objectManager->create(Collection::class, $data);
        return $collection;
    }
}
