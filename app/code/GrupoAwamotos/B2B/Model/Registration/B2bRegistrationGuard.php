<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Model\Registration;

use GrupoAwamotos\B2B\Model\Customer\Attribute\Source\ErpCustomerSyncStatus;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Framework\Exception\InputException;

/**
 * Guards new B2B customer registrations against incomplete fiscal/contact data.
 * Does not enforce rules on existing legacy customers (no overwrite).
 */
class B2bRegistrationGuard
{
    public const DEFAULT_CAMPAIGN = 'direct_b2b_register';
    public const DEFAULT_ORIGIN_FALLBACK = 'awamotos.com';

    /** @var list<int> */
    public const KNOWN_TEST_QA_IDS = [2, 8714];

    public function __construct(
        private readonly B2bPhoneNormalizer $phoneNormalizer
    ) {
    }

    public function isB2bCustomer(CustomerInterface $customer): bool
    {
        $cnpj = $this->getAttributeValue($customer, 'b2b_cnpj');
        if ($cnpj !== '') {
            return true;
        }

        $personType = strtolower($this->getAttributeValue($customer, 'b2b_person_type'));

        return $personType === 'pj';
    }

    public function isNewCustomer(CustomerInterface $customer): bool
    {
        return !(int) $customer->getId();
    }

    /**
     * Validates mandatory fields for a brand-new B2B registration.
     *
     * @throws InputException
     */
    public function validateNewRegistration(CustomerInterface $customer): void
    {
        if (!$this->isB2bCustomer($customer)) {
            return;
        }

        $cnpj = preg_replace('/\D+/', '', $this->getAttributeValue($customer, 'b2b_cnpj'));
        if (strlen($cnpj) !== 14) {
            throw new InputException(__('CNPJ é obrigatório para cadastro B2B.'));
        }

        $razao = trim($this->getAttributeValue($customer, 'b2b_razao_social'));
        if ($razao === '' || mb_strlen($razao) < 3) {
            throw new InputException(
                __('Razão social é obrigatória. Informe manualmente ou valide o CNPJ para preenchimento automático.')
            );
        }

        $phoneRaw = $this->getAttributeValue($customer, 'b2b_phone');
        if (!$this->phoneNormalizer->isValidBrazilianPhone($phoneRaw)) {
            throw new InputException(
                __('Telefone comercial é obrigatório e deve conter DDD válido (10 ou 11 dígitos).')
            );
        }

        $erpStatus = trim($this->getAttributeValue($customer, 'erp_customer_sync_status'));
        if ($erpStatus === '') {
            throw new InputException(__('Status ERP inicial é obrigatório para cadastro B2B.'));
        }

        $origin = trim($this->getAttributeValue($customer, 'b2b_origin_host'));
        if ($origin === '') {
            throw new InputException(__('Origem B2B (b2b_origin_host) é obrigatória para cadastro B2B.'));
        }

        $campaign = trim($this->getAttributeValue($customer, 'b2b_registration_campaign'));
        if ($campaign === '') {
            throw new InputException(__('Campanha B2B (b2b_registration_campaign) é obrigatória para cadastro B2B.'));
        }
    }

    /**
     * Applies safe defaults for new registrations without inventing phone or razão social.
     */
    public function applyNewRegistrationDefaults(CustomerInterface $customer, ?string $originHost = null): void
    {
        if (!$this->isB2bCustomer($customer)) {
            return;
        }

        $phone = $this->getAttributeValue($customer, 'b2b_phone');
        if ($phone !== '' && $this->phoneNormalizer->isValidBrazilianPhone($phone)) {
            $customer->setCustomAttribute('b2b_phone', $this->phoneNormalizer->normalize($phone));
        }

        if (trim($this->getAttributeValue($customer, 'erp_customer_sync_status')) === '') {
            $customer->setCustomAttribute(
                'erp_customer_sync_status',
                ErpCustomerSyncStatus::CUSTOMER_PENDING_ERP_VALIDATION
            );
        }

        $origin = trim($this->getAttributeValue($customer, 'b2b_origin_host'));
        if ($origin === '') {
            $host = trim((string) ($originHost ?? ''));
            $customer->setCustomAttribute(
                'b2b_origin_host',
                $host !== '' ? $host : self::DEFAULT_ORIGIN_FALLBACK
            );
        }

        if (trim($this->getAttributeValue($customer, 'b2b_registration_campaign')) === '') {
            $customer->setCustomAttribute('b2b_registration_campaign', self::DEFAULT_CAMPAIGN);
        }
    }

    public function resolveInitialErpStatus(bool $foundInErp = false): string
    {
        return $foundInErp
            ? ErpCustomerSyncStatus::CUSTOMER_VALIDATED_IN_ERP
            : ErpCustomerSyncStatus::CUSTOMER_PENDING_ERP_VALIDATION;
    }

    public function isTestOrQaAccount(int $customerId, string $email = '', string $adminNotes = ''): bool
    {
        if (in_array($customerId, self::KNOWN_TEST_QA_IDS, true)) {
            return true;
        }

        $emailLower = strtolower(trim($email));
        if (str_contains($emailLower, 'qa.b2b.') || str_contains($emailLower, '@jesssestain.com.br')) {
            return true;
        }

        $notesLower = strtolower($adminNotes);

        return str_contains($notesLower, 'conta de teste')
            || str_contains($notesLower, 'conta qa');
    }

    public function getAttributeValue(CustomerInterface $customer, string $code): string
    {
        $attr = $customer->getCustomAttribute($code);

        return $attr !== null ? trim((string) $attr->getValue()) : '';
    }
}
