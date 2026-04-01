<?php

/**
 * B2B Notification Log Collection
 */

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Model\ResourceModel\NotificationLog;

use GrupoAwamotos\B2B\Model\NotificationLog as NotificationLogModel;
use GrupoAwamotos\B2B\Model\ResourceModel\NotificationLog as NotificationLogResource;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    /**
     * @var string
     */
    protected $_idFieldName = 'notification_id';

    /**
     * @inheritDoc
     */
    protected function _construct(): void
    {
        $this->_init(NotificationLogModel::class, NotificationLogResource::class);
    }
}
