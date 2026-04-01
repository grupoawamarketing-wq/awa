<?php

declare(strict_types=1);

namespace GrupoAwamotos\SmartSuggestions\Controller\Adminhtml\Export;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Response\Http\FileFactory;
use Magento\Framework\App\Filesystem\DirectoryList;
use GrupoAwamotos\SmartSuggestions\Api\RfmCalculatorInterface;
use GrupoAwamotos\SmartSuggestions\Helper\Config;

/**
 * Export RFM data to CSV
 */
class Rfm extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'GrupoAwamotos_SmartSuggestions::export';

    private FileFactory $fileFactory;
    private RfmCalculatorInterface $rfmCalculator;
    private Config $config;

    public function __construct(
        Context $context,
        FileFactory $fileFactory,
        RfmCalculatorInterface $rfmCalculator,
        Config $config
    ) {
        parent::__construct($context);
        $this->fileFactory = $fileFactory;
        $this->rfmCalculator = $rfmCalculator;
        $this->config = $config;
    }

    /**
     * Execute export
     */
    public function execute()
    {
        try {
            $data = $this->rfmCalculator->calculateAll();
            $delimiter = $this->config->getCsvDelimiter();
            $includeHeaders = $this->config->includeHeaders();

            $content = '';

            // Headers
            if ($includeHeaders) {
                $headers = [
                    'ID Cliente',
                    'Nome',
                    'Nome Fantasia',
                    'CNPJ',
                    'Cidade',
                    'Estado',
                    'Email',
                    'Telefone',
                    'Recência (dias)',
                    'Frequência',
                    'Monetário (R$)',
                    'Score R',
                    'Score F',
                    'Score M',
                    'Score RFM',
                    'Segmento',
                    'Última Compra'
                ];
                $content .= implode($delimiter, $headers) . "\n";
            }

            // Data rows
            foreach ($data as $customer) {
                $row = [
                    $customer['customer_id'],
                    $this->escapeCsvField($customer['customer_name'], $delimiter),
                    $this->escapeCsvField($customer['trade_name'] ?? '', $delimiter),
                    $customer['cnpj'] ?? '',
                    $this->escapeCsvField($customer['city'] ?? '', $delimiter),
                    $customer['state'] ?? '',
                    $customer['email'] ?? '',
                    $customer['phone'] ?? '',
                    $customer['recency'],
                    $customer['frequency'],
                    number_format($customer['monetary'], 2, ',', ''),
                    $customer['r_score'],
                    $customer['f_score'],
                    $customer['m_score'],
                    $customer['rfm_score'],
                    $this->escapeCsvField($customer['segment'], $delimiter),
                    $customer['last_purchase']
                ];
                $content .= implode($delimiter, $row) . "\n";
            }

            // Convert to UTF-8 BOM for Excel compatibility
            $content = "\xEF\xBB\xBF" . $content;

            $fileName = 'rfm_analysis_' . date('Y-m-d_His') . '.csv';

            return $this->fileFactory->create(
                $fileName,
                $content,
                DirectoryList::VAR_DIR,
                'text/csv'
            );
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(__('Error exporting RFM data: %1', $e->getMessage()));
            return $this->_redirect('*/rfm/index');
        }
    }

    /**
     * Escape CSV field if contains delimiter or quotes
     */
    private function escapeCsvField(string $field, string $delimiter): string
    {
        if (strpos($field, $delimiter) !== false || strpos($field, '"') !== false || strpos($field, "\n") !== false) {
            return '"' . str_replace('"', '""', $field) . '"';
        }
        return $field;
    }
}
