<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Controller\Ajax;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use Magento\Framework\Event\ManagerInterface as EventManagerInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Receives a B2B Lead signal from the browser when the visitor starts the register form,
 * then dispatches the Magento event that triggers the Meta CAPI observer.
 *
 * URL: POST b2b/ajax/trackLead
 * Params: event_id, funnel_stage, register_channel, form_key
 */
class TrackLead implements HttpPostActionInterface, CsrfAwareActionInterface
{
    /** Allowed funnel-stage values for stricter input validation. */
    private const ALLOWED_FUNNEL_STAGES = ['start', 'consideration'];

    /** Maximum length for sanitised string params. */
    private const MAX_PARAM_LENGTH = 128;

    public function __construct(
        private readonly RequestInterface $request,
        private readonly JsonFactory $resultJsonFactory,
        private readonly FormKeyValidator $formKeyValidator,
        private readonly EventManagerInterface $eventManager,
        private readonly StoreManagerInterface $storeManager,
        private readonly LoggerInterface $logger
    ) {
    }

    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        $resultJson = $this->resultJsonFactory->create();
        $resultJson->setHttpResponseCode(403);
        $resultJson->setData(['success' => false, 'error' => 'Invalid form key']);
        return new InvalidRequestException($resultJson);
    }

    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return $this->formKeyValidator->validate($request);
    }

    public function execute(): Json
    {
        $resultJson = $this->resultJsonFactory->create();

        $eventId = $this->sanitize($this->request->getParam('event_id', ''));
        $funnelStage = $this->request->getParam('funnel_stage', 'start');
        $registerChannel = $this->sanitize($this->request->getParam('register_channel', 'b2b_register_form'));

        // Restrict funnel_stage to known values.
        if (!in_array($funnelStage, self::ALLOWED_FUNNEL_STAGES, true)) {
            $funnelStage = 'start';
        }

        try {
            $storeId = (int) $this->storeManager->getStore()->getId() ?: null;

            $this->eventManager->dispatch('grupoawamotos_b2b_lead_initiated', [
                'event_id' => $eventId,
                'funnel_stage' => $funnelStage,
                'register_channel' => $registerChannel,
                'store_id' => $storeId
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('[B2B Lead AJAX] Event dispatch failed', [
                'error' => $e->getMessage()
            ]);
        }

        return $resultJson->setData(['success' => true]);
    }

    private function sanitize(string|int|float|null $value): string
    {
        $clean = preg_replace('/[^\w\-.]/', '', (string) $value);
        return mb_substr((string) $clean, 0, self::MAX_PARAM_LENGTH);
    }
}
