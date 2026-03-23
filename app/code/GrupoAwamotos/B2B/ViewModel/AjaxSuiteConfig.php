<?php
declare(strict_types=1);

namespace GrupoAwamotos\B2B\ViewModel;

use GrupoAwamotos\B2B\Helper\Config;
use Magento\Framework\View\Element\Block\ArgumentInterface;

class AjaxSuiteConfig implements ArgumentInterface
{
    public function __construct(
        private readonly Config $config
    ) {
    }

    public function shouldUseB2BLogin(): bool
    {
        return $this->config->isEnabled() && $this->config->isStrictB2B();
    }
}
