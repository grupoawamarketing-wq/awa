<?php

/**
 * Actions Column for Order Approval Admin Grid
 */

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Ui\Component\Listing\Column;

use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;

class ApprovalActions extends Column
{
    /**
     * @var UrlInterface
     */
    private UrlInterface $urlBuilder;

    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        UrlInterface $urlBuilder,
        array $components = [],
        array $data = []
    ) {
        $this->urlBuilder = $urlBuilder;
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    /**
     * Prepare data source with action links
     *
     * @param array $dataSource
     * @return array
     */
    public function prepareDataSource(array $dataSource): array
    {
        if (!isset($dataSource['data']['items'])) {
            return $dataSource;
        }

        $name = $this->getData('name');

        foreach ($dataSource['data']['items'] as &$item) {
            $approvalId = $item['approval_id'] ?? null;

            if ($approvalId === null) {
                continue;
            }

            $item[$name] = $this->buildActions($item, $approvalId);
        }

        return $dataSource;
    }

    /**
     * Build action links for a single approval row
     *
     * @param array $item
     * @param int $approvalId
     * @return array
     */
    private function buildActions(array $item, int $approvalId): array
    {
        $actions = [];

        $actions['view_order'] = [
            'href' => $this->urlBuilder->getUrl('sales/order/view', [
                'order_id' => $item['order_id'] ?? 0
            ]),
            'label' => __('Ver Pedido'),
        ];

        if (($item['status'] ?? '') !== 'pending') {
            return $actions;
        }

        $actions['approve'] = [
            'href' => $this->urlBuilder->getUrl('grupoawamotos_b2b/approval/processAction', [
                'approval_id' => $approvalId,
                'approval_action' => 'approve',
            ]),
            'label' => __('Aprovar'),
            'confirm' => [
                'title' => __('Aprovar Pedido'),
                'message' => __('Tem certeza que deseja aprovar este pedido?'),
            ],
        ];

        $actions['reject'] = [
            'href' => $this->urlBuilder->getUrl('grupoawamotos_b2b/approval/processAction', [
                'approval_id' => $approvalId,
                'approval_action' => 'reject',
            ]),
            'label' => __('Rejeitar'),
            'confirm' => [
                'title' => __('Rejeitar Pedido'),
                'message' => __('Tem certeza que deseja rejeitar este pedido?'),
            ],
        ];

        return $actions;
    }
}
