<?php

declare(strict_types=1);

namespace GrupoAwamotos\ERPIntegration\Model\Api;

use GrupoAwamotos\ERPIntegration\Api\RfmAnalysisInterface;
use GrupoAwamotos\ERPIntegration\Model\Rfm\Calculator as RfmCalculator;
use GrupoAwamotos\ERPIntegration\Helper\Data as Helper;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\AuthorizationException;

/**
 * RFM Analysis API Implementation
 */
class RfmAnalysisManagement implements RfmAnalysisInterface
{
    private RfmCalculator $rfmCalculator;
    private CustomerSession $customerSession;
    private Helper $helper;

    public function __construct(
        RfmCalculator $rfmCalculator,
        CustomerSession $customerSession,
        Helper $helper
    ) {
        $this->rfmCalculator = $rfmCalculator;
        $this->customerSession = $customerSession;
        $this->helper = $helper;
    }

    /**
     * @inheritdoc
     */
    public function getCurrentCustomerRfm(): array
    {
        $this->validateEnabled();
        $this->validateLoggedIn();

        $customerCode = $this->getCustomerCode();
        if (!$customerCode) {
            return ['error' => true, 'message' => 'Customer not linked to ERP'];
        }

        return $this->rfmCalculator->getCustomerRfm($customerCode);
    }

    /**
     * @inheritdoc
     */
    public function getCustomerRfm(int $customerId): array
    {
        $this->validateEnabled();

        return $this->rfmCalculator->getCustomerRfm($customerId);
    }

    /**
     * @inheritdoc
     */
    public function getSegmentStats(): array
    {
        $this->validateEnabled();

        return $this->rfmCalculator->getSegmentStats();
    }

    /**
     * @inheritdoc
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\AuthorizationException
     */
    public function getAtRiskCustomers(int $limit = 50): array
    {
        $this->validateEnabled();

        return $this->rfmCalculator->getAtRiskCustomers($limit);
    }

    /**
     * Validate feature is enabled
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    private function validateEnabled(): void
    {
        if (!$this->helper->isEnabled() || !$this->helper->isRfmEnabled()) {
            throw new LocalizedException(__('RFM Analysis feature is disabled'));
        }
    }

    /**
     * Validate customer is logged in
     *
     * @throws \Magento\Framework\Exception\AuthorizationException
     */
    private function validateLoggedIn(): void
    {
        if (!$this->customerSession->isLoggedIn()) {
            throw new AuthorizationException(__('Customer must be logged in'));
        }
    }

    /**
     * Get customer ERP code
     */
    private function getCustomerCode(): ?int
    {
        $customer = $this->customerSession->getCustomer();
        if (!$customer || !$customer->getId()) {
            return null;
        }

        $erpCode = $customer->getData('erp_customer_code');
        if ($erpCode) {
            return (int) $erpCode;
        }

        return (int) $customer->getId();
    }
}
