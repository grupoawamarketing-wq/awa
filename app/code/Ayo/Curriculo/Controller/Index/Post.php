<?php
declare(strict_types=1);

namespace Ayo\Curriculo\Controller\Index;

use Ayo\Curriculo\Model\Mail\SymfonyEmailMimeMessage;
use Ayo\Curriculo\Model\SubmissionFactory;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\File\Mime;
use Magento\Framework\File\UploaderFactory;
use Magento\Framework\Filesystem;
use Magento\Framework\Mail\EmailMessageInterfaceFactory;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\Mail\Template\FactoryInterface as TemplateFactoryInterface;
use Magento\Framework\Mail\Template\SenderResolverInterface;
use Magento\Framework\Mail\TransportInterfaceFactory;
use Magento\Framework\Translate\Inline\StateInterface;
use Magento\Framework\Validator\EmailAddress;
use Magento\Framework\Validator\Url as UrlValidator;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mime\Email;

class Post extends Action implements HttpPostActionInterface
{
    public const XML_PATH_ENABLED = 'ayo_curriculo/general/enabled';
    public const XML_PATH_RECIPIENT_EMAIL = 'ayo_curriculo/general/recipient_email';
    public const XML_PATH_SENDER_EMAIL_IDENTITY = 'ayo_curriculo/general/sender_email_identity';
    public const XML_PATH_COPY_TO = 'ayo_curriculo/general/copy_to';
    public const XML_PATH_MAX_FILE_SIZE_MB = 'ayo_curriculo/general/max_file_size_mb';
    public const XML_PATH_SEND_CONFIRMATION = 'ayo_curriculo/general/send_confirmation';

