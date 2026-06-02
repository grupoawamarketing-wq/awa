<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Ui\Component\Listing\Column;

use GrupoAwamotos\B2B\Model\Sectra\SectraImportStatus;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;

class SectraImportStatusLabel extends Column
{
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
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

        $labels = SectraImportStatus::labels();

        foreach ($dataSource['data']['items'] as &$item) {
            $code = (string) ($item['sectra_import_status'] ?? '');
            $item[$this->getData('name')] = $code !== ''
                ? ($labels[$code] ?? $code)
                : '—';
        }

        return $dataSource;
    }
}
