<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Model;

use GrupoAwamotos\B2B\Api\ApprovalScoreServiceInterface;
use GrupoAwamotos\B2B\Api\CustomerApprovalInterface;
use GrupoAwamotos\B2B\Api\Data\ApprovalScoreResultInterface;
use GrupoAwamotos\B2B\Helper\CnpjValidator;
use GrupoAwamotos\B2B\Helper\Config;
use GrupoAwamotos\B2B\Model\Customer\Attribute\Source\ApprovalStatus;
use Magento\Customer\Api\AddressRepositoryInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Psr\Log\LoggerInterface;

class ApprovalScoreService implements ApprovalScoreServiceInterface
{
    private CustomerRepositoryInterface $customerRepository;
    private AddressRepositoryInterface $addressRepository;
    private Config $config;
    private CnpjValidator $cnpjValidator;
    private CnaeClassifier $cnaeClassifier;
    private CnpjDuplicateChecker $duplicateChecker;
    private CustomerApprovalInterface $customerApproval;
    private LoggerInterface $logger;

    public function __construct(
        CustomerRepositoryInterface $customerRepository,
        AddressRepositoryInterface $addressRepository,
        Config $config,
        CnpjValidator $cnpjValidator,
        CnaeClassifier $cnaeClassifier,
        CnpjDuplicateChecker $duplicateChecker,
        CustomerApprovalInterface $customerApproval,
        LoggerInterface $logger
    ) {
        $this->customerRepository = $customerRepository;
        $this->addressRepository = $addressRepository;
        $this->config = $config;
        $this->cnpjValidator = $cnpjValidator;
        $this->cnaeClassifier = $cnaeClassifier;
        $this->duplicateChecker = $duplicateChecker;
        $this->customerApproval = $customerApproval;
        $this->logger = $logger;
    }

    public function evaluate(int $customerId): ApprovalScoreResultInterface
    {
        try {
            $customer = $this->customerRepository->getById($customerId);
        } catch (\Exception $e) {
            return new ApprovalScoreResult(
                ApprovalScoreResultInterface::SCORE_RED,
                (string) __('Cliente não encontrado para triagem.'),
                $this->config->getDefaultB2BGroupId(),
                false
            );
        }

        $cnaeProfile = (string) ($this->getAttributeValue($customer, 'b2b_cnae_profile') ?? '');
        $suggestedGroupId = $this->resolveSuggestedGroupId($cnaeProfile);

        $duplicate = $this->duplicateChecker->findConflict($customerId);
        if ($duplicate !== null) {
            return new ApprovalScoreResult(
                ApprovalScoreResultInterface::SCORE_RED,
                (string) __(
                    'CNPJ duplicado: já vinculado ao cliente #%1 (%2).',
                    $duplicate['customer_id'],
                    $duplicate['email']
                ),
                $suggestedGroupId,
                false
            );
        }

        $missingFields = $this->getMissingRequiredFields($customer);
        if ($missingFields !== []) {
            return new ApprovalScoreResult(
                ApprovalScoreResultInterface::SCORE_RED,
                (string) __('Dados incompletos: %1.', implode(', ', $missingFields)),
                $suggestedGroupId,
                false
            );
        }

        $cnpj = preg_replace('/\D/', '', (string) $this->getAttributeValue($customer, 'b2b_cnpj'));
        if (!$this->cnpjValidator->validateLocal($cnpj)) {
            return new ApprovalScoreResult(
                ApprovalScoreResultInterface::SCORE_RED,
                (string) __('CNPJ inválido (dígitos verificadores).'),
                $suggestedGroupId,
                false
            );
        }

        $cnpjStatusIssue = $this->getCnpjStatusIssue($cnpj);
        if ($cnpjStatusIssue !== null) {
            return new ApprovalScoreResult(
                ApprovalScoreResultInterface::SCORE_RED,
                $cnpjStatusIssue,
                $suggestedGroupId,
                false
            );
        }

        if ($cnaeProfile === CnaeClassifier::PROFILE_DIRECT) {
            $autoApprove = $this->cnaeClassifier->isAutoApproveDirectEnabled()
                && $this->config->isApprovalScoringEnabled();

            if ($autoApprove) {
                $cnaeCode = (string) ($this->getAttributeValue($customer, 'b2b_cnae_code') ?? '');
                $cnaeDesc = (string) ($this->getAttributeValue($customer, 'b2b_cnae_description') ?? '');

                return new ApprovalScoreResult(
                    ApprovalScoreResultInterface::SCORE_GREEN,
                    (string) __(
                        'Perfil direto (motos/motopeças). CNAE %1 — %2. Elegível para aprovação automática.',
                        $cnaeCode,
                        $cnaeDesc
                    ),
                    $suggestedGroupId,
                    true
                );
            }

            return new ApprovalScoreResult(
                ApprovalScoreResultInterface::SCORE_YELLOW,
                (string) __(
                    'Perfil direto compatível com AWA, mas auto-aprovação CNAE está desligada. Revisão manual recomendada.'
                ),
                $suggestedGroupId,
                false
            );
        }

        if ($cnaeProfile === CnaeClassifier::PROFILE_ADJACENT) {
            return new ApprovalScoreResult(
                ApprovalScoreResultInterface::SCORE_YELLOW,
                (string) __(
                    'Perfil adjacente (automotivo). Requer análise manual antes de liberar condições B2B.'
                ),
                $suggestedGroupId,
                false
            );
        }

        if ($cnaeProfile === CnaeClassifier::PROFILE_OFF) {
            $cnaeCode = (string) ($this->getAttributeValue($customer, 'b2b_cnae_code') ?? '');

            return new ApprovalScoreResult(
                ApprovalScoreResultInterface::SCORE_RED,
                (string) __(
                    'CNAE fora do perfil AWA (%1). Cadastro mantido pendente para avaliação comercial.',
                    $cnaeCode !== '' ? $cnaeCode : __('não classificado')
                ),
                $suggestedGroupId,
                false
            );
        }

        return new ApprovalScoreResult(
            ApprovalScoreResultInterface::SCORE_YELLOW,
            (string) __('CNAE não classificado. Enviado para análise manual.'),
            $suggestedGroupId,
            false
        );
    }

