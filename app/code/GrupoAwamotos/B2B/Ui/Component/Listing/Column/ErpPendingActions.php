<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Ui\Component\Listing\Column;

use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;

class ErpPendingActions extends Column
{
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        private readonly UrlInterface $urlBuilder,
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

        foreach ($dataSource['data']['items'] as &$item) {
            if (!isset($item['entity_id'])) {
                continue;
            }

            $customerId = (int) $item['entity_id'];
            $name = $this->getData('name');

            $item[$name]['view'] = [
                'href' => $this->urlBuilder->getUrl('customer/index/edit', ['id' => $customerId]),
                'label' => __('Ver cliente'),
            ];

            if (($item['b2b_approval_status'] ?? '') !== 'approved') {
                $item[$name]['approve'] = [
                    'href' => $this->urlBuilder->getUrl(
                        'grupoawamotos_b2b/customer/approve',
                        ['customer_id' => $customerId]
                    ),
                    'label' => __('Aprovar comercial'),
                    'confirm' => [
                        'title' => __('Aprovar Cliente'),
                        'message' => __('Aprovar comercialmente e enviar prospect ao pipeline ERP?'),
                    ],
                ];
            }

            $item[$name]['sync'] = [
                'href' => $this->urlBuilder->getUrl(
                    'grupoawamotos_b2b/customer/syncProspect',
                    ['customer_id' => $customerId]
                ),
                'label' => __('Reprocessar ERP'),
                'confirm' => [
                    'title' => __('Reprocessar prospect ERP'),
                    'message' => __('Executar sincronização passiva do prospect no bridge Magento?'),
                ],
            ];
        }

        return $dataSource;
    }
}
