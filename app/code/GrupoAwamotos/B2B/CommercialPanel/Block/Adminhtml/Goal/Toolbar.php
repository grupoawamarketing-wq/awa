<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\CommercialPanel\Block\Adminhtml\Goal;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;

class Toolbar extends Template
{
    public function __construct(Context $context, array $data = [])
    {
        parent::__construct($context, $data);
    }

    public function canManageGoals(): bool
    {
        return $this->_authorization->isAllowed('GrupoAwamotos_B2B::commercial_goals_manage');
    }

    public function getEditUrl(): string
    {
        return $this->getUrl('awa_commercial/commercialgoal/edit');
    }
}
