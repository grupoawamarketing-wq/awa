<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\CommercialPanel\Block\Adminhtml\Inactive;

use GrupoAwamotos\B2B\CommercialPanel\Model\Intelligence\InactiveCustomerService;
use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;

class Filters extends Template
{
    public function __construct(
        Context $context,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * @return array<int, string>
     */
    public function getPresetFilters(): array
    {
        return [
            30 => (string) __('30 dias'),
            60 => (string) __('60 dias'),
            90 => (string) __('90 dias'),
            120 => (string) __('120 dias'),
        ];
    }

    public function getActiveDays(): int
    {
        $custom = (int) $this->getRequest()->getParam('inactive_days_custom', 0);
        if ($custom > 0) {
            return $custom;
        }

        $preset = (int) $this->getRequest()->getParam('inactive_days', 30);

        return in_array($preset, InactiveCustomerService::PRESET_DAYS, true) ? $preset : 30;
    }

    public function getFilterUrl(int $days): string
    {
        return $this->getUrl('*/*/*', ['inactive_days' => $days, 'inactive_days_custom' => null]);
    }
}
