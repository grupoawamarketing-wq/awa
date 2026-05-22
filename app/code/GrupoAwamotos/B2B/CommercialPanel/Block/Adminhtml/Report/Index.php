<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\CommercialPanel\Block\Adminhtml\Report;

use GrupoAwamotos\B2B\CommercialPanel\Api\PortfolioScopeInterface;
use GrupoAwamotos\B2B\CommercialPanel\Model\Intelligence\CommercialReportService;
use GrupoAwamotos\B2B\CommercialPanel\Model\TaskType;
use GrupoAwamotos\B2B\Model\ResourceModel\Attendant\CollectionFactory as AttendantCollectionFactory;
use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;

class Index extends Template
{
    public function __construct(
        Context $context,
        private readonly CommercialReportService $reportService,
        private readonly AttendantCollectionFactory $attendantCollectionFactory,
        private readonly PortfolioScopeInterface $portfolioScope,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * @return array<string, mixed>
     */
    public function getReport(): array
    {
        return $this->reportService->buildReport($this->getFilters());
    }

    /**
     * @return array<string, mixed>
     */
    public function getFilters(): array
    {
        return [
            'date_from' => (string) $this->getRequest()->getParam('date_from', date('Y-m-01')),
            'date_to' => (string) $this->getRequest()->getParam('date_to', date('Y-m-d')),
            'attendant_id' => $this->getRequest()->getParam('attendant_id'),
            'customer_status' => (string) $this->getRequest()->getParam('customer_status', ''),
            'task_type' => (string) $this->getRequest()->getParam('task_type', ''),
        ];
    }

    /**
     * @return array<int, string>
     */
    public function getAttendantOptions(): array
    {
        $ids = $this->portfolioScope->getVisibleAttendantIds();
        if ($ids === []) {
            return [];
        }

        $collection = $this->attendantCollectionFactory->create();
        $collection->addFieldToFilter('attendant_id', ['in' => $ids]);

        $options = [];
        foreach ($collection as $attendant) {
            $options[(int) $attendant->getId()] = (string) $attendant->getData('name');
        }

        return $options;
    }

    /**
     * @return array<string, string>
     */
    public function getTaskTypeOptions(): array
    {
        return [
            '' => (string) __('Todos'),
            TaskType::NO_PURCHASE => (string) __('Sem compra'),
            TaskType::PENDING_NO_CONTACT => (string) __('Pendente sem contato'),
            TaskType::ABANDONED_CART => (string) __('Carrinho abandonado'),
            TaskType::MANUAL => (string) __('Manual'),
        ];
    }

    public function getExportUrl(): string
    {
        $filters = $this->getFilters();

        return $this->getUrl('awa_commercial/commercialreport/exportcsv', $filters);
    }

    public function canExport(): bool
    {
        return $this->_authorization->isAllowed('GrupoAwamotos_B2B::commercial_reports_export');
    }
}
