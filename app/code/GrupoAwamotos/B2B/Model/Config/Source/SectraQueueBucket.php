<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Model\Config\Source;

use GrupoAwamotos\B2B\Model\Sectra\SectraOrderQueueResolver;
use Magento\Framework\Data\OptionSourceInterface;

class SectraQueueBucket implements OptionSourceInterface
{
    /**
     * @inheritdoc
     */
    public function toOptionArray(): array
    {
        $options = [];
        foreach (SectraOrderQueueResolver::bucketLabels() as $value => $label) {
            $options[] = ['value' => $value, 'label' => $label];
        }

        return $options;
    }
}
