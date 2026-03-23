<?php

declare(strict_types=1);

namespace GrupoAwamotos\MarketingIntelligence\Controller\Adminhtml\Audiences;

use GrupoAwamotos\MarketingIntelligence\Model\Service\AudienceSyncer;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Psr\Log\LoggerInterface;

class Sync extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'GrupoAwamotos_MarketingIntelligence::audiences';

    public function __construct(
        Context $context,
        private readonly AudienceSyncer $audienceSyncer,
        private readonly LoggerInterface $logger
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $redirect = $this->resultRedirectFactory->create();
        $redirect->setPath('marketingintelligence/audiences/index');

        try {
            $count = $this->audienceSyncer->refreshAll();
            $this->messageManager->addSuccessMessage(
                __('Sincronização concluída. %1 audiências atualizadas.', $count)
            );
        } catch (\Exception $e) {
            $this->logger->error('MarketingIntelligence AudienceSync error: ' . $e->getMessage());
            $this->messageManager->addErrorMessage(
                __('Erro ao sincronizar audiências: %1', $e->getMessage())
            );
        }

        return $redirect;
    }
}
