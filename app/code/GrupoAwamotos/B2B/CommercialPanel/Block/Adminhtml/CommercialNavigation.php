<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\CommercialPanel\Block\Adminhtml;

use GrupoAwamotos\B2B\CommercialPanel\Model\SupervisorDashboardDataProvider;
use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\Phrase;

class CommercialNavigation extends Template
{
    public function __construct(
        Context $context,
        private readonly SupervisorDashboardDataProvider $supervisorDashboardDataProvider,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function isSupervisorView(): bool
    {
        return $this->supervisorDashboardDataProvider->isSupervisorView();
    }

    /**
     * @return array<int, array{label: Phrase, url: string, action: string}>
     */
    public function getNavigationTabs(): array
    {
        if ($this->isSupervisorView()) {
            return [
                ['label' => __('Painel da Equipe'), 'url' => $this->getDashboardUrl(), 'action' => 'awa_commercial_commercialdashboard_index'],
                ['label' => __('Carteira da Equipe'), 'url' => $this->getPortfolioUrl(), 'action' => 'awa_commercial_commercialportfolio_index'],
                ['label' => __('Clientes da Equipe'), 'url' => $this->getPendingUrl(), 'action' => 'awa_commercial_commercialpending_index'],
                ['label' => __('Tarefas da Equipe'), 'url' => $this->getTasksUrl(), 'action' => 'awa_commercial_commercialtask_index'],
                ['label' => __('Sugestões de Recompra'), 'url' => $this->getRepurchaseUrl(), 'action' => 'awa_commercial_commercialrepurchase_index'],
                ['label' => __('Clientes Parados'), 'url' => $this->getInactiveUrl(), 'action' => 'awa_commercial_commercialinactive_index'],
                ['label' => __('Metas Comerciais'), 'url' => $this->getGoalsUrl(), 'action' => 'awa_commercial_commercialgoal_index'],
                ['label' => __('Relatórios'), 'url' => $this->getReportsUrl(), 'action' => 'awa_commercial_commercialreport_index'],
            ];
        }

        return [
            ['label' => __('Meu Painel'), 'url' => $this->getDashboardUrl(), 'action' => 'awa_commercial_commercialdashboard_index'],
            ['label' => __('Minha Carteira'), 'url' => $this->getPortfolioUrl(), 'action' => 'awa_commercial_commercialportfolio_index'],
            ['label' => __('Minhas Tarefas'), 'url' => $this->getTasksUrl(), 'action' => 'awa_commercial_commercialtask_index'],
            ['label' => __('Sugestões de Recompra'), 'url' => $this->getRepurchaseUrl(), 'action' => 'awa_commercial_commercialrepurchase_index'],
            ['label' => __('Clientes Parados'), 'url' => $this->getInactiveUrl(), 'action' => 'awa_commercial_commercialinactive_index'],
            ['label' => __('Metas Comerciais'), 'url' => $this->getGoalsUrl(), 'action' => 'awa_commercial_commercialgoal_index'],
            ['label' => __('Relatórios'), 'url' => $this->getReportsUrl(), 'action' => 'awa_commercial_commercialreport_index'],
        ];
    }

    public function getCurrentAction(): string
    {
        return $this->getRequest()->getFullActionName();
    }

    public function getDashboardUrl(): string
    {
        return $this->getUrl('awa_commercial/commercialdashboard/index');
    }

    public function getPortfolioUrl(): string
    {
        return $this->getUrl('awa_commercial/commercialportfolio/index');
    }

    public function getPendingUrl(): string
    {
        return $this->getUrl('awa_commercial/commercialpending/index');
    }

    public function getTasksUrl(): string
    {
        return $this->getUrl('awa_commercial/commercialtask/index');
    }

    public function getRepurchaseUrl(): string
    {
        return $this->getUrl('awa_commercial/commercialrepurchase/index');
    }

    public function getInactiveUrl(): string
    {
        return $this->getUrl('awa_commercial/commercialinactive/index');
    }

    public function getGoalsUrl(): string
    {
        return $this->getUrl('awa_commercial/commercialgoal/index');
    }

    public function getReportsUrl(): string
    {
        return $this->getUrl('awa_commercial/commercialreport/index');
    }
}
