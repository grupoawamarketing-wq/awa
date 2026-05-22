<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Ui\Component\Listing\Column;

use GrupoAwamotos\B2B\Model\Customer\Attribute\Source\ErpCustomerSyncStatus;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;

class ErpSyncStatusLabel extends Column
{
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        private readonly ErpCustomerSyncStatus $erpStatusSource,
        array $components = [],
        array $data = []
    ) {
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    public function prepareDataSource(array $dataSource): array
    {
        if (!isset($dataSource['data']['items'])) {
            return $dataSource;
        }

        $labels = [];
        foreach ($this->erpStatusSource->getAllOptions() as $option) {
            $labels[(string) $option['value']] = (string) $option['label'];
        }
        $labels['pending_erp_validation'] = (string) __('Pendente validação ERP');

        foreach ($dataSource['data']['items'] as &$item) {
            $code = (string) ($item['erp_customer_sync_status'] ?? '');
            $item[$this->getData('name')] = $labels[$code] ?? ($code !== '' ? $code : '—');
        }

        return $dataSource;
    }
}
