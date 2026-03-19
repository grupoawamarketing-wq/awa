<?php
declare(strict_types=1);

namespace GrupoAwamotos\ERPIntegration\Model;

use Magento\Framework\App\ResourceConnection;
use Magento\Sales\Model\Order;
use Psr\Log\LoggerInterface;

class OpenCartBridgeCustomerSync
{
    private const BR_COUNTRY_ID = 30;
    private const BR_LANGUAGE_ID = 2;
    private const PF_GROUP_ID = 1;
    private const B2B_GROUP_ID = 2;
    private const BRIDGE_STORE_ID = 0;

    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function syncByOrder(Order $order): void
    {
        $customerId = (int) $order->getCustomerId();
        if ($customerId <= 0) {
            return;
        }

        try {
            $connection = $this->resourceConnection->getConnection();
            $mapTable = $this->resourceConnection->getTableName('oc_customer_id_map');
            $customerTable = $this->resourceConnection->getTableName('oc_customer');
            $addressTable = $this->resourceConnection->getTableName('oc_address');
            $confirmedTable = $this->resourceConnection->getTableName('oc_customer_b2b_confirmed');
            $preRegistrationTable = $this->resourceConnection->getTableName('oc_pre_registration');
            $zoneMappingTable = $this->resourceConnection->getTableName('oc_zone_mapping');

            $mappedCustomerId = $connection->fetchOne(
                $connection->select()
                    ->from($mapTable, ['old_oc_customer_id'])
                    ->where('magento_customer_id = ?', $customerId)
                    ->limit(1)
            );

            $exportCustomerId = $mappedCustomerId ? (int) $mappedCustomerId : $customerId + 200000;
            $fullName = trim((string) $order->getCustomerFirstname() . ' ' . (string) $order->getCustomerLastname());
            $taxvat = $this->normalizeTaxvat((string) ($order->getData('b2b_cnpj') ?: $order->getCustomerTaxvat()));
            $bridgeCustomerGroupId = $this->resolveCustomerGroupId($taxvat);
            $billingAddress = $order->getBillingAddress();
            $shippingAddress = $order->getShippingAddress();
            $bridgeAddress = $billingAddress ?: $shippingAddress;
            $telephone = (string) ($billingAddress?->getTelephone() ?: $shippingAddress?->getTelephone() ?: '');
            $bridgeAddressId = 0;
            $bridgeZoneId = 0;

            if ($bridgeAddress && $bridgeAddress->getRegionId()) {
                $mappedZoneId = $connection->fetchOne(
                    $connection->select()
                        ->from($zoneMappingTable, ['oc_zone_id'])
                        ->where('magento_region_id = ?', (int) $bridgeAddress->getRegionId())
                        ->limit(1)
                );
                $bridgeZoneId = $mappedZoneId ? (int) $mappedZoneId : 0;
            }

            if ($bridgeAddress) {
                $nativeAddressId = $this->resolveSourceAddressId($order, $bridgeAddress);
                $bridgeAddressId = $nativeAddressId > 0
                    ? ($mappedCustomerId ? $nativeAddressId : $nativeAddressId + 200000)
                    : 0;

                if ($bridgeAddressId > 0) {
                    $connection->insertOnDuplicate(
                        $addressTable,
                        [
                            'address_id' => $bridgeAddressId,
                            'customer_id' => $exportCustomerId,
                            'firstname' => substr((string) ($bridgeAddress->getFirstname() ?: $order->getCustomerFirstname() ?: ''), 0, 128),
                            'lastname' => substr((string) ($bridgeAddress->getLastname() ?: $order->getCustomerLastname() ?: ''), 0, 128),
                            'company' => substr((string) ($bridgeAddress->getCompany() ?: $fullName), 0, 255),
                            'address_1' => substr($this->extractAddressLine($bridgeAddress->getStreet(), 0), 0, 255),
                            'address_2' => substr($this->extractAddressRemainder($bridgeAddress->getStreet()), 0, 255),
                            'city' => substr((string) ($bridgeAddress->getCity() ?: ''), 0, 128),
                            'postcode' => substr((string) ($bridgeAddress->getPostcode() ?: ''), 0, 10),
                            'country_id' => self::BR_COUNTRY_ID,
                            'zone_id' => $bridgeZoneId,
                            'custom_field' => '[]',
                        ],
                        [
                            'firstname',
                            'lastname',
                            'company',
                            'address_1',
                            'address_2',
                            'city',
                            'postcode',
                            'zone_id',
                        ]
                    );
                }
            }

            $customerPayload = [
                'customer_id' => $exportCustomerId,
                'customer_group_id' => $bridgeCustomerGroupId,
                'store_id' => self::BRIDGE_STORE_ID,
                'language_id' => self::BR_LANGUAGE_ID,
                'firstname' => substr((string) ($order->getCustomerFirstname() ?: ''), 0, 128),
                'lastname' => substr((string) ($order->getCustomerLastname() ?: ''), 0, 128),
                'email' => substr((string) ($order->getCustomerEmail() ?: ''), 0, 128),
                'telephone' => substr($telephone, 0, 64),
                'fax' => '',
                'password' => '',
                'salt' => '',
                'cart' => null,
                'wishlist' => null,
                'newsletter' => 0,
                'address_id' => $bridgeAddressId,
                'custom_field' => $this->buildCustomField($taxvat, $fullName),
                'ip' => '',
                'status' => 1,
                'safe' => 0,
                'token' => '',
                'code' => (string) $exportCustomerId,
                'date_added' => (string) ($order->getCreatedAt() ?: date('Y-m-d H:i:s')),
            ];

            $preRegistrationPayload = $customerPayload;
            $preRegistrationPayload['status'] = 0;

            $connection->insertOnDuplicate(
                $customerTable,
                $customerPayload,
                [
                    'customer_group_id',
                    'store_id',
                    'firstname',
                    'lastname',
                    'email',
                    'telephone',
                    'address_id',
                    'custom_field',
                    'status',
                    'safe',
                    'code',
                ]
            );

            if ($bridgeCustomerGroupId === self::B2B_GROUP_ID) {
                $connection->insertOnDuplicate(
                    $confirmedTable,
                    [
                        'customer_id' => $exportCustomerId,
                        'synced_at' => date('Y-m-d H:i:s'),
                    ],
                    ['synced_at']
                );
            } else {
                $connection->delete($confirmedTable, ['customer_id = ?' => $exportCustomerId]);
            }

            $connection->insertOnDuplicate(
                $preRegistrationTable,
                $preRegistrationPayload,
                [
                    'customer_group_id',
                    'store_id',
                    'firstname',
                    'lastname',
                    'email',
                    'telephone',
                    'address_id',
                    'custom_field',
                    'status',
                    'safe',
                    'code',
                ]
            );

            $this->logger->info('[ERP Bridge] Synced customer to OpenCart bridge', [
                'magento_customer_id' => $customerId,
                'bridge_customer_id' => $exportCustomerId,
                'order_increment_id' => $order->getIncrementId(),
            ]);
        } catch (\Exception $exception) {
            $this->logger->warning('[ERP Bridge] Failed to sync customer to OpenCart bridge: ' . $exception->getMessage(), [
                'order_increment_id' => $order->getIncrementId(),
                'customer_id' => $customerId,
            ]);
        }
    }

