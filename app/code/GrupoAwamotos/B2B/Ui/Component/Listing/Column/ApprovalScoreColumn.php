<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Ui\Component\Listing\Column;

use GrupoAwamotos\B2B\Api\Data\ApprovalScoreResultInterface;
use GrupoAwamotos\B2B\Model\Customer\Attribute\Source\ApprovalScore;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;

class ApprovalScoreColumn extends Column
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

        $columnName = $this->getData('name');

        foreach ($dataSource['data']['items'] as &$item) {
            $score = (string) ($item['b2b_approval_score'] ?? '');
            $label = ApprovalScore::getLabel($score);
            $cssClass = match ($score) {
                ApprovalScoreResultInterface::SCORE_GREEN => 'b2b-score-green',
                ApprovalScoreResultInterface::SCORE_YELLOW => 'b2b-score-yellow',
                ApprovalScoreResultInterface::SCORE_RED => 'b2b-score-red',
                default => 'b2b-score-none',
            };
            $style = match ($score) {
                ApprovalScoreResultInterface::SCORE_GREEN => 'background:#dcfce7;color:#166534;',
                ApprovalScoreResultInterface::SCORE_YELLOW => 'background:#fef9c3;color:#854d0e;',
                ApprovalScoreResultInterface::SCORE_RED => 'background:#fee2e2;color:#991b1b;',
                default => 'background:#f3f4f6;color:#374151;',
            };

            $item[$columnName] = sprintf(
                '<span class="b2b-approval-score %s" style="display:inline-block;padding:2px 10px;border-radius:12px;font-weight:600;font-size:12px;%s">%s</span>',
                $cssClass,
                $style,
                htmlspecialchars($label, ENT_QUOTES, 'UTF-8')
            );
        }

        return $dataSource;
    }
}
