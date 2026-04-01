<?php

declare(strict_types=1);

namespace GrupoAwamotos\Fitment\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

/**
 * Brand Resource Model
 */
class Brand extends AbstractDb
{
    /**
     * @var string
     */
    protected $_eventPrefix = 'grupoawamotos_fitment_brand_resource';

    /**
     * @inheritdoc
     */
    protected function _construct(): void
    {
        $this->_init('grupoawamotos_fitment_brand', 'brand_id');
    }
}
