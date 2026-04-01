<?php

declare(strict_types=1);

namespace Ayo\Curriculo\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class Submission extends AbstractDb
{
    public const TABLE_NAME = 'ayo_curriculo_submissions';

    protected function _construct(): void
    {
        $this->_init(self::TABLE_NAME, 'entity_id');
    }
}
