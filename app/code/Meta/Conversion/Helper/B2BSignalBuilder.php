<?php

declare(strict_types=1);

namespace Meta\Conversion\Helper;

use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Model\Customer as CustomerModel;

/**
 * Builds a consistent B2B classification payload for Meta custom_data.
 */
class B2BSignalBuilder
{
    /**
     * @param array<string, mixed> $context
     * @return array<string, string|int|bool>
     */
    public function build(array $context = [], CustomerInterface|CustomerModel|null $customer = null): array
    {
        $personType = $this->normalizeValue(
            $context['person_type'] ?? $this->getCustomerAttributeValue($customer, 'b2b_person_type') ?? 'pj',
            'pj'
        );
        $approvalStatus = $this->normalizeValue(
            $context['approval_status'] ?? $this->getCustomerAttributeValue($customer, 'b2b_approval_status') ?? 'pending',
            'pending'
        );
        $leadType = $this->normalizeValue(
            $context['lead_type'] ?? ($personType === 'pj' ? 'b2b_cnpj' : 'b2b'),
            'b2b'
        );
        $registerChannel = $this->normalizeValue($context['register_channel'] ?? 'b2b_register_form', 'b2b_register_form');

        $payload = [
            'business_model' => 'b2b',
            'business_segment' => 'wholesale',
            'audience_type' => $personType === 'pj' ? 'company' : 'individual_business',
            'lead_type' => $leadType,
            'person_type' => $personType,
            'business_document_type' => $personType === 'pj' ? 'cnpj' : 'cpf',
            'approval_status' => $approvalStatus,
            'customer_lifecycle_stage' => $this->resolveLifecycleStage($approvalStatus),
            'register_channel' => $registerChannel,
            'is_b2b' => true
        ];

        $customerGroupId = $context['customer_group_id'] ?? $this->extractCustomerGroupId($customer);
        if ($customerGroupId !== null) {
            $payload['customer_group_id'] = $customerGroupId;
        }

        if (array_key_exists('cnpj_validated', $context)) {
            $payload['cnpj_validated'] = (bool) $context['cnpj_validated'];
        }

        return $payload;
    }

    private function resolveLifecycleStage(string $approvalStatus): string
    {
        return match ($approvalStatus) {
            'approved' => 'qualified',
            'rejected' => 'disqualified',
            default => 'lead'
        };
    }

    private function extractCustomerGroupId(CustomerInterface|CustomerModel|null $customer): ?int
    {
        if ($customer === null || !method_exists($customer, 'getGroupId')) {
            return null;
        }

        $groupId = $customer->getGroupId();

        return $groupId !== null ? (int) $groupId : null;
    }

    private function getCustomerAttributeValue(CustomerInterface|CustomerModel|null $customer, string $attributeCode): ?string
    {
        if ($customer === null) {
            return null;
        }

        if (method_exists($customer, 'getCustomAttribute')) {
            $attribute = $customer->getCustomAttribute($attributeCode);
            if ($attribute !== null) {
                $value = trim((string) $attribute->getValue());
                if ($value !== '') {
                    return $value;
                }
            }
        }

        if (method_exists($customer, 'getData')) {
            $value = trim((string) $customer->getData($attributeCode));
            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    private function normalizeValue(mixed $value, string $default): string
    {
        $normalized = strtolower(trim((string) $value));

        return $normalized !== '' ? $normalized : $default;
    }
}
