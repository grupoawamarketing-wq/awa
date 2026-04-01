<?php

declare(strict_types=1);

namespace Ayo\Curriculo\Model;

use Magento\Framework\ObjectManagerInterface;

/**
 * Factory para \Ayo\Curriculo\Model\Submission.
 *
 * Em instalações Magento, esse factory costuma ser gerado em build (setup:di:compile).
 * Este arquivo existe para análise estática/IDE no workspace.
 */
class SubmissionFactory
{
    public function __construct(
        private readonly ObjectManagerInterface $objectManager
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data = []): Submission
    {
        /** @var Submission $model */
        $model = $this->objectManager->create(Submission::class, $data);
        return $model;
    }
}
