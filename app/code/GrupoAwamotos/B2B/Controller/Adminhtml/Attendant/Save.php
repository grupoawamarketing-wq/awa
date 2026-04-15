<?php

/**
 * Controller Admin para salvar atendente
 */

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Controller\Adminhtml\Attendant;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use GrupoAwamotos\B2B\Model\Attendant\AttendantManager;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Exception\LocalizedException;
use Psr\Log\LoggerInterface;

class Save extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'GrupoAwamotos_B2B::attendants';

    private JsonFactory $jsonFactory;
    private AttendantManager $attendantManager;
    private LoggerInterface $logger;

    public function __construct(
        Context $context,
        JsonFactory $jsonFactory,
        AttendantManager $attendantManager,
        LoggerInterface $logger
    ) {
        parent::__construct($context);
        $this->jsonFactory = $jsonFactory;
        $this->attendantManager = $attendantManager;
        $this->logger = $logger;
    }

    public function execute()
    {
        $result = $this->jsonFactory->create();

        try {
            $data = $this->getRequest()->getPostValue();

            if (empty($data['name']) || empty($data['email'])) {
                return $result->setData([
                    'success' => false,
                    'message' => __('Nome e e-mail são obrigatórios.')
                ]);
            }

            if (!filter_var((string) $data['email'], FILTER_VALIDATE_EMAIL)) {
                return $result->setData([
                    'success' => false,
                    'message' => __('Informe um e-mail válido para o atendente.')
                ]);
            }

            $adminUser = $this->_auth->getUser();
            if ($adminUser && $adminUser->getId()) {
                $data['admin_user_id'] = (int) $adminUser->getId();
            }

            $attendantId = $this->attendantManager->saveAttendant($data);

            return $result->setData([
                'success' => true,
                'message' => __('Atendente salvo com sucesso.'),
                'attendant_id' => $attendantId
            ]);
        } catch (LocalizedException $e) {
            return $result->setData([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        } catch (\Exception $e) {
            $this->logger->error('[B2B Admin] Attendant save failed', ['exception' => $e]);
            return $result->setData([
                'success' => false,
                'message' => __('Erro ao salvar atendente.')
            ]);
        }
    }
}
