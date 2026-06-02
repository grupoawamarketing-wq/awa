<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Ui\Component\Listing\Column;

use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;

class SectraQueueActions extends Column
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

            $orderId = (int) $item['entity_id'];
            $customerId = (int) ($item['customer_id'] ?? 0);
            $name = $this->getData('name');

            $item[$name]['view_order'] = [
                'href' => $this->urlBuilder->getUrl('sales/order/view', ['order_id' => $orderId]),
                'label' => (string) __('Ver pedido'),
            ];

            if ($customerId > 0) {
                $item[$name]['view_customer'] = [
                    'href' => $this->urlBuilder->getUrl('customer/index/edit', ['id' => $customerId]),
                    'label' => (string) __('Ver cliente'),
                ];
            }

            if (($item['queue_bucket'] ?? '') === 'awaiting' && $customerId > 0) {
                $item[$name]['erp_pending'] = [
                    'href' => $this->urlBuilder->getUrl('grupoawamotos_b2b/customer/erpPending'),
                    'label' => (string) __('Clientes pendentes ERP'),
                ];
            }
        }

        return $dataSource;
    }
}
