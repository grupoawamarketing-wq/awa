<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Controller\Adminhtml\Followup;

use GrupoAwamotos\B2B\Helper\CurrentAttendant;
use GrupoAwamotos\B2B\Model\Followup;
use GrupoAwamotos\B2B\Model\FollowupFactory;
use GrupoAwamotos\B2B\Model\ResourceModel\Followup as FollowupResource;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;

class Save extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'GrupoAwamotos_B2B::followup';

    public function __construct(
        Context $context,
        private readonly JsonFactory $jsonFactory,
        private readonly FollowupFactory $followupFactory,
        private readonly FollowupResource $followupResource,
        private readonly CurrentAttendant $currentAttendant
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $result = $this->jsonFactory->create();

        try {
            $data = $this->getRequest()->getPostValue();
            $attendantId = $this->currentAttendant->getId();
            $adminUserId = (int) $this->_auth->getUser()->getId();

            if (!$attendantId && !$this->_authorization->isAllowed('GrupoAwamotos_B2B::b2b')) {
                return $result->setData(['success' => false, 'message' => __('Acesso negado.')->render()]);
            }

            $followupId = isset($data['followup_id']) ? (int) $data['followup_id'] : null;

            /** @var Followup $model */
            $model = $this->followupFactory->create();

            if ($followupId) {
                $this->followupResource->load($model, $followupId);
                if (!$model->getId()) {
                    return $result->setData(['success' => false, 'message' => __('Follow-up não encontrado.')->render()]);
                }
            }

            $model->setData([
                'customer_id'     => (int) ($data['customer_id'] ?? 0),
                'attendant_id'    => $attendantId ?? (int) ($data['attendant_id'] ?? 0),
                'contact_type'    => $data['contact_type'] ?? 'whatsapp',
                'observation'     => trim($data['observation'] ?? ''),
                'result'          => $data['result'] ?? null,
                'status'          => $data['status'] ?? 'open',
                'contact_at'      => $data['contact_at'] ?? null,
                'next_contact_at' => $data['next_contact_at'] ?? null,
                'admin_user_id'   => $adminUserId,
            ]);

            if ($followupId) {
                $model->setId($followupId);
            }

            $this->followupResource->save($model);

            return $result->setData([
                'success'    => true,
                'followup_id' => $model->getId(),
                'message'    => __('Follow-up salvo com sucesso.')->render(),
            ]);
        } catch (\Exception $e) {
            return $result->setData(['success' => false, 'message' => $e->getMessage()]);
        }
    }
}
