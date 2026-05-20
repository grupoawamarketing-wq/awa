<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Plugin\Customer;

use GrupoAwamotos\B2B\Model\Registration\B2bRegistrationGuard;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Framework\Exception\InputException;

/**
 * Enforces B2B registration completeness on new customer saves only.
 * Existing legacy customers are never blocked or overwritten.
 */
class ValidateB2bCustomerOnSavePlugin
{
    public function __construct(
        private readonly B2bRegistrationGuard $registrationGuard
    ) {
    }

    /**
     * @throws InputException
     */
    public function beforeSave(
        CustomerRepositoryInterface $subject,
        CustomerInterface $customer,
        $passwordHash = null
    ): array {
        if (!$this->registrationGuard->isB2bCustomer($customer)) {
            return [$customer, $passwordHash];
        }

        if (!$this->registrationGuard->isNewCustomer($customer)) {
            return [$customer, $passwordHash];
        }

        $this->registrationGuard->applyNewRegistrationDefaults($customer);
        $this->registrationGuard->validateNewRegistration($customer);

        return [$customer, $passwordHash];
    }
}
