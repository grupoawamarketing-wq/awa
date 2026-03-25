<?php
declare(strict_types=1);

namespace Ayo\Curriculo\Model\Mail;

use Ayo\Curriculo\Model\Submission;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\Translate\Inline\StateInterface;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

class StatusNotifier
{
    public const XML_PATH_SEND_STATUS_NOTIFICATION = 'ayo_curriculo/general/send_status_notification';
    public const XML_PATH_SENDER_EMAIL_IDENTITY = 'ayo_curriculo/general/sender_email_identity';

    private const STATUS_LABELS = [
        'pending'   => 'Pendente - Aguardando análise',
        'reviewing' => 'Em Análise - Seu currículo está sendo avaliado',
        'interview' => 'Entrevista - Você será contatado para agendar',
        'approved'  => 'Aprovado - Parabéns! Entre em contato conosco',
        'rejected'  => 'Não selecionado para esta vaga',
    ];

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var TransportBuilder
     */
    private $transportBuilder;

    /**
     * @var StateInterface
     */
    private $inlineTranslation;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var UrlInterface
     */
    private $url;

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        TransportBuilder $transportBuilder,
        StateInterface $inlineTranslation,
        StoreManagerInterface $storeManager,
        UrlInterface $url,
        LoggerInterface $logger
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->transportBuilder = $transportBuilder;
        $this->inlineTranslation = $inlineTranslation;
        $this->storeManager = $storeManager;
        $this->url = $url;
        $this->logger = $logger;
    }

    /**
     * Send status update notification email to the candidate.
     *
     * @param Submission $submission
     * @throws \Exception
     */
    public function send(Submission $submission): void
    {
        $storeId = (int)($submission->getData('store_id') ?: $this->storeManager->getStore()->getId());

        if (!$this->scopeConfig->isSetFlag(self::XML_PATH_SEND_STATUS_NOTIFICATION, ScopeInterface::SCOPE_STORE, $storeId)) {
            return;
        }

        $candidateEmail = trim((string)$submission->getData('email'));
        if ($candidateEmail === '') {
            return;
        }

        $sender = (string)$this->scopeConfig->getValue(
            self::XML_PATH_SENDER_EMAIL_IDENTITY,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
        if ($sender === '') {
            $sender = 'general';
        }

        $status = (string)($submission->getData('status') ?: 'pending');
        $statusLabel = self::STATUS_LABELS[$status] ?? $status;
        $statusUrl = $this->url->getUrl('curriculo/index/status');

        $vars = [
            'name'          => trim((string)$submission->getData('name')),
            'tracking_code' => trim((string)$submission->getData('tracking_code')),
            'status'        => $status,
            'status_label'  => $statusLabel,
            'status_url'    => $statusUrl,
        ];

        $this->inlineTranslation->suspend();
        try {
            $transport = $this->transportBuilder
                ->setTemplateIdentifier('ayo_curriculo_status_update_template')
                ->setTemplateOptions([
                    'area'  => \Magento\Framework\App\Area::AREA_FRONTEND,
                    'store' => $storeId,
                ])
                ->setTemplateVars(['data' => $vars])
                ->setFrom($sender)
                ->addTo($candidateEmail, $vars['name'])
                ->getTransport();

            $transport->sendMessage();
        } catch (\Exception $e) {
            $this->logger->error('Curriculo: failed to send status notification email', [
                'submission_id' => $submission->getId(),
                'exception'     => $e->getMessage(),
            ]);
        } finally {
            $this->inlineTranslation->resume();
        }
    }
}
