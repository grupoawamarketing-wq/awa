<?php

declare(strict_types=1);

namespace GrupoAwamotos\Fitment\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

/**
 * MotorcycleModel Resource Model
 */
class MotorcycleModel extends AbstractDb
{
    /**
     * @var string
     */
    protected $_eventPrefix = 'grupoawamotos_fitment_model_resource';

    /**
     * @inheritdoc
     */
    protected function _construct(): void
    {
        $this->_init('grupoawamotos_fitment_model', 'model_id');
    }
}
