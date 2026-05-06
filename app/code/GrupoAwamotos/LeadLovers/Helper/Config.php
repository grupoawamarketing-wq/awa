<?php

declare(strict_types=1);

namespace GrupoAwamotos\LeadLovers\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Store\Model\ScopeInterface;

class Config extends AbstractHelper
{
    private const XML_PATH_ENABLED        = 'leadlovers/general/enabled';
    private const XML_PATH_API_TOKEN      = 'leadlovers/general/api_token';
    private const XML_PATH_MACHINE_CODE   = 'leadlovers/general/machine_code';
    private const XML_PATH_SEQUENCE_CODE  = 'leadlovers/general/sequence_code';
    private const XML_PATH_SEQUENCE_LEVEL = 'leadlovers/general/sequence_level';
    private const XML_PATH_TAG_ID         = 'leadlovers/general/tag_id';

    public function isEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_ENABLED,
            ScopeInterface::SCOPE_STORE
        );
    }

    public function getApiToken(): string
    {
        return (string) $this->scopeConfig->getValue(
            self::XML_PATH_API_TOKEN,
            ScopeInterface::SCOPE_STORE
        );
    }

    public function getMachineCode(): int
    {
        return (int) $this->scopeConfig->getValue(
            self::XML_PATH_MACHINE_CODE,
            ScopeInterface::SCOPE_STORE
        );
    }

    public function getSequenceCode(): int
    {
        return (int) $this->scopeConfig->getValue(
            self::XML_PATH_SEQUENCE_CODE,
            ScopeInterface::SCOPE_STORE
        );
    }

    public function getSequenceLevel(): int
    {
        return (int) $this->scopeConfig->getValue(
            self::XML_PATH_SEQUENCE_LEVEL,
            ScopeInterface::SCOPE_STORE
        );
    }

    public function getTagId(): int
    {
        return (int) $this->scopeConfig->getValue(
            self::XML_PATH_TAG_ID,
            ScopeInterface::SCOPE_STORE
        );
    }
}
