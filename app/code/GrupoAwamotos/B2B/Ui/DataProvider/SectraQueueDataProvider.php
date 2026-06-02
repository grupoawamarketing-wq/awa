<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Ui\DataProvider;

use GrupoAwamotos\B2B\Model\Sectra\SectraOrderQueueQuery;
use Magento\Framework\Api\Filter;
use Magento\Framework\App\RequestInterface;
use Magento\Ui\DataProvider\AbstractDataProvider;

class SectraQueueDataProvider extends AbstractDataProvider
{
    private int $currentPage = 1;

    private int $pageSize = 20;

    public function __construct(
        $name,
        $primaryFieldName,
        $requestFieldName,
        private readonly SectraOrderQueueQuery $queueQuery,
        private readonly RequestInterface $request,
        array $meta = [],
        array $data = []
    ) {
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
    }

    /**
     * This provider is backed by SectraOrderQueueQuery instead of a Magento collection.
     */
    public function addFilter(Filter $filter)
    {
    }

    /**
     * This provider is backed by SectraOrderQueueQuery instead of a Magento collection.
     */
    public function addOrder($field, $direction)
    {
    }

    /**
     * UI paging calls setLimit before getData; keep values locally because there is no collection.
     */
    public function setLimit($offset, $size)
    {
        $this->currentPage = max(1, (int) $offset);
        $this->pageSize = max(1, (int) $size);
    }

    public function getData(): array
    {
        $filters = $this->request->getParam('filters', []);
        if (!is_array($filters)) {
            $filters = [];
        }

        $items = $this->queueQuery->fetchPendingOrders($filters);

        $paging = $this->request->getParam('paging', []);
        $pageSize = max(1, (int) ($paging['pageSize'] ?? $this->pageSize));
        $currentPage = max(1, (int) ($paging['current'] ?? $this->currentPage));
        $totalRecords = count($items);
        $offset = ($currentPage - 1) * $pageSize;

        return [
            'totalRecords' => $totalRecords,
            'items' => array_slice($items, $offset, $pageSize),
        ];
    }
}
