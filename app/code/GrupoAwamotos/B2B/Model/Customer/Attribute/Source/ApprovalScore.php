<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Model\Customer\Attribute\Source;

use GrupoAwamotos\B2B\Api\Data\ApprovalScoreResultInterface;
use Magento\Eav\Model\Entity\Attribute\Source\AbstractSource;

class ApprovalScore extends AbstractSource
{
    /**
     * @inheritdoc
     */
    public function getAllOptions(): array
    {
        if ($this->_options === null) {
            $this->_options = [
                ['value' => '', 'label' => __('—')],
                ['value' => ApprovalScoreResultInterface::SCORE_GREEN, 'label' => __('Verde — Aprovação automática')],
                ['value' => ApprovalScoreResultInterface::SCORE_YELLOW, 'label' => __('Amarelo — Análise manual')],
                ['value' => ApprovalScoreResultInterface::SCORE_RED, 'label' => __('Vermelho — Pendente / Revisar')],
            ];
        }

        return $this->_options;
    }

    /**
     * @param string $value
     * @return string|bool
     */
    public function getOptionText($value)
    {
        foreach ($this->getAllOptions() as $option) {
            if ($option['value'] === $value) {
                return $option['label'];
            }
        }

        return false;
    }

    public static function getLabel(string $score): string
    {
        return match ($score) {
            ApprovalScoreResultInterface::SCORE_GREEN => (string) __('Verde'),
            ApprovalScoreResultInterface::SCORE_YELLOW => (string) __('Amarelo'),
            ApprovalScoreResultInterface::SCORE_RED => (string) __('Vermelho'),
            default => (string) __('—'),
        };
    }
}
