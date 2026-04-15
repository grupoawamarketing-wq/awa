<?php

/**
 * Coluna de ações do grid de atendentes.
 */

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Ui\Component\Listing\Column;

use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;

class AttendantActions extends Column
{
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
     * @param array<string, mixed> $dataSource
     * @return array<string, mixed>
     */
    public function prepareDataSource(array $dataSource): array
    {
        if (!isset($dataSource['data']['items'])) {
            return $dataSource;
        }

        $name = (string) $this->getData('name');

        foreach ($dataSource['data']['items'] as &$item) {
            $attendantId = isset($item['attendant_id']) ? (int) $item['attendant_id'] : 0;
            if ($attendantId <= 0) {
                continue;
            }

            $item[$name] = [
                'edit' => [
                    'href' => $this->urlBuilder->getUrl('grupoawamotos_b2b/attendant/index', ['id' => $attendantId]),
                    'label' => __('Editar'),
                ],
            ];
        }

        return $dataSource;
    }
}
