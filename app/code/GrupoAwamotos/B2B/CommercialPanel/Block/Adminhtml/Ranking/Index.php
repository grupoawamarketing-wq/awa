<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\CommercialPanel\Block\Adminhtml\Ranking;

use GrupoAwamotos\B2B\CommercialPanel\Model\Intelligence\CommercialRankingService;
use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\Pricing\Helper\Data as PriceHelper;

class Index extends Template
{
    public function __construct(
        Context $context,
        private readonly CommercialRankingService $rankingService,
        private readonly PriceHelper $priceHelper,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getRanking(): array
    {
        $period = (string) $this->getRequest()->getParam('period_month', date('Y-m'));

        return $this->rankingService->getRanking($period);
    }

    public function getPeriodMonth(): string
    {
        $period = (string) $this->getRequest()->getParam('period_month', date('Y-m'));

        return preg_match('/^\d{4}-\d{2}$/', $period) ? $period : date('Y-m');
    }

    public function formatPrice(float $amount): string
    {
        return (string) $this->priceHelper->currency($amount, true, false);
    }
}
