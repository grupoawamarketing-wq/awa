<?php

/**
 * B2B Carrier Model
 */

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Model;

use Magento\Framework\Model\AbstractModel;

class Carrier extends AbstractModel
{
    /**
     * @inheritDoc
     */
    protected function _construct()
    {
        $this->_init(\GrupoAwamotos\B2B\Model\ResourceModel\Carrier::class);
    }

    /**
     * Check if carrier is active
     *
     * @return bool
     */
    public function isActive(): bool
    {
        return (bool)$this->getData('is_active');
    }
}