    private const DEFAULT_MAX_FILE_SIZE_MB = 5;
    private const STORAGE_DIR = 'curriculos';
    private const ALLOWED_EXTENSIONS = ['pdf', 'doc', 'docx'];
    private const ALLOWED_MIME_TYPES = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
    ];

    /**
     * @var FormKeyValidator
     */
    private $formKeyValidator;

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var TransportBuilder
     */
    private $transportBuilder;

    /**
     * @var DataPersistorInterface
     */
    private $dataPersistor;

    /**
     * @var StateInterface
     */
    private $inlineTranslation;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var UploaderFactory
     */
    private $uploaderFactory;

    /**
     * @var Filesystem
     */
    private $filesystem;

    /**
     * @var EmailAddress
     */
    private $emailValidator;

    /**
     * @var UrlValidator
     */
    private $urlValidator;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var Mime
     */
    private $mime;

    /**
     * @var SubmissionFactory
     */
    private $submissionFactory;

    /**
     * @var TemplateFactoryInterface
     */
    private $templateFactory;

    /**
     * @var SenderResolverInterface
     */
    private $senderResolver;

    /**
     * @var TransportInterfaceFactory
     */
    private $transportFactory;

    /**
     * @var EmailMessageInterfaceFactory
     */
    private $emailMessageFactory;

    public function __construct(
        Context $context,
        FormKeyValidator $formKeyValidator,
        ScopeConfigInterface $scopeConfig,
        TransportBuilder $transportBuilder,
        TemplateFactoryInterface $templateFactory,
        SenderResolverInterface $senderResolver,
        TransportInterfaceFactory $transportFactory,
        EmailMessageInterfaceFactory $emailMessageFactory,
        DataPersistorInterface $dataPersistor,
        StateInterface $inlineTranslation,
        StoreManagerInterface $storeManager,
        UploaderFactory $uploaderFactory,
        Filesystem $filesystem,
        EmailAddress $emailValidator,
        UrlValidator $urlValidator,
        LoggerInterface $logger,
        Mime $mime,
        SubmissionFactory $submissionFactory
    ) {
        parent::__construct($context);
        $this->formKeyValidator = $formKeyValidator;
        $this->scopeConfig = $scopeConfig;
        $this->transportBuilder = $transportBuilder;
        $this->templateFactory = $templateFactory;
        $this->senderResolver = $senderResolver;
        $this->transportFactory = $transportFactory;
        $this->emailMessageFactory = $emailMessageFactory;
        $this->dataPersistor = $dataPersistor;
        $this->inlineTranslation = $inlineTranslation;
        $this->storeManager = $storeManager;
        $this->uploaderFactory = $uploaderFactory;
        $this->filesystem = $filesystem;
        $this->emailValidator = $emailValidator;
        $this->urlValidator = $urlValidator;
        $this->logger = $logger;
        $this->mime = $mime;
        $this->submissionFactory = $submissionFactory;
    }

    public function execute()
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        /** @var \Magento\Framework\App\Request\Http $request */
        $request = $this->getRequest();
        $data = (array)$request->getPostValue();

        if (!$this->formKeyValidator->validate($request)) {
            $this->messageManager->addErrorMessage(__('Formulário inválido. Atualize a página e tente novamente.'));
            return $resultRedirect->setPath('*/*/');
        }

        if (!$this->scopeConfig->isSetFlag(self::XML_PATH_ENABLED, ScopeInterface::SCOPE_STORE)) {
            $this->messageManager->addErrorMessage(__('Formulário temporariamente indisponível.'));
            return $resultRedirect->setPath('*/*/');
        }

        if (!$data) {
            return $resultRedirect->setPath('*/*/');
        }

        if (!empty($data['hideit'])) {
            $this->messageManager->addSuccessMessage(__('Recebemos seu currículo. Obrigado!'));
            $this->dataPersistor->clear('ayo_curriculo');
            $this->dataPersistor->clear('ayo_curriculo_errors');
            return $resultRedirect->setPath('*/*/');
        }

        $fileInfo = $request->getFiles('cv_file');
        [$errors, $fieldErrors] = $this->validate($data, $fileInfo);
        if (!empty($errors)) {
            $this->dataPersistor->set('ayo_curriculo', $this->getPersistData($data));
            if (!empty($fieldErrors)) {
                $this->dataPersistor->set('ayo_curriculo_errors', $fieldErrors);
            }
            foreach ($errors as $error) {
                $this->messageManager->addErrorMessage($error);
            }
            return $resultRedirect->setPath('*/*/');
        }

        try {
            $storedFile = $this->saveFile();
            $trackingCode = $this->generateTrackingCode();
            $this->saveSubmission($data, $storedFile, $trackingCode);
            $this->sendEmail($data, $storedFile, $trackingCode);
            $this->sendConfirmationEmail($data, $trackingCode);
            $this->messageManager->addSuccessMessage(__('Recebemos seu currículo. Obrigado!'));
            $this->dataPersistor->clear('ayo_curriculo');
            $this->dataPersistor->clear('ayo_curriculo_errors');
            return $resultRedirect->setPath('*/*/', ['_query' => ['success' => 1]]);
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
            $this->dataPersistor->set('ayo_curriculo', $this->getPersistData($data));
            if (!empty($fieldErrors)) {
                $this->dataPersistor->set('ayo_curriculo_errors', $fieldErrors);
            }
        } catch (\Exception $e) {
            $this->logger->error('Erro ao enviar currículo', ['exception' => $e]);
            $this->messageManager->addErrorMessage(__('Não foi possível enviar o currículo agora. Tente novamente.'));
            $this->dataPersistor->set('ayo_curriculo', $this->getPersistData($data));
            if (!empty($fieldErrors)) {
                $this->dataPersistor->set('ayo_curriculo_errors', $fieldErrors);
            }
        }

        return $resultRedirect->setPath('*/*/');
    }

    /**
     * @param array $data
     * @param array|null $fileInfo
     * @return array
     */
    private function validate(array $data, $fileInfo): array
    {
        $errors = [];
        $fieldErrors = [];

        $name = $this->sanitizeText($data['name'] ?? '');
        $email = $this->sanitizeText($data['email'] ?? '');
        $phone = $this->sanitizeText($data['phone'] ?? '');
        $cep = $this->sanitizeText($data['cep'] ?? '');
        $cpf = $this->sanitizeText($data['cpf'] ?? '');
        $cnpj = $this->sanitizeText($data['cnpj'] ?? '');
        $workArea = $this->sanitizeText($data['work_area'] ?? '');
        $linkedin = $this->normalizeUrl($this->sanitizeText($data['linkedin'] ?? ''));
        $portfolio = $this->normalizeUrl($this->sanitizeText($data['portfolio'] ?? ''));

        if ($name === '') {
            $errors[] = __('Informe seu nome.');
            $fieldErrors['name'] = __('Informe seu nome.');
        }

        if ($email === '' || !$this->emailValidator->isValid($email)) {
            $errors[] = __('Informe um email válido.');
            $fieldErrors['email'] = __('Informe um email válido.');
        }

        if ($phone === '') {
            $errors[] = __('Informe seu telefone.');
            $fieldErrors['phone'] = __('Informe seu telefone.');
        } else {
            $phoneDigits = preg_replace('/\\D+/', '', $phone);
            if (strlen($phoneDigits) !== 10 && strlen($phoneDigits) !== 11) {
                $errors[] = __('Telefone deve ter 10 ou 11 dígitos.');
                $fieldErrors['phone'] = __('Telefone deve ter 10 ou 11 dígitos.');
            }
        }

        if ($cep !== '') {
            $cepDigits = preg_replace('/\\D+/', '', $cep);
            if (strlen($cepDigits) !== 8) {
                $errors[] = __('CEP deve ter 8 dígitos.');
                $fieldErrors['cep'] = __('CEP deve ter 8 dígitos.');
            }
        }

        if ($cpf !== '' && !$this->isValidCpf($cpf)) {
            $errors[] = __('CPF inválido.');
            $fieldErrors['cpf'] = __('CPF inválido.');
        }

        if ($cnpj !== '' && !$this->isValidCnpj($cnpj)) {
            $errors[] = __('CNPJ inválido.');
            $fieldErrors['cnpj'] = __('CNPJ inválido.');
        }

        if ($linkedin !== '' && !$this->urlValidator->isValid($linkedin, ['http', 'https'])) {
            $errors[] = __('Informe um link do LinkedIn válido.');
            $fieldErrors['linkedin'] = __('Informe um link do LinkedIn válido.');
        }

        if ($portfolio !== '' && !$this->urlValidator->isValid($portfolio, ['http', 'https'])) {
            $errors[] = __('Informe um link de portfólio válido.');
            $fieldErrors['portfolio'] = __('Informe um link de portfólio válido.');
        }

        if ($workArea === '') {
            $errors[] = __('Selecione a área de interesse.');
            $fieldErrors['work_area'] = __('Selecione a área de interesse.');
        }

        if (empty($data['consent'])) {
            $errors[] = __('É necessário aceitar o consentimento.');
            $fieldErrors['consent'] = __('É necessário aceitar o consentimento.');
        }

        if (empty($fileInfo) || !isset($fileInfo['error'])) {
            $errors[] = __('Envie seu currículo.');
            $fieldErrors['cv_file'] = __('Envie seu currículo.');
            return [$errors, $fieldErrors];
        }

        if ((int)$fileInfo['error'] === UPLOAD_ERR_NO_FILE) {
            $errors[] = __('Envie seu currículo.');
            $fieldErrors['cv_file'] = __('Envie seu currículo.');
        } elseif ((int)$fileInfo['error'] !== UPLOAD_ERR_OK) {
            $errors[] = __('O arquivo do currículo não pôde ser enviado.');
            $fieldErrors['cv_file'] = __('O arquivo do currículo não pôde ser enviado.');
        }

        $maxBytes = $this->getMaxFileSizeBytes();
        if (!empty($fileInfo['size']) && (int)$fileInfo['size'] > $maxBytes) {
            $errors[] = __('O arquivo excede o tamanho máximo de %1 MB.', $this->getMaxFileSizeMb());
            $fieldErrors['cv_file'] = __('O arquivo excede o tamanho máximo de %1 MB.', $this->getMaxFileSizeMb());
        }

        $extension = strtolower((string)pathinfo($fileInfo['name'] ?? '', PATHINFO_EXTENSION));
        if ($extension === '' || !in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            $errors[] = __('Formato de arquivo inválido. Envie PDF, DOC ou DOCX.');
            $fieldErrors['cv_file'] = __('Formato de arquivo inválido. Envie PDF, DOC ou DOCX.');
        }

        if (!empty($fileInfo['tmp_name']) && is_uploaded_file($fileInfo['tmp_name'])) {
            $mimeType = (string)$this->mime->getMimeType($fileInfo['tmp_name']);
            if ($mimeType !== '' && !in_array($mimeType, self::ALLOWED_MIME_TYPES, true)) {
                $errors[] = __('Formato de arquivo inválido.');
                $fieldErrors['cv_file'] = __('Formato de arquivo inválido.');
            }

            if ($extension !== '' && !$this->isFileSignatureValid($fileInfo['tmp_name'], $extension)) {
                $errors[] = __('O conteúdo do arquivo não corresponde ao formato informado.');
                $fieldErrors['cv_file'] = __('O conteúdo do arquivo não corresponde ao formato informado.');
            }
        }

        return [$errors, $fieldErrors];
    }

    /**
     * @return string
     * @throws LocalizedException
     */
    private function saveFile(): string
    {
        $varDirectory = $this->filesystem->getDirectoryWrite(DirectoryList::VAR_DIR);
        if (!$varDirectory->isExist(self::STORAGE_DIR)) {
            $varDirectory->create(self::STORAGE_DIR);
        }

        $uploader = $this->uploaderFactory->create(['fileId' => 'cv_file']);
        $uploader->setAllowedExtensions(self::ALLOWED_EXTENSIONS);
        $uploader->setAllowRenameFiles(true);
        $uploader->setFilesDispersion(false);
        $uploader->setAllowCreateFolders(true);

        $result = $uploader->save($varDirectory->getAbsolutePath(self::STORAGE_DIR));
        if (!$result || !isset($result['file'])) {
            throw new LocalizedException(__('Não foi possível salvar o arquivo do currículo.'));
        }

        return self::STORAGE_DIR . '/' . ltrim($result['file'], '/');
    }

    /**
     * @param array $data
     * @param string $storedFile
     * @return void
     * @throws LocalizedException
     */
    private function sendEmail(array $data, string $storedFile, string $trackingCode): void
    {
        $storeId = (int)$this->storeManager->getStore()->getId();
        $recipientEmail = trim((string)$this->scopeConfig->getValue(
            self::XML_PATH_RECIPIENT_EMAIL,
            ScopeInterface::SCOPE_STORE,
            $storeId
        ));
        if ($recipientEmail === '') {
            $recipientEmail = (string)$this->scopeConfig->getValue(
                'trans_email/ident_general/email',
                ScopeInterface::SCOPE_STORE,
                $storeId
            );
        }

        if ($recipientEmail === '') {
            throw new LocalizedException(__('Email de destino não configurado.'));
        }

        $sender = (string)$this->scopeConfig->getValue(
            self::XML_PATH_SENDER_EMAIL_IDENTITY,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
        if ($sender === '') {
            $sender = 'general';
        }

        $senderResolved = $this->senderResolver->resolve($sender, $storeId);
        $fromEmail = (string)($senderResolved['email'] ?? '');
        $fromName = (string)($senderResolved['name'] ?? '');
        if ($fromEmail === '') {
            throw new LocalizedException(__('Email do remetente não configurado.'));
        }

        $specialtiesValue = $this->formatSpecialties($data['specialties'] ?? []);

        $statusUrl = $this->_url->getUrl('curriculo/index/status');

        $vars = [
            'name' => $this->sanitizeText($data['name'] ?? ''),
            'email' => $this->sanitizeText($data['email'] ?? ''),
            'phone' => $this->sanitizeText($data['phone'] ?? ''),
            'cep' => $this->sanitizeText($data['cep'] ?? ''),
            'cpf' => $this->sanitizeText($data['cpf'] ?? ''),
            'cnpj' => $this->sanitizeText($data['cnpj'] ?? ''),
            'city' => $this->sanitizeText($data['city'] ?? ''),
            'state' => $this->sanitizeText($data['state'] ?? ''),
            'position' => $this->sanitizeText($data['position'] ?? ''),
            'experience_level' => $this->sanitizeText($data['experience_level'] ?? ''),
            'work_area' => $this->sanitizeText($data['work_area'] ?? ''),
            'specialties' => $specialtiesValue,
            'cnh' => $this->sanitizeText($data['cnh'] ?? ''),
            'availability' => $this->sanitizeText($data['availability'] ?? ''),
            'contract_type' => $this->sanitizeText($data['contract_type'] ?? ''),
            'salary_expectation' => $this->sanitizeText($data['salary_expectation'] ?? ''),
            'referral_source' => $this->sanitizeText($data['referral_source'] ?? ''),
            'linkedin' => $this->sanitizeText($this->normalizeUrl($data['linkedin'] ?? '')),
            'portfolio' => $this->sanitizeText($this->normalizeUrl($data['portfolio'] ?? '')),
            'message' => $this->sanitizeMultiline($data['message'] ?? ''),
            'file_path' => $this->sanitizeText('var/' . ltrim($storedFile, '/')),
            'file_name' => $this->sanitizeText(basename($storedFile)),
            'tracking_code' => $trackingCode,
            'status_url' => $statusUrl,
            'submitted_date' => date('d/m/Y H:i'),
        ];

        $this->inlineTranslation->suspend();
        try {
            $template = $this->templateFactory
                ->get('ayo_curriculo_email_template')
                ->setOptions([
                    'area' => \Magento\Framework\App\Area::AREA_FRONTEND,
                    'store' => $storeId,
                ])
                ->setVars(['data' => $vars]);

            $htmlBody = (string)$template->processTemplate();
            $subject = (string)$template->getSubject();
            if (trim($subject) === '') {
                $subject = (string)__('Novo Currículo: %1', $vars['name']);
            }

            $email = new Email();
            $email->html($htmlBody, 'utf-8');
            $email->text($this->htmlToText($htmlBody), 'utf-8');

            $varDirectory = $this->filesystem->getDirectoryRead(DirectoryList::VAR_DIR);
            $attachmentAbsolutePath = $varDirectory->getAbsolutePath($storedFile);
            if (is_file($attachmentAbsolutePath) && is_readable($attachmentAbsolutePath)) {
                $attachmentMimeType = (string)$this->mime->getMimeType($attachmentAbsolutePath);
                $email->attachFromPath(
                    $attachmentAbsolutePath,
                    $vars['file_name'] !== '' ? $vars['file_name'] : null,
                    $attachmentMimeType !== '' ? $attachmentMimeType : null
                );
            }

            $messageData = [
                'body' => new SymfonyEmailMimeMessage($email),
                'subject' => $subject,
                'to' => [[
                    'email' => $recipientEmail,
                    'name' => '',
                ]],
                'from' => [[
                    'email' => $fromEmail,
                    'name' => $fromName,
                ]],
                'replyTo' => [[
                    'email' => $vars['email'],
                    'name' => $vars['name'],
                ]],
            ];

            $copyTo = $this->getCopyToEmails($storeId);
            if (!empty($copyTo)) {
                $messageData['bcc'] = array_map(static function (string $bccEmail): array {
                    return ['email' => $bccEmail, 'name' => ''];
                }, $copyTo);
            }

            /** @var \Magento\Framework\Mail\EmailMessageInterface $message */
            $message = $this->emailMessageFactory->create($messageData);
            $transport = $this->transportFactory->create(['message' => $message]);
            $transport->sendMessage();
        } catch (\Throwable $e) {
            $this->logger->error('Falha ao enviar email de currículo com anexo. Tentando fallback sem anexo.', ['exception' => $e]);

            $builder = $this->transportBuilder
                ->setTemplateIdentifier('ayo_curriculo_email_template')
                ->setTemplateOptions([
                    'area' => \Magento\Framework\App\Area::AREA_FRONTEND,
                    'store' => $storeId
                ])
                ->setTemplateVars(['data' => $vars])
                ->setFrom($sender)
                ->addTo($recipientEmail)
                ->setReplyTo($vars['email'], $vars['name']);

            $copyTo = $this->getCopyToEmails($storeId);
            if (!empty($copyTo)) {
                $builder->addBcc($copyTo);
            }

            $transport = $builder->getTransport();
            $transport->sendMessage();
        } finally {
            $this->inlineTranslation->resume();
        }
    }

    private function htmlToText(string $html): string
    {
        $text = preg_replace('#<\s*br\s*/?>#i', "\n", $html);
        $text = preg_replace('#</\s*p\s*>#i', "\n\n", (string)$text);
        $text = trim(strip_tags((string)$text));
        // Normaliza whitespace sem destruir quebras de linha
        $text = preg_replace("/\n{3,}/", "\n\n", $text);
        return (string)$text;
    }

    private function isFileSignatureValid(string $tmpPath, string $extension): bool
    {
        if (!is_file($tmpPath) || !is_readable($tmpPath)) {
            return false;
        }

        $header = file_get_contents($tmpPath, false, null, 0, 8);
        if ($header === false || $header === '') {
            return false;
        }

        if ($extension === 'pdf') {
            return strncmp($header, "%PDF-", 5) === 0;
        }

        if ($extension === 'docx') {
            $zipHeader = "PK\x03\x04";
            if (strncmp($header, $zipHeader, 4) !== 0) {
                return false;
            }

            $zip = new \ZipArchive();
            if ($zip->open($tmpPath) !== true) {
                return false;
            }

            $documentEntry = $zip->locateName('word/document.xml');
            $contentTypesEntry = $zip->locateName('[Content_Types].xml');
            $zip->close();

            return $documentEntry !== false && $contentTypesEntry !== false;
        }

        if ($extension === 'doc') {
            return strncmp($header, "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1", 8) === 0;
        }

        return false;
    }

    private function getCopyToEmails(int $storeId): array
    {
        $copyTo = (string)$this->scopeConfig->getValue(
            self::XML_PATH_COPY_TO,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
        if ($copyTo === '') {
            return [];
        }

        $emails = array_filter(array_map('trim', explode(',', $copyTo)));
        return array_values($emails);
    }

    private function getMaxFileSizeMb(): int
    {
        $value = (int)$this->scopeConfig->getValue(self::XML_PATH_MAX_FILE_SIZE_MB, ScopeInterface::SCOPE_STORE);
        if ($value <= 0) {
            return self::DEFAULT_MAX_FILE_SIZE_MB;
        }
        return $value;
    }

    private function getMaxFileSizeBytes(): int
    {
        return $this->getMaxFileSizeMb() * 1024 * 1024;
    }

    private function getPersistData(array $data): array
    {
        unset($data['hideit']);
        return $data;
    }

    private function normalizeUrl(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        if (preg_match('#^(https?|ftp)://#i', $value)) {
            return $value;
        }

        if (preg_match('#^www\\.#i', $value)) {
            return 'https://' . $value;
        }

        return 'https://' . $value;
    }

    private function sanitizeText(string $value, int $maxLength = 255): string
    {
        $value = trim(strip_tags($value));
        if (mb_strlen($value) > $maxLength) {
            $value = mb_substr($value, 0, $maxLength);
        }
        return $value;
    }

    private function isValidCpf(string $cpf): bool
    {
        $cpf = preg_replace('/\\D+/', '', $cpf);
        if (strlen($cpf) !== 11) {
            return false;
        }
        if (preg_match('/^(\\d)\\1+$/', $cpf)) {
            return false;
        }

        $sum = 0;
        for ($i = 0; $i < 9; $i++) {
            $sum += (int)$cpf[$i] * (10 - $i);
        }
        $remainder = ($sum * 10) % 11;
        if ($remainder === 10) {
            $remainder = 0;
        }
        if ($remainder !== (int)$cpf[9]) {
            return false;
        }

        $sum = 0;
        for ($i = 0; $i < 10; $i++) {
            $sum += (int)$cpf[$i] * (11 - $i);
        }
        $remainder = ($sum * 10) % 11;
        if ($remainder === 10) {
            $remainder = 0;
        }

        return $remainder === (int)$cpf[10];
    }

    private function isValidCnpj(string $cnpj): bool
    {
        $cnpj = preg_replace('/\\D+/', '', $cnpj);
        if (strlen($cnpj) !== 14) {
            return false;
        }
        if (preg_match('/^(\\d)\\1+$/', $cnpj)) {
            return false;
        }

        $length = 12;
        $numbers = substr($cnpj, 0, $length);
        $digits = substr($cnpj, $length);
        $sum = 0;
        $pos = $length - 7;

        for ($i = $length; $i >= 1; $i--) {
            $sum += (int)$numbers[$length - $i] * $pos--;
            if ($pos < 2) {
                $pos = 9;
            }
        }

        $result = $sum % 11 < 2 ? 0 : 11 - ($sum % 11);
        if ($result !== (int)$digits[0]) {
            return false;
        }

        $length = 13;
        $numbers = substr($cnpj, 0, $length);
        $sum = 0;
        $pos = $length - 7;

        for ($i = $length; $i >= 1; $i--) {
            $sum += (int)$numbers[$length - $i] * $pos--;
            if ($pos < 2) {
                $pos = 9;
            }
        }

        $result = $sum % 11 < 2 ? 0 : 11 - ($sum % 11);
        return $result === (int)$digits[1];
    }

    /**
     * Format specialties array into comma-separated string for storage and display
     */
    private function formatSpecialties($specialties): string
    {
        if (is_string($specialties)) {
            return $this->sanitizeText($specialties, 1000);
        }
        if (!is_array($specialties)) {
            return '';
        }
        $clean = array_map(function ($item) {
            return $this->sanitizeText((string)$item, 100);
        }, $specialties);
        $clean = array_filter($clean);
        return implode(', ', $clean);
    }

    private function sanitizeMultiline(string $value, int $maxLength = 2000): string
    {
        $value = trim(strip_tags($value));
        if (mb_strlen($value) > $maxLength) {
            $value = mb_substr($value, 0, $maxLength);
        }
        return $value;
    }

    /**
     * Save submission to database
     */
    private function saveSubmission(array $data, string $storedFile, string $trackingCode): void
    {
        try {
            $storeId = (int)$this->storeManager->getStore()->getId();
            
            $submission = $this->submissionFactory->create();
            $specialtiesJson = $this->formatSpecialties($data['specialties'] ?? []);

            $submission->setData([
                'tracking_code' => $trackingCode,
                'name' => $this->sanitizeText($data['name'] ?? ''),
                'email' => $this->sanitizeText($data['email'] ?? ''),
                'phone' => $this->sanitizeText($data['phone'] ?? ''),
                'cep' => $this->sanitizeText($data['cep'] ?? ''),
                'cpf' => $this->sanitizeText($data['cpf'] ?? ''),
                'cnpj' => $this->sanitizeText($data['cnpj'] ?? ''),
                'city' => $this->sanitizeText($data['city'] ?? ''),
                'state' => $this->sanitizeText($data['state'] ?? ''),
                'position' => $this->sanitizeText($data['position'] ?? ''),
                'experience_level' => $this->sanitizeText($data['experience_level'] ?? ''),
                'work_area' => $this->sanitizeText($data['work_area'] ?? ''),
                'specialties' => $specialtiesJson,
                'cnh' => $this->sanitizeText($data['cnh'] ?? ''),
                'availability' => $this->sanitizeText($data['availability'] ?? ''),
                'contract_type' => $this->sanitizeText($data['contract_type'] ?? ''),
                'salary_expectation' => $this->sanitizeText($data['salary_expectation'] ?? ''),
                'referral_source' => $this->sanitizeText($data['referral_source'] ?? ''),
                'linkedin' => $this->sanitizeText($this->normalizeUrl($data['linkedin'] ?? '')),
                'portfolio' => $this->sanitizeText($this->normalizeUrl($data['portfolio'] ?? '')),
                'message' => $this->sanitizeMultiline($data['message'] ?? ''),
                'file_path' => $storedFile,
                'file_name' => basename($storedFile),
                'status' => 'pending',
                'store_id' => $storeId,
            ]);
            
            $submission->save();
        } catch (\Exception $e) {
            $this->logger->error('Failed to save curriculum submission', ['exception' => $e]);
            // Don't fail the submission if database save fails
        }
    }

    /**
     * Generate unique tracking code for the application
     */
    private function generateTrackingCode(): string
    {
        $prefix = 'AWA';
        $timestamp = date('ymd');
        $random = strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
        return $prefix . $timestamp . $random;
    }

    /**
     * Send confirmation email to the applicant
     */
    private function sendConfirmationEmail(array $data, string $trackingCode): void
    {
        $storeId = (int)$this->storeManager->getStore()->getId();
        
        // Check if confirmation email is enabled
        $sendConfirmation = $this->scopeConfig->isSetFlag(
            self::XML_PATH_SEND_CONFIRMATION,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
        
        if (!$sendConfirmation) {
            return;
        }

        $candidateEmail = $this->sanitizeText($data['email'] ?? '');
        if ($candidateEmail === '' || !$this->emailValidator->isValid($candidateEmail)) {
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

        $statusUrl = $this->_url->getUrl('curriculo/index/status');

        $vars = [
            'name' => $this->sanitizeText($data['name'] ?? ''),
            'email' => $candidateEmail,
            'position' => $this->sanitizeText($data['position'] ?? ''),
            'experience_level' => $this->sanitizeText($data['experience_level'] ?? ''),
            'work_area' => $this->sanitizeText($data['work_area'] ?? ''),
            'tracking_code' => $trackingCode,
            'submitted_date' => date('d/m/Y H:i'),
            'status_url' => $statusUrl,
        ];

        $this->inlineTranslation->suspend();
        try {
            $transport = $this->transportBuilder
                ->setTemplateIdentifier('ayo_curriculo_confirmation_template')
                ->setTemplateOptions([
                    'area' => \Magento\Framework\App\Area::AREA_FRONTEND,
                    'store' => $storeId
                ])
                ->setTemplateVars(['data' => $vars])
                ->setFrom($sender)
                ->addTo($candidateEmail, $vars['name'])
                ->getTransport();

            $transport->sendMessage();
        } catch (\Exception $e) {
            // Log but don't fail the submission
            $this->logger->error('Failed to send confirmation email', ['exception' => $e]);
        } finally {
            $this->inlineTranslation->resume();
        }
    }
}
