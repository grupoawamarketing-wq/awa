<?php

declare(strict_types=1);

namespace GrupoAwamotos\Fitment\Controller\Adminhtml\Model;

use GrupoAwamotos\Fitment\Model\MotorcycleModelFactory;
use GrupoAwamotos\Fitment\Model\ResourceModel\MotorcycleModel as ModelResource;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;

class InlineEdit extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'GrupoAwamotos_Fitment::model';

    public function __construct(
        Context $context,
        private readonly MotorcycleModelFactory $modelFactory,
        private readonly ModelResource $modelResource,
        private readonly JsonFactory $jsonFactory
    ) {
        parent::__construct($context);
    }

    public function execute(): \Magento\Framework\Controller\Result\Json
    {
        $resultJson = $this->jsonFactory->create();
        $error = false;
        $messages = [];

        $items = $this->getRequest()->getParam('items', []);
        if (!$items || !$this->getRequest()->getParam('isAjax')) {
            return $resultJson->setData(['messages' => [__('Dados inválidos.')], 'error' => true]);
        }

        foreach ($items as $itemId => $itemData) {
            $model = $this->modelFactory->create();
            $this->modelResource->load($model, $itemId);
            try {
                $model->setData(array_merge($model->getData(), $itemData));
                $this->modelResource->save($model);
            } catch (\Exception $e) {
                $messages[] = $e->getMessage();
                $error = true;
            }
        }

        return $resultJson->setData(['messages' => $messages, 'error' => $error]);
    }
}
