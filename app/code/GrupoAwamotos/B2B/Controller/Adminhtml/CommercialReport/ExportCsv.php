<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Controller\Adminhtml\CommercialReport;

use GrupoAwamotos\B2B\CommercialPanel\Model\Intelligence\CommercialCsvExporter;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Response\Http\FileFactory;
use Magento\Framework\Controller\ResultInterface;

class ExportCsv extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'GrupoAwamotos_B2B::commercial_reports_export';

    public function __construct(
        Context $context,
        private readonly CommercialCsvExporter $csvExporter,
        private readonly FileFactory $fileFactory
    ) {
        parent::__construct($context);
    }

    public function execute(): ResultInterface
    {
        $filters = [
            'date_from' => (string) $this->getRequest()->getParam('date_from', date('Y-m-01')),
            'date_to' => (string) $this->getRequest()->getParam('date_to', date('Y-m-d')),
            'attendant_id' => $this->getRequest()->getParam('attendant_id'),
            'customer_status' => (string) $this->getRequest()->getParam('customer_status', ''),
            'task_type' => (string) $this->getRequest()->getParam('task_type', ''),
        ];

        try {
            $csv = $this->csvExporter->exportReportCsv($filters);
            $fileName = 'relatorio_comercial_' . date('Ymd_His') . '.csv';

            return $this->fileFactory->create(
                $fileName,
                ['type' => 'string', 'value' => $csv, 'rm' => true],
                \Magento\Framework\App\Filesystem\DirectoryList::VAR_DIR,
                'text/csv'
            );
        } catch (\Throwable) {
            $this->messageManager->addErrorMessage(__('Não foi possível exportar o relatório.'));
            $resultRedirect = $this->resultRedirectFactory->create();

            return $resultRedirect->setPath('awa_commercial/commercialreport/index');
        }
    }
}
