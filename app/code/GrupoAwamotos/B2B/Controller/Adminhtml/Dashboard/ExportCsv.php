<?php
declare(strict_types=1);

namespace GrupoAwamotos\B2B\Controller\Adminhtml\Dashboard;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\App\Response\Http\FileFactory;
use Magento\Framework\Controller\Result\Redirect;

class ExportCsv extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'GrupoAwamotos_B2B::dashboard';

    public function __construct(
        Context $context,
        private readonly ResourceConnection $resourceConnection,
        private readonly FileFactory $fileFactory
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $period = (int) $this->getRequest()->getParam('period', 30);
        $allowedPeriods = [7, 30, 90, 0];
        if (!in_array($period, $allowedPeriods, true)) {
            $this->messageManager->addErrorMessage(__('Período inválido para exportação.'));
            /** @var Redirect $redirect */
            $redirect = $this->resultRedirectFactory->create();
            return $redirect->setPath('*/*/index');
        }

        $content = $this->buildCsvContent($period);
        $periodLabel = $period === 0 ? 'all' : (string) $period;
        $fileName = sprintf('b2b_meta_dashboard_%s_days_%s.csv', $periodLabel, date('Ymd_His'));

        return $this->fileFactory->create(
            $fileName,
            $content,
            DirectoryList::VAR_DIR,
            'text/csv; charset=UTF-8'
        );
    }

    private function buildCsvContent(int $period): string
    {
        $conn = $this->resourceConnection->getConnection();
        $logTable = $this->resourceConnection->getTableName('grupoawamotos_b2b_customer_approval_log');
        $customerTable = $this->resourceConnection->getTableName('customer_entity');
        $companyTable = $this->resourceConnection->getTableName('grupoawamotos_b2b_company');

        $periodCondition = $period === 0
            ? ''
            : sprintf(' AND l.created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)', $period);

        $sql = "SELECT
                    l.log_id,
                    l.action,
                    l.comment,
                    l.created_at,
                    ce.email,
                    CONCAT(ce.firstname, ' ', ce.lastname) AS customer_name,
                    co.razao_social AS company_name,
                    co.cnpj
                FROM {$logTable} l
                INNER JOIN {$customerTable} ce ON ce.entity_id = l.customer_id
                LEFT JOIN {$companyTable} co ON co.admin_customer_id = l.customer_id
                WHERE 1=1{$periodCondition}
                ORDER BY l.log_id DESC";

        $rows = $conn->fetchAll($sql);

        $map = [
            'registered' => 'CompleteRegistration',
            'approved' => 'SubmitApplication (approved)',
            'rejected' => 'SubmitApplication (rejected)',
            'suspended' => '-',
        ];

        $stream = fopen('php://temp', 'w+');
        if ($stream === false) {
            return '';
        }

        fwrite($stream, "\xEF\xBB\xBF");
        fputcsv($stream, [
            'log_id',
            'data_hora',
            'acao',
            'evento_meta_capi',
            'empresa',
            'cliente',
            'email',
            'cnpj',
            'comentario',
        ]);

        foreach ($rows as $row) {
            $action = (string) ($row['action'] ?? '');
            fputcsv($stream, [
                (string) ($row['log_id'] ?? ''),
                (string) ($row['created_at'] ?? ''),
                $action,
                $map[$action] ?? '-',
                (string) ($row['company_name'] ?? ''),
                (string) ($row['customer_name'] ?? ''),
                (string) ($row['email'] ?? ''),
                (string) ($row['cnpj'] ?? ''),
                (string) ($row['comment'] ?? ''),
            ]);
        }

        rewind($stream);
        $csv = (string) stream_get_contents($stream);
        fclose($stream);

        return $csv;
    }
}
