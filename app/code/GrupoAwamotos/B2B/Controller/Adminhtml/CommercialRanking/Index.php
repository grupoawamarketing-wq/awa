<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Controller\Adminhtml\CommercialRanking;

use GrupoAwamotos\B2B\CommercialPanel\Model\Intelligence\CommercialRankingService;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\View\Result\PageFactory;

class Index extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'GrupoAwamotos_B2B::commercial_ranking';

    public function __construct(
        Context $context,
        private readonly PageFactory $resultPageFactory,
        private readonly CommercialRankingService $rankingService
    ) {
        parent::__construct($context);
    }

    public function execute(): ResultInterface
    {
        if (!$this->rankingService->isRankingAllowed()) {
            $this->messageManager->addErrorMessage(__('Ranking disponível apenas para supervisora.'));
            $resultRedirect = $this->resultRedirectFactory->create();

            return $resultRedirect->setPath('awa_commercial/commercialdashboard/index');
        }

        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('GrupoAwamotos_B2B::commercial_ranking');
        $resultPage->getConfig()->getTitle()->prepend(__('Ranking Comercial'));

        return $resultPage;
    }
}
