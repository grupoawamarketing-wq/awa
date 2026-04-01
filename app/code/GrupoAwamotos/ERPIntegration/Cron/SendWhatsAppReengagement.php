<?php

declare(strict_types=1);

namespace GrupoAwamotos\ERPIntegration\Cron;

use GrupoAwamotos\ERPIntegration\Model\Rfm\Calculator as RfmCalculator;
use GrupoAwamotos\ERPIntegration\Model\Coupon\Generator as CouponGenerator;
use GrupoAwamotos\ERPIntegration\Model\WhatsApp\Client as WhatsAppClient;
use GrupoAwamotos\ERPIntegration\Helper\Data as Helper;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Psr\Log\LoggerInterface;

/**
 * Cron Job - Send WhatsApp Re-engagement Messages
 *
 * Sends WhatsApp messages with coupons to at-risk customers
 * Runs weekly on Wednesdays (after email campaign on Tuesday)
 */
class SendWhatsAppReengagement
{
    private RfmCalculator $rfmCalculator;
    private CouponGenerator $couponGenerator;
    private WhatsAppClient $whatsAppClient;
    private CustomerRepositoryInterface $customerRepository;
    private Helper $helper;
    private LoggerInterface $logger;

    public function __construct(
        RfmCalculator $rfmCalculator,
        CouponGenerator $couponGenerator,
        WhatsAppClient $whatsAppClient,
        CustomerRepositoryInterface $customerRepository,
        Helper $helper,
        LoggerInterface $logger
    ) {
        $this->rfmCalculator = $rfmCalculator;
        $this->couponGenerator = $couponGenerator;
        $this->whatsAppClient = $whatsAppClient;
        $this->customerRepository = $customerRepository;
        $this->helper = $helper;
        $this->logger = $logger;
    }

    /**
     * Execute cron job
     */
    public function execute(): void
    {
        if (!$this->helper->isWhatsAppReengagementEnabled()) {
            return;
        }

        if (!$this->whatsAppClient->isConfigured()) {
            $this->logger->warning('[WhatsApp Cron] WhatsApp API not configured');
            return;
        }

        $this->logger->info('[WhatsApp Cron] Starting re-engagement campaign...');

        try {
            // Get at-risk customers (different batch than email to avoid overlap)
            $atRiskCustomers = $this->rfmCalculator->getAtRiskCustomers(15);

            if (empty($atRiskCustomers)) {
                $this->logger->info('[WhatsApp Cron] No at-risk customers found.');
                return;
            }

            $sentCount = 0;
            $errorCount = 0;
            $validDays = $this->helper->getCouponValidDays();

            foreach ($atRiskCustomers as $customerData) {
                try {
                    // Get phone number
                    $phoneNumber = $this->getCustomerPhone($customerData);

                    if (!$phoneNumber) {
                        continue;
                    }

                    // Get Magento customer ID
                    $magentoCustomerId = $this->findMagentoCustomerId($customerData);

                    if (!$magentoCustomerId) {
                        continue;
                    }

                    $customer = $this->customerRepository->getById($magentoCustomerId);
                    $segment = $customerData['segment'] ?? 'at_risk';

                    // Generate coupon (reuse if already generated for email)
                    $coupon = $this->couponGenerator->generateForCustomer(
                        $magentoCustomerId,
                        $segment,
                        null,
                        $validDays
                    );

                    if (!$coupon) {
                        continue;
                    }

                    // Send WhatsApp message
                    $result = $this->whatsAppClient->sendReengagementMessage(
                        $phoneNumber,
                        $customer->getFirstname(),
                        $coupon['coupon_code'],
                        $coupon['discount_percent'],
                        $validDays
                    );

                    if ($result) {
                        $sentCount++;
                    } else {
                        $errorCount++;
                    }

                    // Rate limiting: WhatsApp has limits on messages per second
                    usleep(1000000); // 1 second delay between messages
                } catch (\Exception $e) {
                    $errorCount++;
                    $this->logger->error(sprintf(
                        '[WhatsApp Cron] Error processing customer: %s',
                        $e->getMessage()
                    ));
                }
            }

            $this->logger->info(sprintf(
                '[WhatsApp Cron] Campaign completed: %d sent, %d errors',
                $sentCount,
                $errorCount
            ));
        } catch (\Exception $e) {
            $this->logger->error('[WhatsApp Cron] Campaign error: ' . $e->getMessage());
        }
    }

    /**
     * Get customer phone number
     */
    private function getCustomerPhone(array $customerData): ?string
    {
        // Try phone from ERP data
        if (!empty($customerData['phone'])) {
            return $customerData['phone'];
        }

        if (!empty($customerData['celular'])) {
            return $customerData['celular'];
        }

        // Try to get from Magento customer
        $magentoId = $this->findMagentoCustomerId($customerData);

        if ($magentoId) {
            try {
                $customer = $this->customerRepository->getById($magentoId);

                // Check custom attribute for phone
                $phoneAttr = $customer->getCustomAttribute('telephone');
                if ($phoneAttr && $phoneAttr->getValue()) {
                    return $phoneAttr->getValue();
                }

                $mobileAttr = $customer->getCustomAttribute('mobile');
                if ($mobileAttr && $mobileAttr->getValue()) {
                    return $mobileAttr->getValue();
                }
            } catch (\Exception $e) {
                // Ignore
            }
        }

        return null;
    }

    /**
     * Find Magento customer ID from ERP customer data
     */
    private function findMagentoCustomerId(array $customerData): ?int
    {
        if (!empty($customerData['magento_customer_id'])) {
            return (int) $customerData['magento_customer_id'];
        }

        if (!empty($customerData['email'])) {
            try {
                $customer = $this->customerRepository->get($customerData['email']);
                return (int) $customer->getId();
            } catch (\Exception $e) {
                // Customer not found
            }
        }

        if (!empty($customerData['customer_code'])) {
            try {
                $customer = $this->customerRepository->getById((int) $customerData['customer_code']);
                return (int) $customer->getId();
            } catch (\Exception $e) {
                // Customer not found
            }
        }

        return null;
    }
}
