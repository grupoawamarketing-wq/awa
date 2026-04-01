<?php

/**
 * Plugin que permite login com CNPJ — resolve CNPJ para o e-mail do cliente.
 */

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Plugin\Customer;

use GrupoAwamotos\B2B\Helper\CnpjValidator;
use Magento\Customer\Api\AccountManagementInterface;
use Magento\Customer\Model\ResourceModel\Customer\CollectionFactory as CustomerCollectionFactory;
use Psr\Log\LoggerInterface;

class ResolveCnpjLoginPlugin
{
    private CnpjValidator $cnpjValidator;
    private CustomerCollectionFactory $customerCollectionFactory;
    private LoggerInterface $logger;

    public function __construct(
        CnpjValidator $cnpjValidator,
        CustomerCollectionFactory $customerCollectionFactory,
        LoggerInterface $logger
    ) {
        $this->cnpjValidator = $cnpjValidator;
        $this->customerCollectionFactory = $customerCollectionFactory;
        $this->logger = $logger;
    }

    /**
     * Before authenticate: if username looks like a CNPJ, resolve it to email.
     *
     * @param AccountManagementInterface $subject
     * @param string $username
     * @param string $password
     * @return array
     */
    public function beforeAuthenticate(
        AccountManagementInterface $subject,
        $username,
        $password
    ): array {
        $username = trim((string) $username);

        // Strip formatting: "12.345.678/0001-90" → "12345678000190"
        $digits = (string) preg_replace('/\D/', '', $username);

        // Only act if it looks like a CNPJ (14 digits, not a valid email)
        if (strlen($digits) !== 14 || strpos($username, '@') !== false) {
            return [$username, $password];
        }

        // Validate locally to avoid spurious lookups
        if (!$this->cnpjValidator->validateLocal($digits)) {
            return [$username, $password];
        }

        $email = $this->findEmailByCnpj($digits);

        if ($email !== null) {
            $this->logger->info(sprintf(
                '[B2B][Login] CNPJ login resolved for %s',
                substr($digits, 0, 2) . '******' . substr($digits, -4)
            ));
            return [$email, $password];
        }

        // CNPJ not found — return as-is (Magento will show "Invalid login" naturally)
        return [$username, $password];
    }

    private function findEmailByCnpj(string $cnpjDigits): ?string
    {
        $formattedCnpj = $this->cnpjValidator->format($cnpjDigits);

        $collection = $this->customerCollectionFactory->create();
        $collection->addAttributeToSelect(['email', 'b2b_cnpj']);
        $collection->addAttributeToFilter(
            [
                ['attribute' => 'b2b_cnpj', 'eq' => $formattedCnpj],
                ['attribute' => 'b2b_cnpj', 'eq' => $cnpjDigits]
            ]
        );
        $collection->setPageSize(1);

        $customer = $collection->getFirstItem();
        if (!$customer || !$customer->getId()) {
            return null;
        }

        $email = trim((string) $customer->getData('email'));
        return $email !== '' ? $email : null;
    }
}