    public function cleanupLegacyBridgeState(): int
    {
        $connection = $this->resourceConnection->getConnection();
        $confirmedTable = $this->resourceConnection->getTableName('oc_customer_b2b_confirmed');
        $customerTable = $this->resourceConnection->getTableName('oc_customer');

        $staleCustomerIds = $connection->fetchCol(
            $connection->select()
                ->from(['cbc' => $confirmedTable], ['customer_id'])
                ->joinLeft(
                    ['oc' => $customerTable],
                    'oc.customer_id = cbc.customer_id',
                    []
                )
                ->where('oc.customer_id IS NULL OR oc.customer_group_id <> ?', self::B2B_GROUP_ID)
        );

        if ($staleCustomerIds === []) {
            return 0;
        }

        $connection->delete($confirmedTable, ['customer_id IN (?)' => $staleCustomerIds]);

        return count($staleCustomerIds);
    }

    private function normalizeTaxvat(string $value): string
    {
        return preg_replace('/\D+/', '', $value) ?: '';
    }

    private function resolveCustomerGroupId(string $document): int
    {
        return strlen($document) === 14 ? self::B2B_GROUP_ID : self::PF_GROUP_ID;
    }

    /**
     * @param string|array<int, string>|null $street
     */
    private function extractAddressLine(string|array|null $street, int $index): string
    {
        $lines = is_array($street) ? $street : preg_split('/\r?\n/', (string) $street);
        return trim((string) ($lines[$index] ?? ''));
    }

    /**
     * @param string|array<int, string>|null $street
     */
    private function extractAddressRemainder(string|array|null $street): string
    {
        $lines = is_array($street) ? $street : preg_split('/\r?\n/', (string) $street);
        if (!is_array($lines) || count($lines) <= 1) {
            return '';
        }

        return trim(implode(', ', array_slice($lines, 1)));
    }

    private function buildCustomField(string $taxvat, string $fullName): string
    {
        $safeName = str_replace('"', '', $fullName);

        if (strlen($taxvat) === 14) {
            return sprintf(
                '{"6":"%s","2":"","3":"ISENTO","1":"%s"}',
                $this->formatCnpj($taxvat),
                $safeName
            );
        }

        if (strlen($taxvat) === 11) {
            return sprintf(
                '{"6":"","2":"%s","3":"","1":"%s"}',
                $this->formatCpf($taxvat),
                $safeName
            );
        }

        return sprintf(
            '{"6":"","2":"","3":"","1":"%s"}',
            $safeName
        );
    }

    private function formatCpf(string $value): string
    {
        return preg_replace('/^(\d{3})(\d{3})(\d{3})(\d{2})$/', '$1.$2.$3-$4', $value) ?: $value;
    }

    private function formatCnpj(string $value): string
    {
        return preg_replace('/^(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})$/', '$1.$2.$3/$4-$5', $value) ?: $value;
    }

    private function resolveSourceAddressId(Order $order, object $bridgeAddress): int
    {
        $customerAddressId = (int) $bridgeAddress->getData('customer_address_id');
        if ($customerAddressId > 0) {
            return $customerAddressId;
        }

        $addressId = (int) ($bridgeAddress->getData('entity_id') ?: $bridgeAddress->getId());
        if ($addressId > 0) {
            return $addressId;
        }

        $addressType = (string) $bridgeAddress->getData('address_type');
        if ($addressType === 'billing') {
            return (int) $order->getBillingAddressId();
        }

        if ($addressType === 'shipping') {
            return (int) $order->getShippingAddressId();
        }

        return (int) ($order->getShippingAddressId() ?: $order->getBillingAddressId());
    }
}
