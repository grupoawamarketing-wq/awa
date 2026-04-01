<?php

/**
 * Observer: classify new B2B customers by CNAE code on registration
 */

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Observer;

use GrupoAwamotos\B2B\Api\CustomerApprovalInterface;
use GrupoAwamotos\B2B\Helper\CnpjValidator;
use GrupoAwamotos\B2B\Helper\Config;
use GrupoAwamotos\B2B\Model\CnaeClassifier;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Psr\Log\LoggerInterface;

class CnaeClassificationObserver implements ObserverInterface
{
    private Config $config;
    private CnaeClassifier $cnaeClassifier;
    private CustomerRepositoryInterface $customerRepository;
    private CnpjValidator $cnpjValidator;
    private CustomerApprovalInterface $customerApproval;
    private LoggerInterface $logger;

    public function __construct(
        Config $config,
        CnaeClassifier $cnaeClassifier,
        CustomerRepositoryInterface $customerRepository,
        CnpjValidator $cnpjValidator,
        CustomerApprovalInterface $customerApproval,
        LoggerInterface $logger
    ) {
        $this->config = $config;
        $this->cnaeClassifier = $cnaeClassifier;
        $this->customerRepository = $customerRepository;
        $this->cnpjValidator = $cnpjValidator;
        $this->customerApproval = $customerApproval;
        $this->logger = $logger;
    }

    public function execute(Observer $observer)
    {
        if (!$this->config->isCnaeProfilingEnabled()) {
            return;
        }

        try {
            /** @var \Magento\Customer\Model\Customer $customer */
            $customer = $observer->getEvent()->getCustomer();

            if (!$customer || !$customer->getId()) {
                return;
            }

            $customerId = (int) $customer->getId();

            // Load full customer data via repository for attribute access
            $customerData = $this->customerRepository->getById($customerId);
            $cnpj = $this->getCustomerAttributeValue($customerData, 'b2b_cnpj');

            if (empty($cnpj)) {
                return;
            }

            // Strip formatting from CNPJ
            $cnpjDigits = preg_replace('/\D/', '', $cnpj);

            // Get API data - this should be cached from the registration CNPJ validation
            $apiData = $this->cnpjValidator->validateApi($cnpjDigits);

            if ($apiData === null || !isset($apiData['data'])) {
                $this->logger->info(sprintf(
                    'B2B CNAE: No API data available for customer #%d (CNPJ: %s)',
                    $customerId,
                    $cnpjDigits
                ));
                return;
            }

            $rawData = $apiData['data'];
            $cnaeCode = $this->cnaeClassifier->extractCnaeCode($rawData);
            $cnaeDescription = $this->cnaeClassifier->extractCnaeDescription($rawData);

            if (empty($cnaeCode)) {
                $this->logger->info(sprintf(
                    'B2B CNAE: No CNAE code found for customer #%d (CNPJ: %s)',
                    $customerId,
                    $cnpjDigits
                ));
                return;
            }

            // Classify the CNAE
            $profile = $this->cnaeClassifier->classify($cnaeCode);

            // Save CNAE attributes on customer
            $customerData->setCustomAttribute('b2b_cnae_code', $cnaeCode);
            $customerData->setCustomAttribute('b2b_cnae_description', $cnaeDescription);
            $customerData->setCustomAttribute('b2b_cnae_profile', $profile);
            $this->customerRepository->save($customerData);

            $this->logger->info(sprintf(
                'B2B CNAE: Customer #%d classified as "%s" (CNAE: %s - %s)',
                $customerId,
                $this->cnaeClassifier->getProfileLabel($profile),
                $cnaeCode,
                $cnaeDescription
            ));

            // Auto-approve direct-profile customers if configured
            if (
                $profile === CnaeClassifier::PROFILE_DIRECT
                && $this->cnaeClassifier->isAutoApproveDirectEnabled()
            ) {
                $this->customerApproval->approveCustomer(
                    $customerId,
                    null,
                    sprintf('Auto-aprovado por CNAE: %s (%s)', $cnaeCode, $cnaeDescription)
                );

                $this->logger->info(sprintf(
                    'B2B CNAE: Customer #%d auto-approved (direct profile, CNAE: %s)',
                    $customerId,
                    $cnaeCode
                ));
            }
        } catch (\Exception $e) {
            $this->logger->error(
                'B2B CnaeClassificationObserver error: ' . $e->getMessage(),
                ['exception' => $e]
            );
        }
    }

    private function getCustomerAttributeValue(CustomerInterface $customer, string $attributeCode): ?string
    {
        $attribute = $customer->getCustomAttribute($attributeCode);
        return $attribute ? (string) $attribute->getValue() : null;
    }
}
