<?php

declare(strict_types=1);

namespace GrupoAwamotos\SmartSuggestions\Controller\Adminhtml\Export;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Response\Http\FileFactory;
use Magento\Framework\App\Filesystem\DirectoryList;
use GrupoAwamotos\SmartSuggestions\Api\SuggestionEngineInterface;
use GrupoAwamotos\SmartSuggestions\Helper\Config;

/**
 * Export Suggestions/Opportunities to CSV
 */
class Suggestions extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'GrupoAwamotos_SmartSuggestions::export';

    private FileFactory $fileFactory;
    private SuggestionEngineInterface $suggestionEngine;
    private Config $config;

    public function __construct(
        Context $context,
        FileFactory $fileFactory,
        SuggestionEngineInterface $suggestionEngine,
        Config $config
    ) {
        parent::__construct($context);
        $this->fileFactory = $fileFactory;
        $this->suggestionEngine = $suggestionEngine;
        $this->config = $config;
    }

    /**
     * Execute export
     */
    public function execute()
    {
        try {
            $opportunities = $this->suggestionEngine->getTopOpportunities(500);
            $delimiter = $this->config->getCsvDelimiter();
            $includeHeaders = $this->config->includeHeaders();

            $content = '';

            // Headers
            if ($includeHeaders) {
                $headers = [
                    'ID Cliente',
                    'Nome',
                    'Segmento',
                    'Dias desde Última Compra',
                    'Ciclo Médio (dias)',
                    'Razão Atraso',
                    'Score RFM',
                    'Valor Est. Carrinho (R$)',
                    'Score Oportunidade'
                ];
                $content .= implode($delimiter, $headers) . "\n";
            }

            // Data rows
            foreach ($opportunities as $opp) {
                $row = [
                    $opp['customer_id'],
                    $this->escapeCsvField($opp['customer_name'], $delimiter),
                    $this->escapeCsvField($opp['segment'], $delimiter),
                    $opp['days_since_purchase'],
                    $opp['avg_cycle'],
                    number_format($opp['overdue_ratio'], 2, ',', ''),
                    $opp['rfm_score'],
                    number_format($opp['estimated_cart_value'], 2, ',', ''),
                    number_format($opp['opportunity_score'], 2, ',', '')
                ];
                $content .= implode($delimiter, $row) . "\n";
            }

            // Convert to UTF-8 BOM for Excel compatibility
            $content = "\xEF\xBB\xBF" . $content;

            $fileName = 'opportunities_' . date('Y-m-d_His') . '.csv';

            return $this->fileFactory->create(
                $fileName,
                $content,
                DirectoryList::VAR_DIR,
                'text/csv'
            );
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(__('Error exporting opportunities: %1', $e->getMessage()));
            return $this->_redirect('*/suggestions/index');
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
