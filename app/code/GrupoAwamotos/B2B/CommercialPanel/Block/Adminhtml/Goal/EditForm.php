<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\CommercialPanel\Block\Adminhtml\Goal;

use GrupoAwamotos\B2B\CommercialPanel\Api\PortfolioScopeInterface;
use GrupoAwamotos\B2B\Model\ResourceModel\Attendant\CollectionFactory as AttendantCollectionFactory;
use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;

class EditForm extends Template
{
    public function __construct(
        Context $context,
        private readonly AttendantCollectionFactory $attendantCollectionFactory,
        private readonly PortfolioScopeInterface $portfolioScope,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * @return array<int, string>
     */
    public function getAttendantOptions(): array
    {
        $ids = $this->portfolioScope->getVisibleAttendantIds();
        $collection = $this->attendantCollectionFactory->create();
        if ($ids !== []) {
            $collection->addFieldToFilter('attendant_id', ['in' => $ids]);
        }

        $options = [];
        foreach ($collection as $attendant) {
            $options[(int) $attendant->getId()] = (string) $attendant->getData('name');
        }

        return $options;
    }

    public function getSaveUrl(): string
    {
        return $this->getUrl('awa_commercial/commercialgoal/save');
    }

    public function getBackUrl(): string
    {
        return $this->getUrl('awa_commercial/commercialgoal/index');
    }

    public function getDefaultPeriod(): string
    {
        return date('Y-m');
    }
}
