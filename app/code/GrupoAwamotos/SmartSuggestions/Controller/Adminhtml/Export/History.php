<?php

declare(strict_types=1);

namespace GrupoAwamotos\SmartSuggestions\Controller\Adminhtml\Export;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Response\Http\FileFactory;
use Magento\Framework\App\Filesystem\DirectoryList;
use GrupoAwamotos\SmartSuggestions\Model\ResourceModel\SuggestionHistory\CollectionFactory;
use GrupoAwamotos\SmartSuggestions\Helper\Config;

/**
 * Export History to CSV
 */
class History extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'GrupoAwamotos_SmartSuggestions::export';

    private FileFactory $fileFactory;
    private CollectionFactory $collectionFactory;
    private Config $config;

    public function __construct(
        Context $context,
        FileFactory $fileFactory,
        CollectionFactory $collectionFactory,
        Config $config
    ) {
        parent::__construct($context);
        $this->fileFactory = $fileFactory;
        $this->collectionFactory = $collectionFactory;
        $this->config = $config;
    }

    /**
     * Execute export
     */
    public function execute()
    {
        try {
            $collection = $this->collectionFactory->create();
            $collection->setOrder('created_at', 'DESC');

            $delimiter = $this->config->getCsvDelimiter();
            $includeHeaders = $this->config->includeHeaders();

            $content = '';

            // Headers
            if ($includeHeaders) {
                $headers = [
                    'ID',
                    'ID Cliente',
                    'Nome Cliente',
                    'Telefone',
                    'Valor Sugerido (R$)',
                    'Qtd Produtos',
                    'Status',
                    'Canal',
                    'Criada em',
                    'Enviada em',
                    'Convertida em',
                    'Valor Convertido (R$)',
                    'ID Pedido'
                ];
                $content .= implode($delimiter, $headers) . "\n";
            }

            // Data rows
            foreach ($collection as $item) {
                $row = [
                    $item->getId(),
                    $item->getCustomerId(),
                    $this->escapeCsvField($item->getCustomerName(), $delimiter),
                    $item->getData('customer_phone') ?? '',
                    number_format((float) $item->getTotalValue(), 2, ',', ''),
                    $item->getData('products_count'),
                    $item->getStatus(),
                    $item->getChannel() ?? '',
                    $item->getData('created_at'),
                    $item->getData('sent_at') ?? '',
                    $item->getData('converted_at') ?? '',
                    $item->getData('conversion_value') ? number_format((float) $item->getData('conversion_value'), 2, ',', '') : '',
                    $item->getData('converted_order_id') ?? ''
                ];
                $content .= implode($delimiter, $row) . "\n";
            }

            // Convert to UTF-8 BOM for Excel compatibility
            $content = "\xEF\xBB\xBF" . $content;

            $fileName = 'suggestions_history_' . date('Y-m-d_His') . '.csv';

            return $this->fileFactory->create(
                $fileName,
                $content,
                DirectoryList::VAR_DIR,
                'text/csv'
            );
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(__('Error exporting history: %1', $e->getMessage()));
            return $this->_redirect('*/history/index');
        }
    }

    /**
     * Escape CSV field
     */
    private function escapeCsvField(string $field, string $delimiter): string
    {
        if (strpos($field, $delimiter) !== false || strpos($field, '"') !== false || strpos($field, "\n") !== false) {
            return '"' . str_replace('"', '""', $field) . '"';
        }
        return $field;
    }
}
