<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Controller\Adminhtml\Cnpj;

use GrupoAwamotos\B2B\Helper\CnpjValidator;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;

class ClearCache extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'GrupoAwamotos_B2B::customer_approval';

    private JsonFactory $jsonFactory;
    private CnpjValidator $cnpjValidator;

    public function __construct(
        Context $context,
        JsonFactory $jsonFactory,
        CnpjValidator $cnpjValidator
    ) {
        parent::__construct($context);
        $this->jsonFactory = $jsonFactory;
        $this->cnpjValidator = $cnpjValidator;
    }

    public function execute(): Json
    {
        $result = $this->jsonFactory->create();

        $clearAll = filter_var(
            $this->getRequest()->getParam('all', false),
            FILTER_VALIDATE_BOOLEAN
        );

        if ($clearAll) {
            $cleared = $this->cnpjValidator->clearCache();

            return $result->setData([
                'success' => true,
                'message' => $cleared
                    ? 'Cache global de CNPJ limpo com sucesso.'
                    : 'Nenhuma entrada de cache global foi removida.'
            ]);
        }

        $cnpj = $this->cnpjValidator->clean((string) $this->getRequest()->getParam('cnpj', ''));
        if (strlen($cnpj) !== 14) {
            return $result->setData([
                'success' => false,
                'message' => 'Informe um CNPJ com 14 dígitos para limpar o cache.'
            ]);
        }

        $cleared = $this->cnpjValidator->clearCache($cnpj);

        return $result->setData([
            'success' => true,
            'message' => $cleared
                ? 'Cache do CNPJ removido com sucesso.'
                : 'Nenhuma entrada de cache encontrada para este CNPJ.'
        ]);
    }

    protected function _isAllowed(): bool
    {
        return $this->_authorization->isAllowed('GrupoAwamotos_B2B::customer_approval')
            || $this->_authorization->isAllowed('Magento_Sales::create')
            || $this->_authorization->isAllowed('Magento_Customer::manage');
    }
}
