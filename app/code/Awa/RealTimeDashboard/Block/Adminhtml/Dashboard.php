<?php

namespace Awa\RealTimeDashboard\Block\Adminhtml;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Awa\RealTimeDashboard\Model\DashboardDataProvider;

class Dashboard extends Template
{
    protected $dataProvider;

    public function __construct(
        Context $context,
        DashboardDataProvider $dataProvider,
        array $data = []
    ) {
        $this->dataProvider = $dataProvider;
        parent::__construct($context, $data);
    }

    public function getDashboardData()
    {
        return $this->dataProvider->getData();
    }

    public function getAjaxUrl()
    {
        return $this->getUrl('awa_dashboard/dashboard/data');
    }

    public function formatPrice($value)
    {
        return 'R$ ' . number_format((float)$value, 2, ',', '.');
    }

    public function timeAgo($datetime)
    {
        $now = new \DateTime();
        $ago = new \DateTime($datetime);
        $diff = $now->diff($ago);

        if ($diff->d > 0) {
            return $diff->d . 'd ' . $diff->h . 'h atrás';
        }
        if ($diff->h > 0) {
            return $diff->h . 'h ' . $diff->i . 'min atrás';
        }
        if ($diff->i > 0) {
            return $diff->i . 'min atrás';
        }
        return 'Agora mesmo';
    }
}
