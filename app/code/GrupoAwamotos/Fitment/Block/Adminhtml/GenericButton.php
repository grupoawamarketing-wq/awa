<?php

declare(strict_types=1);

namespace GrupoAwamotos\Fitment\Block\Adminhtml;

use Magento\Backend\Block\Widget\Context;
use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;

/**
 * Generic button for Fitment admin forms
 */
abstract class GenericButton implements ButtonProviderInterface
{
    public function __construct(protected readonly Context $context)
    {
    }

    /**
     * Get record ID from request
     */
    protected function getId(): int
    {
        return (int) $this->context->getRequest()->getParam('id');
    }

    /**
     * Get URL to redirect after button action
     */
    protected function getUrl(string $route = '', array $params = []): string
    {
        return $this->context->getUrlBuilder()->getUrl($route, $params);
    }
}
