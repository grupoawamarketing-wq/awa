<?php

declare(strict_types=1);

namespace GrupoAwamotos\SmartSuggestions\Controller\Adminhtml\Suggestions;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use GrupoAwamotos\SmartSuggestions\Api\SuggestionEngineInterface;
use GrupoAwamotos\SmartSuggestions\Api\WhatsappSenderInterface;
use GrupoAwamotos\SmartSuggestions\Model\SuggestionHistoryFactory;
use GrupoAwamotos\SmartSuggestions\Model\ResourceModel\SuggestionHistory as SuggestionHistoryResource;
use GrupoAwamotos\SmartSuggestions\Model\SuggestionHistory;

/**
 * Send Suggestion via WhatsApp Controller
 */
class Send extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'GrupoAwamotos_SmartSuggestions::suggestions';

    private JsonFactory $jsonFactory;
    private SuggestionEngineInterface $suggestionEngine;
    private WhatsappSenderInterface $whatsappSender;
    private SuggestionHistoryFactory $historyFactory;
    private SuggestionHistoryResource $historyResource;

    public function __construct(
        Context $context,
        JsonFactory $jsonFactory,
        SuggestionEngineInterface $suggestionEngine,
        WhatsappSenderInterface $whatsappSender,
        SuggestionHistoryFactory $historyFactory,
        SuggestionHistoryResource $historyResource
    ) {
        parent::__construct($context);
        $this->jsonFactory = $jsonFactory;
        $this->suggestionEngine = $suggestionEngine;
        $this->whatsappSender = $whatsappSender;
        $this->historyFactory = $historyFactory;
        $this->historyResource = $historyResource;
    }

    /**
     * Execute send action
     */
    public function execute()
    {
        $result = $this->jsonFactory->create();

        $customerId = (int) $this->getRequest()->getParam('customer_id');
        $phone = $this->getRequest()->getParam('phone');

        if (!$customerId) {
            return $result->setData([
                'success' => false,
                'message' => 'Customer ID is required'
            ]);
        }

        try {
            // Generate suggestion
            $suggestion = $this->suggestionEngine->generateCartSuggestion($customerId);

            if (isset($suggestion['error'])) {
                return $result->setData([
                    'success' => false,
                    'message' => $suggestion['error']
                ]);
            }

            // Use provided phone or get from suggestion
            $phoneNumber = $phone ?: ($suggestion['customer']['phone'] ?? null);

            if (!$phoneNumber) {
                return $result->setData([
                    'success' => false,
                    'message' => 'Phone number not available for this customer'
                ]);
            }

            // Create history record
            $history = $this->historyFactory->create();
            $history->setData([
                'customer_id' => $customerId,
                'customer_name' => $suggestion['customer']['trade_name'] ?: $suggestion['customer']['customer_name'],
                'customer_phone' => $phoneNumber,
                'suggestion_data' => json_encode($suggestion),
                'total_value' => $suggestion['cart_summary']['total_value'] ?? 0,
                'products_count' => $suggestion['cart_summary']['total_products'] ?? 0,
                'status' => SuggestionHistory::STATUS_GENERATED,
                'channel' => SuggestionHistory::CHANNEL_WHATSAPP,
                'admin_user_id' => $this->_auth->getUser()->getId(),
                'created_at' => date('Y-m-d H:i:s')
            ]);
            $this->historyResource->save($history);

            // Send via WhatsApp
            $sendResult = $this->whatsappSender->sendSuggestion($phoneNumber, $suggestion);

            if ($sendResult['success']) {
                $history->setData('status', SuggestionHistory::STATUS_SENT);
                $history->setData('sent_at', date('Y-m-d H:i:s'));
                $history->setData('whatsapp_message_id', $sendResult['message_id'] ?? null);
                $this->historyResource->save($history);

                return $result->setData([
                    'success' => true,
                    'message' => 'Sugestão enviada com sucesso via WhatsApp!',
                    'history_id' => $history->getId()
                ]);
            } else {
                $history->setData('status', SuggestionHistory::STATUS_FAILED);
                $history->setData('error_message', $sendResult['message']);
                $this->historyResource->save($history);

                return $result->setData([
                    'success' => false,
                    'message' => 'Falha ao enviar: ' . $sendResult['message']
                ]);
            }
        } catch (\Exception $e) {
            return $result->setData([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ]);
        }
    }
}
