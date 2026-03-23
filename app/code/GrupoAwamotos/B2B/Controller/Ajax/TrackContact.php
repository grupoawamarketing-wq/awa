<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Controller\Ajax;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use Magento\Framework\Event\ManagerInterface as EventManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Receives a B2B contact-CTA click from the browser and dispatches the CAPI event.
 *
 * URL: POST b2b/ajax/trackContact
 * Params: contact_action, contact_channel, funnel_stage, touchpoint, event_id, form_key
 */
class TrackContact implements HttpPostActionInterface, CsrfAwareActionInterface
{
    /** Maximum length for sanitised string params. */
    private const MAX_PARAM_LENGTH = 128;

    public function __construct(
        private readonly RequestInterface $request,
        private readonly JsonFactory $resultJsonFactory,
        private readonly FormKeyValidator $formKeyValidator,
        private readonly EventManagerInterface $eventManager,
        private readonly CustomerSession $customerSession,
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

        $contactAction = $this->sanitize($this->request->getParam('contact_action', ''));
        if ($contactAction === '') {
            $resultJson->setHttpResponseCode(400);
            return $resultJson->setData(['success' => false, 'error' => 'Missing contact_action']);
        }

        $contactChannel = $this->sanitize($this->request->getParam('contact_channel', 'unknown'));
        $funnelStage = $this->sanitize($this->request->getParam('funnel_stage', 'consideration'));
        $touchpoint = $this->sanitize($this->request->getParam('touchpoint', 'b2b_contact'));
        $eventId = $this->sanitize($this->request->getParam('event_id', ''));

        $customer = $this->customerSession->isLoggedIn()
            ? $this->customerSession->getCustomer()
            : null;

        try {
            $this->eventManager->dispatch('grupoawamotos_b2b_contact_initiated', [
                'contact_action' => $contactAction,
                'contact_channel' => $contactChannel,
                'funnel_stage' => $funnelStage,
                'touchpoint' => $touchpoint,
                'event_id' => $eventId,
                'customer' => $customer
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('[B2B Contact AJAX] Event dispatch failed', [
                'error' => $e->getMessage(),
                'contact_action' => $contactAction
            ]);
        }

        return $resultJson->setData(['success' => true]);
    }

    /**
     * Removes characters outside of word chars, hyphens, and dots; truncates to safe length.
     */
    private function sanitize(string|int|float|null $value): string
    {
        $clean = preg_replace('/[^\w\-.]/', '', (string) $value);
        return mb_substr((string) $clean, 0, self::MAX_PARAM_LENGTH);
    }
}
