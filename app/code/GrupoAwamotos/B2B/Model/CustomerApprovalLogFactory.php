<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Model;

use Magento\Framework\ObjectManagerInterface;

/**
 * Factory for CustomerApprovalLog model.
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class CustomerApprovalLogFactory
{
    /**
     * @var ObjectManagerInterface
     */
    private ObjectManagerInterface $objectManager;

    /**
     * @var string
     */
    private string $instanceName;

    public function __construct(
        ObjectManagerInterface $objectManager,
        string $instanceName = CustomerApprovalLog::class
    ) {
        $this->objectManager = $objectManager;
        $this->instanceName  = $instanceName;
    }

    /**
     * Create new CustomerApprovalLog instance.
     *
     * @param array $data
     * @return CustomerApprovalLog
     */
    public function create(array $data = []): CustomerApprovalLog
    {
        return $this->objectManager->create($this->instanceName, $data);
    }
}
