<?php

declare(strict_types=1);

namespace GrupoAwamotos\ERPIntegration\Controller\Adminhtml\Goals;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use GrupoAwamotos\ERPIntegration\Model\Goals\Manager as GoalsManager;

/**
 * Save Goals via AJAX
 */
class Save extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'GrupoAwamotos_ERPIntegration::goals';

    private JsonFactory $jsonFactory;
    private GoalsManager $goalsManager;

    public function __construct(
        Context $context,
        JsonFactory $jsonFactory,
        GoalsManager $goalsManager
    ) {
        parent::__construct($context);
        $this->jsonFactory = $jsonFactory;
        $this->goalsManager = $goalsManager;
    }

    public function execute()
    {
        $result = $this->jsonFactory->create();

        try {
            $data = $this->getRequest()->getParams();

            $yearMonth = $data['year_month'] ?? null;
            $target = (float)($data['target'] ?? 0);
            $notes = $data['notes'] ?? '';

            if (!$yearMonth || $target <= 0) {
                return $result->setData([
                    'success' => false,
                    'message' => 'Período e meta são obrigatórios'
                ]);
            }

            $this->goalsManager->saveGoal($yearMonth, $target, $notes);

            return $result->setData([
                'success' => true,
                'message' => 'Meta salva com sucesso!'
            ]);
        } catch (\Exception $e) {
            return $result->setData([
                'success' => false,
                'message' => 'Erro ao salvar: ' . $e->getMessage()
            ]);
        }
    }
}
