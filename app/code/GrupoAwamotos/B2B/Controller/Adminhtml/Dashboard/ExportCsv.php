<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Controller\Adminhtml\Dashboard;

use GrupoAwamotos\B2B\Platform\Dashboard\DashboardFilter;
use GrupoAwamotos\B2B\Platform\Dashboard\ExecutiveDashboardService;
use GrupoAwamotos\B2B\Platform\Dashboard\Export\ExecutiveDashboardCsvExporter;
use GrupoAwamotos\B2B\Platform\Model\Config\PlatformConfig;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\Response\Http\FileFactory;
use Magento\Framework\Controller\ResultInterface;

class ExportCsv extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'GrupoAwamotos_B2B::platform_dashboard_export';

    public function __construct(
        Context $context,
        private readonly PlatformConfig $platformConfig,
        private readonly ExecutiveDashboardService $dashboardService,
        private readonly ExecutiveDashboardCsvExporter $csvExporter,
        private readonly FileFactory $fileFactory
    ) {
        parent::__construct($context);
    }

    public function execute(): ResultInterface
    {
        if (!$this->platformConfig->isExecutiveDashboardEnabled()) {
            $this->messageManager->addErrorMessage(__('Dashboard executivo desativado.'));

            return $this->resultRedirectFactory->create()->setPath('awa_commercial/commercialdashboard/index');
        }

        $filter = DashboardFilter::fromRequestParams(
            $this->getRequest()->getParam('date_from'),
            $this->getRequest()->getParam('date_to')
        );

        $dashboard = $this->dashboardService->buildDashboard($filter);
        $csv = $this->csvExporter->export($dashboard);
        $fileName = 'b2b_executive_dashboard_' . date('Y-m-d_His') . '.csv';

        return $this->fileFactory->create(
            $fileName,
            ['type' => 'string', 'value' => $csv, 'rm' => true],
            DirectoryList::VAR_DIR,
            'text/csv'
        );
    }

    protected function _isAllowed(): bool
    {
        return $this->_authorization->isAllowed(self::ADMIN_RESOURCE)
            || $this->_authorization->isAllowed('GrupoAwamotos_B2B::commercial_reports_export');
    }
}
