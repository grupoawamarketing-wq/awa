<?php

declare(strict_types=1);

namespace GrupoAwamotos\WhatsAppCommerce\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\HTTP\PhpEnvironment\RemoteAddress;
use Psr\Log\LoggerInterface;

class LogConsentChange implements ObserverInterface
{
    private ResourceConnection $resourceConnection;
    private RemoteAddress $remoteAddress;
    private LoggerInterface $logger;

    public function __construct(
        ResourceConnection $resourceConnection,
        RemoteAddress $remoteAddress,
        LoggerInterface $logger
    ) {
        $this->resourceConnection = $resourceConnection;
        $this->remoteAddress = $remoteAddress;
        $this->logger = $logger;
    }

    public function execute(Observer $observer): void
    {
        /** @var \Magento\Customer\Model\Customer $customer */
        $customer = $observer->getEvent()->getCustomer();

        if (!$customer || !$customer->getId()) {
            return;
        }

        $newOptin = (int) $customer->getData('whatsapp_optin');
        $origOptin = (int) ($customer->getOrigData('whatsapp_optin') ?? 0);

        if ($newOptin === $origOptin) {
            return;
        }

        try {
            $connection = $this->resourceConnection->getConnection();
            $tableName = $this->resourceConnection->getTableName('awa_whatsapp_consent_log');

            $phone = $customer->getData('telephone')
                ?? $customer->getDefaultBillingAddress()?->getTelephone()
                ?? '';

            $connection->insert($tableName, [
                'customer_id' => (int) $customer->getId(),
                'phone' => (string) $phone,
                'optin' => $newOptin,
                'source' => $this->detectSource(),
                'ip_address' => $this->remoteAddress->getRemoteAddress(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            ]);

            $this->logger->info('WhatsApp consent changed', [
                'customer_id' => $customer->getId(),
                'optin' => $newOptin,
                'source' => $this->detectSource(),
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to log WhatsApp consent change: ' . $e->getMessage(), [
                'customer_id' => $customer->getId(),
            ]);
        }
    }

    private function detectSource(): string
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '';

        if (str_contains($uri, '/admin/') || str_contains($uri, 'adminhtml')) {
            return 'admin';
        }

        if (str_contains($uri, '/rest/') || str_contains($uri, '/graphql')) {
            return 'api';
        }

        if (str_contains($uri, 'checkout')) {
            return 'checkout';
        }

        return 'account';
    }
}
