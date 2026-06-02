<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Block\Adminhtml\SectraQueue;

use GrupoAwamotos\B2B\Model\Sectra\SectraOrderQueueQuery;
use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;

class Summary extends Template
{
    protected $_template = 'GrupoAwamotos_B2B::sectra_queue/summary.phtml';

    public function __construct(
        Context $context,
        private readonly SectraOrderQueueQuery $queueQuery,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * @return array<string, int>
     */
    public function getSummary(): array
    {
        return $this->queueQuery->getSummary();
    }

    /**
     * @return array{duplicate_erp_codes: int, affected_customers: int}
     */
    public function getDuplicateErpSummary(): array
    {
        return $this->queueQuery->getDuplicateErpCodeSummary();
    }

    public function getErpPendingUrl(): string
    {
        return $this->getUrl('grupoawamotos_b2b/customer/erpPending');
    }
}
