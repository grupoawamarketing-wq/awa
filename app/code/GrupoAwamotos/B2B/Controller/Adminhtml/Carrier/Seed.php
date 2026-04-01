<?php

/**
 * Admin Seed Carriers Controller
 */

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Controller\Adminhtml\Carrier;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use GrupoAwamotos\B2B\Model\CarrierService;
use Magento\Framework\App\Action\HttpPostActionInterface;

class Seed extends Action implements HttpPostActionInterface
{
    /**
     * Authorization level
     */
    public const ADMIN_RESOURCE = 'GrupoAwamotos_B2B::carrier';

    /**
     * @var CarrierService
     */
    private $carrierService;

    /**
     * @param Context $context
     * @param CarrierService $carrierService
     */
    public function __construct(
        Context $context,
        CarrierService $carrierService
    ) {
        parent::__construct($context);
        $this->carrierService = $carrierService;
    }

    /**
     * @inheritDoc
     */
    public function execute()
    {
        try {
            $carriers = $this->carrierService->seedDefaultCarriers();

            if (count($carriers) > 0) {
                $this->messageManager->addSuccessMessage(
                    __('%1 transportadora(s) cadastrada(s) com sucesso.', count($carriers))
                );
            } else {
                $this->messageManager->addNoticeMessage(
                    __('Todas as transportadoras já estavam cadastradas.')
                );
            }
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        }

        return $this->resultRedirectFactory->create()->setPath('b2b/carrier/index');
    }
}