    public function persistScore(int $customerId, ApprovalScoreResultInterface $result): void
    {
        try {
            $customer = $this->customerRepository->getById($customerId);
            $customer->setCustomAttribute('b2b_approval_score', $result->getScore());
            $customer->setCustomAttribute('b2b_approval_score_reason', $result->getReason());
            $customer->setCustomAttribute('b2b_suggested_group_id', (string) $result->getSuggestedGroupId());
            $this->customerRepository->save($customer);
        } catch (\Exception $e) {
            $this->logger->error('B2B persistScore error: ' . $e->getMessage(), ['customer_id' => $customerId]);
        }
    }

    public function processRegistration(int $customerId): ApprovalScoreResultInterface
    {
        $result = $this->evaluate($customerId);
        $this->persistScore($customerId, $result);

        $currentStatus = $this->customerApproval->getApprovalStatus($customerId);
        if ($currentStatus === ApprovalStatus::STATUS_APPROVED) {
            $this->logger->info(sprintf(
                'B2B Score: Cliente #%d já aprovado (grupo automático). Score=%s',
                $customerId,
                $result->getScore()
            ));

            return $result;
        }

        if ($result->shouldAutoApprove()) {
            $approved = $this->customerApproval->approveCustomer(
                $customerId,
                null,
                $result->getReason()
            );

            if ($approved) {
                $this->logger->info(sprintf(
                    'B2B Score: Cliente #%d auto-aprovado (score verde).',
                    $customerId
                ));
            }
        } else {
            $this->logger->info(sprintf(
                'B2B Score: Cliente #%d triagem concluída — score=%s. Motivo: %s',
                $customerId,
                $result->getScore(),
                $result->getReason()
            ));
        }

        return $result;
    }

    /**
     * @return list<string>
     */
    private function getMissingRequiredFields(CustomerInterface $customer): array
    {
        $missing = [];

        $requiredAttributes = [
            'b2b_cnpj' => (string) __('CNPJ'),
            'b2b_razao_social' => (string) __('Razão Social'),
            'b2b_phone' => (string) __('Telefone'),
        ];

        foreach ($requiredAttributes as $code => $label) {
            $value = trim((string) ($this->getAttributeValue($customer, $code) ?? ''));
            if ($value === '') {
                $missing[] = $label;
            }
        }

        if (trim($customer->getFirstname()) === '') {
            $missing[] = (string) __('Nome');
        }

        if (trim($customer->getLastname()) === '') {
            $missing[] = (string) __('Sobrenome');
        }

        if (trim((string) $customer->getEmail()) === '') {
            $missing[] = (string) __('E-mail');
        }

        if (!$this->hasValidAddress($customer)) {
            $missing[] = (string) __('Endereço completo');
        }

        return $missing;
    }

    private function hasValidAddress(CustomerInterface $customer): bool
    {
        $defaultBilling = $customer->getDefaultBilling();
        if ($defaultBilling === null) {
            return false;
        }

        try {
            $address = $this->addressRepository->getById((int) $defaultBilling);
        } catch (\Exception) {
            return false;
        }

        $street = implode(' ', $address->getStreet());
        if (trim($street) === '') {
            return false;
        }

        if (trim((string) $address->getCity()) === '') {
            return false;
        }

        if (trim((string) $address->getPostcode()) === '') {
            return false;
        }

        return $address->getRegionId() !== null || trim((string) $address->getRegion()) !== '';
    }

    private function getCnpjStatusIssue(string $cnpj): ?string
    {
        $apiResult = $this->cnpjValidator->validateApi($cnpj);
        if ($apiResult === null) {
            return null;
        }

        if (($apiResult['valid'] ?? false) !== true) {
            return (string) __('CNPJ não validado na Receita Federal: %1', $apiResult['message'] ?? '');
        }

        $data = $apiResult['data'] ?? [];
        $status = strtoupper((string) ($data['situacao'] ?? $data['descricao_situacao_cadastral'] ?? ''));

        if ($status !== '' && !in_array($status, ['ATIVA', 'ACTIVE', '2'], true)) {
            return (string) __('CNPJ com situação cadastral irregular: %1.', $status);
        }

        return null;
    }

    private function resolveSuggestedGroupId(string $cnaeProfile): int
    {
        if ($cnaeProfile === CnaeClassifier::PROFILE_DIRECT) {
            $groupId = $this->config->getDirectProfileGroupId();
            if ($groupId > 0) {
                return $groupId;
            }
        }

        if ($cnaeProfile === CnaeClassifier::PROFILE_ADJACENT) {
            $groupId = $this->config->getAdjacentProfileGroupId();
            if ($groupId > 0) {
                return $groupId;
            }
        }

        return $this->config->getDefaultB2BGroupId();
    }

    private function getAttributeValue(CustomerInterface $customer, string $code): ?string
    {
        $attribute = $customer->getCustomAttribute($code);

        return $attribute !== null ? (string) $attribute->getValue() : null;
    }
}
