<?php

declare(strict_types=1);

namespace GrupoAwamotos\WhatsAppCommerce\Block\Adminhtml;

use GrupoAwamotos\WhatsAppCommerce\Api\HealthCheckInterface;
use GrupoAwamotos\WhatsAppCommerce\Helper\Config;
use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;

class Dashboard extends Template
{
    public function __construct(
        Context $context,
        private readonly HealthCheckInterface $healthCheck,
        private readonly Config $config,
        array $data = [],
    ) {
        parent::__construct($context, $data);
    }

    public function getHealthData(): array
    {
        return $this->healthCheck->check();
    }

    public function isModuleEnabled(): bool
    {
        return $this->config->isEnabled();
    }

    public function getNotificationConfig(): array
    {
        return [
            'order_placed' => $this->config->isNotifyOrderPlacedEnabled(),
            'order_paid' => $this->config->isNotifyOrderPaidEnabled(),
            'order_shipped' => $this->config->isNotifyOrderShippedEnabled(),
            'order_refunded' => $this->config->isNotifyOrderRefundedEnabled(),
        ];
    }
}
