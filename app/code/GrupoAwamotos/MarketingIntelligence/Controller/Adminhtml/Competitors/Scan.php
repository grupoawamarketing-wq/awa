<?php

declare(strict_types=1);

namespace GrupoAwamotos\MarketingIntelligence\Controller\Adminhtml\Competitors;

use GrupoAwamotos\MarketingIntelligence\Model\Service\CompetitorMonitor;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Psr\Log\LoggerInterface;

class Scan extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'GrupoAwamotos_MarketingIntelligence::competitors';

    public function __construct(
        Context $context,
        private readonly CompetitorMonitor $competitorMonitor,
        private readonly LoggerInterface $logger
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $redirect = $this->resultRedirectFactory->create();
        $redirect->setPath('marketingintelligence/competitors/index');

        try {
            $count = $this->competitorMonitor->scanAll();
            $this->messageManager->addSuccessMessage(
                __('Varredura concluída. %1 anúncios encontrados.', $count)
            );
        } catch (\Exception $e) {
            $this->logger->error('MarketingIntelligence CompetitorScan error: ' . $e->getMessage());
            $this->messageManager->addErrorMessage(
                __('Erro ao varrer concorrentes: %1', $e->getMessage())
            );
        }

        return $redirect;
    }
}
