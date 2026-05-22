<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Controller\Adminhtml\CommercialAbandonedCart;

use GrupoAwamotos\B2B\CommercialPanel\Model\AbandonedCartCommercialManagement;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\LocalizedException;

class Treat extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'GrupoAwamotos_B2B::commercial_abandoned_cart_treat';

    public function __construct(
        Context $context,
        private readonly AbandonedCartCommercialManagement $abandonedCartManagement
    ) {
        parent::__construct($context);
    }

    public function execute(): ResultInterface
    {
        $redirect = $this->resultRedirectFactory->create();

        try {
            if (!$this->_formKeyValidator->validate($this->getRequest())) {
                throw new LocalizedException(__('Formulário inválido.'));
            }

            $user = $this->_auth->getUser();
            if (!$user || !$user->getId()) {
                throw new LocalizedException(__('Sessão expirada.'));
            }

            $entityId = (int) $this->getRequest()->getPostValue('entity_id');
            $this->abandonedCartManagement->markAsTreated($entityId, (int) $user->getId());
            $this->messageManager->addSuccessMessage(__('Carrinho marcado como tratado.'));
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        } catch (\Exception) {
            $this->messageManager->addErrorMessage(__('Não foi possível atualizar o carrinho.'));
        }

        return $redirect->setPath('awa_commercial/commercialabandonedcart/index');
    }
}
