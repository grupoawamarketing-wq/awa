<?php

declare(strict_types=1);

namespace GrupoAwamotos\Vlibras\Block;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Store\Model\ScopeInterface;

/**
 * Block responsável por renderizar o widget de acessibilidade VLibras.
 * O widget conecta-se ao serviço gov.br https://vlibras.gov.br
 */
class Vlibras extends Template
{
    private const XML_PATH_ENABLED = 'grupoawamotos_vlibras/general/enabled';

    public function __construct(
        Context $context,
        private readonly ScopeConfigInterface $scopeConfig,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function isEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_ENABLED,
            ScopeInterface::SCOPE_STORE
        );
    }
}
