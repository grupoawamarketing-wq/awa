<?php
declare(strict_types=1);

namespace GrupoAwamotos\LiveChat\Plugin\LiveChat\Controller\GetCart;

use GrupoAwamotos\B2B\Model\Customer\Attribute\Source\ApprovalStatus;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Api\GroupRepositoryInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Exception\NoSuchEntityException;

class CustomerContextPlugin
{
    private const MAX_VALUE_LENGTH = 500;

    private CustomerSession $customerSession;
    private CustomerRepositoryInterface $customerRepository;
    private GroupRepositoryInterface $groupRepository;

    public function __construct(
        CustomerSession $customerSession,
        CustomerRepositoryInterface $customerRepository,
        GroupRepositoryInterface $groupRepository
    ) {
        $this->customerSession = $customerSession;
        $this->customerRepository = $customerRepository;
        $this->groupRepository = $groupRepository;
    }

    /**
     * @param array<int, array{name: string, value: string}> $result
     * @return array<int, array{name: string, value: string}>
     */
    public function afterGetCustomVariables(\LiveChat\LiveChat\Controller\GetCart\Index $subject, array $result): array
    {
        if (!$this->customerSession->isLoggedIn()) {
            return $result;
        }

        try {
            $customer = $this->customerRepository->getById((int) $this->customerSession->getCustomerId());
        } catch (NoSuchEntityException $exception) {
            return $result;
        }

        $variables = [];
        $groupLabel = null;

        try {
            $groupLabel = $this->groupRepository->getById((int) $customer->getGroupId())->getCode();
        } catch (NoSuchEntityException $exception) {
            $groupLabel = null;
        }

        $cnpj = $this->getCustomerAttributeValue($customer, 'b2b_cnpj');
        $personType = $this->getCustomerAttributeValue($customer, 'b2b_person_type');

        $this->appendVariable(
            $variables,
            'Tipo de cliente',
            $personType === 'pj' || $cnpj !== null ? 'B2B' : 'B2C'
        );
        $this->appendVariable($variables, 'Grupo do cliente', $groupLabel);
        $this->appendVariable(
            $variables,
            'Status B2B',
            $this->getApprovalStatusLabel($this->getCustomerAttributeValue($customer, 'b2b_approval_status'))
        );
        $this->appendVariable($variables, 'CNPJ', $cnpj);
        $this->appendVariable($variables, 'Razao social', $this->getCustomerAttributeValue($customer, 'b2b_razao_social'));
        $this->appendVariable($variables, 'Nome fantasia', $this->getCustomerAttributeValue($customer, 'b2b_nome_fantasia'));
        $this->appendVariable($variables, 'Telefone', $this->getCustomerAttributeValue($customer, 'b2b_phone'));

        return $this->mergeVariables($result, $variables);
    }

    private function getCustomerAttributeValue(CustomerInterface $customer, string $attributeCode): ?string
    {
        $attribute = $customer->getCustomAttribute($attributeCode);
        if ($attribute === null) {
            return null;
        }

        $value = $attribute->getValue();
        if (!is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }

    private function getApprovalStatusLabel(?string $status): ?string
    {
        if ($status === null || $status === '') {
            return null;
        }

        $labels = [
            ApprovalStatus::STATUS_PENDING => 'Pendente de aprovacao',
            ApprovalStatus::STATUS_APPROVED => 'Aprovado',
            ApprovalStatus::STATUS_REJECTED => 'Rejeitado',
            ApprovalStatus::STATUS_SUSPENDED => 'Suspenso',
        ];

        return $labels[$status] ?? $status;
    }

    /**
     * @param array<int, array{name: string, value: string}> $variables
     */
    private function appendVariable(array &$variables, string $name, ?string $value): void
    {
        if ($value === null) {
            return;
        }

        $value = $this->truncateValue($value);
        if ($value === '') {
            return;
        }

        $variables[] = [
            'name' => $name,
            'value' => $value,
        ];
    }

    /**
     * @param array<int, array{name: string, value: string}> $baseVariables
     * @param array<int, array{name: string, value: string}> $extraVariables
     * @return array<int, array{name: string, value: string}>
     */
    private function mergeVariables(array $baseVariables, array $extraVariables): array
    {
        $merged = [];

        foreach (array_merge($baseVariables, $extraVariables) as $variable) {
            if (!isset($variable['name'], $variable['value'])) {
                continue;
            }

            $merged[$variable['name']] = [
                'name' => (string) $variable['name'],
                'value' => (string) $variable['value'],
            ];
        }

        return array_values($merged);
    }

    private function truncateValue(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        if (mb_strlen($value) <= self::MAX_VALUE_LENGTH) {
            return $value;
        }

        return mb_substr($value, 0, self::MAX_VALUE_LENGTH - 3) . '...';
    }
}
