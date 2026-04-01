<?php

namespace Ayo\Curriculo\Block;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\View\Element\Template;
use Magento\Store\Model\ScopeInterface;

class Form extends Template
{
    public const XML_PATH_ENABLED = 'ayo_curriculo/general/enabled';
    public const XML_PATH_MAX_FILE_SIZE_MB = 'ayo_curriculo/general/max_file_size_mb';
    private const DEFAULT_MAX_FILE_SIZE_MB = 5;

    /**
     * @var FormKey
     */
    private $formKey;

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var DataPersistorInterface
     */
    private $dataPersistor;

    /**
     * @var array|null
     */
    private $postData;

    /**
     * @var array|null
     */
    private $postErrors;

    public function __construct(
        Template\Context $context,
        FormKey $formKey,
        ScopeConfigInterface $scopeConfig,
        DataPersistorInterface $dataPersistor,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->formKey = $formKey;
        $this->scopeConfig = $scopeConfig;
        $this->dataPersistor = $dataPersistor;
    }

    public function isEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_ENABLED, ScopeInterface::SCOPE_STORE);
    }

    public function getPostUrl(): string
    {
        return $this->getUrl('curriculo/index/post');
    }

    public function getFormKeyValue(): string
    {
        return $this->formKey->getFormKey();
    }

    public function getMaxFileSizeMb(): int
    {
        $value = (int)$this->scopeConfig->getValue(self::XML_PATH_MAX_FILE_SIZE_MB, ScopeInterface::SCOPE_STORE);
        if ($value <= 0) {
            return self::DEFAULT_MAX_FILE_SIZE_MB;
        }
        return $value;
    }

    public function getAllowedExtensionsLabel(): string
    {
        return 'PDF, DOC, DOCX';
    }

    public function isSuccess(): bool
    {
        return (bool)$this->getRequest()->getParam('success');
    }

    public function getPostValue(string $key): string
    {
        if ($this->postData === null) {
            $this->postData = (array)$this->dataPersistor->get('ayo_curriculo');
            $this->dataPersistor->clear('ayo_curriculo');
        }

        return isset($this->postData[$key]) ? (string)$this->postData[$key] : '';
    }

    public function isPostValueChecked(string $key): bool
    {
        $value = $this->getPostValue($key);
        if ($value === '') {
            return false;
        }
        return in_array(strtolower((string)$value), ['1', 'true', 'on', 'yes'], true);
    }

    public function getFieldError(string $key): string
    {
        if ($this->postErrors === null) {
            $this->postErrors = (array)$this->dataPersistor->get('ayo_curriculo_errors');
            $this->dataPersistor->clear('ayo_curriculo_errors');
        }

        return isset($this->postErrors[$key]) ? (string)$this->postErrors[$key] : '';
    }

    /**
     * @return array<string, string>
     */
    public function getFieldErrors(): array
    {
        if ($this->postErrors === null) {
            $this->postErrors = (array)$this->dataPersistor->get('ayo_curriculo_errors');
            $this->dataPersistor->clear('ayo_curriculo_errors');
        }

        return $this->postErrors;
    }

    public function hasFieldError(string $key): bool
    {
        return $this->getFieldError($key) !== '';
    }
}
