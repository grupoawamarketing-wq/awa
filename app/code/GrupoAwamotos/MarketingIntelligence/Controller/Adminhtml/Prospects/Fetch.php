<?php

declare(strict_types=1);

namespace GrupoAwamotos\MarketingIntelligence\Controller\Adminhtml\Prospects;

use GrupoAwamotos\MarketingIntelligence\Model\Service\ProspectFetcher;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Psr\Log\LoggerInterface;

class Fetch extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'GrupoAwamotos_MarketingIntelligence::prospects';

    public function __construct(
        Context $context,
        private readonly ProspectFetcher $prospectFetcher,
        private readonly LoggerInterface $logger
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $redirect = $this->resultRedirectFactory->create();
        $redirect->setPath('marketingintelligence/prospects/index');

        try {
            $count = $this->prospectFetcher->execute();
            $this->messageManager->addSuccessMessage(
                __('Prospecção concluída. %1 prospects processados.', $count)
            );
        } catch (\Exception $e) {
            $this->logger->error('MarketingIntelligence ProspectFetch error: ' . $e->getMessage());
            $this->messageManager->addErrorMessage(
                __('Erro ao buscar prospects: %1', $e->getMessage())
            );
        }

        return $redirect;
    }
}
