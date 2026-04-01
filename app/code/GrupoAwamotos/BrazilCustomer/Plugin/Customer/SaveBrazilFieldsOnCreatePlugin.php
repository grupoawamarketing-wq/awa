<?php

declare(strict_types=1);

namespace GrupoAwamotos\BrazilCustomer\Plugin\Customer;

use Magento\Customer\Api\AccountManagementInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Framework\App\RequestInterface;
use Psr\Log\LoggerInterface;

/**
 * Persists Brazilian custom attributes on customer registration
 */
class SaveBrazilFieldsOnCreatePlugin
{
    private const BRAZIL_ATTRIBUTES = [
        'person_type',
        'cpf',
        'rg',
        'cnpj',
        'ie',
        'company_name',
        'trade_name',
    ];

    private RequestInterface $request;
    private LoggerInterface $logger;

    public function __construct(
        RequestInterface $request,
        LoggerInterface $logger
    ) {
        $this->request = $request;
        $this->logger = $logger;
    }

    /**
     * Before creating account, set Brazil attributes from POST data
     */
    public function beforeCreateAccount(
        AccountManagementInterface $subject,
        CustomerInterface $customer,
        $password = null,
        $redirectUrl = ''
    ): array {
        try {
            foreach (self::BRAZIL_ATTRIBUTES as $attributeCode) {
                $value = $this->request->getParam($attributeCode);
                if ($value !== null && $value !== '') {
                    $customer->setCustomAttribute($attributeCode, $value);
                }
            }

            // Sync CPF/CNPJ to taxvat for ERP compatibility
            $personType = $this->request->getParam('person_type', 'pf');
            if ($personType === 'pj') {
                $cnpj = $this->request->getParam('cnpj');
                if ($cnpj) {
                    $customer->setTaxvat(preg_replace('/[^0-9]/', '', $cnpj));
                }
            } else {
                $cpf = $this->request->getParam('cpf');
                if ($cpf) {
                    $customer->setTaxvat(preg_replace('/[^0-9]/', '', $cpf));
                }
            }
        } catch (\Exception $e) {
            $this->logger->error('[BrazilCustomer] Error setting registration fields: ' . $e->getMessage());
        }

        return [$customer, $password, $redirectUrl];
    }
}
