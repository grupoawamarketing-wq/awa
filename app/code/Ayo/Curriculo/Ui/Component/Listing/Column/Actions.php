<?php

declare(strict_types=1);

namespace Ayo\Curriculo\Ui\Component\Listing\Column;

use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;

class Actions extends Column
{
    private const URL_PATH_VIEW = 'curriculo/submission/view';
    private const URL_PATH_DELETE = 'curriculo/submission/delete';
    private const URL_PATH_DOWNLOAD = 'curriculo/submission/download';

    /**
     * @var UrlInterface
     */
    private $urlBuilder;

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

    public function prepareDataSource(array $dataSource): array
    {
        if (isset($dataSource['data']['items'])) {
            foreach ($dataSource['data']['items'] as &$item) {
                if (isset($item['entity_id'])) {
                    $item[$this->getData('name')] = [
                        'view' => [
                            'href' => $this->urlBuilder->getUrl(
                                self::URL_PATH_VIEW,
                                ['id' => $item['entity_id']]
                            ),
                            'label' => __('Ver')
                        ],
                        'download' => [
                            'href' => $this->urlBuilder->getUrl(
                                self::URL_PATH_DOWNLOAD,
                                ['id' => $item['entity_id']]
                            ),
                            'label' => __('Baixar CV'),
                            'target' => '_blank'
                        ],
                        'delete' => [
                            'href' => $this->urlBuilder->getUrl(
                                self::URL_PATH_DELETE,
                                ['id' => $item['entity_id']]
                            ),
                            'label' => __('Excluir'),
                            'confirm' => [
                                'title' => __('Excluir Candidatura'),
                                'message' => __('Tem certeza que deseja excluir esta candidatura?')
                            ]
                        ]
                    ];
                }
            }
        }

        return $dataSource;
    }
}
