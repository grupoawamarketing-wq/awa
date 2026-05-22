<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Ui\Component\Listing\Column;

use Magento\Customer\Api\GroupRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;

class SuggestedGroup extends Column
{
    private GroupRepositoryInterface $groupRepository;
    private SearchCriteriaBuilder $searchCriteriaBuilder;

    /** @var array<int, string> */
    private array $groupLabels = [];

    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        GroupRepositoryInterface $groupRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        array $components = [],
        array $data = []
    ) {
        $this->groupRepository = $groupRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    public function prepareDataSource(array $dataSource): array
    {
        if (!isset($dataSource['data']['items'])) {
            return $dataSource;
        }

        $columnName = $this->getData('name');

        foreach ($dataSource['data']['items'] as &$item) {
            $groupId = (int) ($item['b2b_suggested_group_id'] ?? 0);
            $item[$columnName] = $groupId > 0 ? $this->resolveGroupLabel($groupId) : '—';
        }

        return $dataSource;
    }

    private function resolveGroupLabel(int $groupId): string
    {
        if (isset($this->groupLabels[$groupId])) {
            return $this->groupLabels[$groupId];
        }

        try {
            $group = $this->groupRepository->getById($groupId);
            $label = $group->getCode();
        } catch (\Exception) {
            $label = (string) __('Grupo #%1', $groupId);
        }

        $this->groupLabels[$groupId] = $label;

        return $label;
    }
}
