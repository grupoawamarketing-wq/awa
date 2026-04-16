<?php

/**
 * Plugin para aplicar preco B2B dinamico por cliente
 *
 * Prioridade:
 * 1. Cliente com ERP → preco vem da lista ERP do cliente (VLRVDSUG)
 * 2. Cliente sem lista especifica (NACIONAL) ou sem ERP → preco base Magento (VLRVDSUG NACIONAL)
 *
 * O ERP e a autoridade de preco. Nao ha desconto de grupo — todo pricing vem do ERP.
 * Quando o ERP define preco, special_price e catalog rules sao neutralizados
 * para evitar precos conflitantes na PDP.
 */

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Plugin;

use GrupoAwamotos\B2B\Helper\Config;
use GrupoAwamotos\B2B\Model\ErpCodeResolver;
use GrupoAwamotos\ERPIntegration\Model\CustomerPriceProvider;
use GrupoAwamotos\ERPIntegration\Model\ResourceModel\SyncLog as SyncLogResource;
use Magento\Catalog\Model\Product;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class GroupPricePlugin
{
    private Config $config;
    private CustomerSession $customerSession;
    private CustomerRepositoryInterface $customerRepository;
    private CustomerPriceProvider $customerPriceProvider;
    private SyncLogResource $syncLogResource;
    private LoggerInterface $logger;
    private ?ErpCodeResolver $erpCodeResolver;

    /** Cache: productId → computed price */
    private array $processedProducts = [];

    /** Cache: productId → computed final price */
    private array $processedFinalPrices = [];

    /** Customer approval status cache */
    private string|null|false $approvalStatusCache = false;

    /** Customer ERP code cache */
    private int|null|false $erpCodeCache = false;

    public function __construct(
        Config $config,
        CustomerSession $customerSession,
        CustomerRepositoryInterface $customerRepository,
        CustomerPriceProvider $customerPriceProvider,
        SyncLogResource $syncLogResource,
        ?LoggerInterface $logger = null,
        ?ErpCodeResolver $erpCodeResolver = null
    ) {
        $this->config = $config;
        $this->customerSession = $customerSession;
        $this->customerRepository = $customerRepository;
        $this->customerPriceProvider = $customerPriceProvider;
        $this->syncLogResource = $syncLogResource;
        $this->logger = $logger ?? new NullLogger();
        $this->erpCodeResolver = $erpCodeResolver;
    }

    /**
     * Apply B2B pricing to product price
     */
    public function afterGetPrice(Product $subject, $result)
    {
        if (!$this->config->isEnabled() || !$this->customerSession->isLoggedIn()) {
            return $result;
        }

        $productId = $subject->getId();
        if (isset($this->processedProducts[$productId])) {
            return $this->processedProducts[$productId];
        }

        $price = $this->computeB2BPrice($subject, (float) $result);
        $this->processedProducts[$productId] = $price;

        return $price;
    }

    /**
     * Apply B2B pricing to final price
     */
    public function afterGetFinalPrice(Product $subject, $result)
    {
        if (!$this->config->isEnabled() || !$this->customerSession->isLoggedIn()) {
            return $result;
        }

        $productId = $subject->getId();
        if (isset($this->processedFinalPrices[$productId])) {
            return $this->processedFinalPrices[$productId];
        }

        $price = $this->computeB2BPrice($subject, (float) $result);
        $this->processedFinalPrices[$productId] = $price;

        return $price;
    }

    /**
     * Compute B2B price — ERP is the sole pricing authority
     *
     * - Customer with ERP code + specific list → ERP price (VLRVDSUG)
     * - Customer with ERP code + NACIONAL list → base price (VLRVDSUG NACIONAL, already synced)
     * - Customer without ERP code (new, pending ERP registration) → base price (NACIONAL)
     *
     * When ERP price is applied, special_price and catalog_rule_price are cleared
     * from the product data to prevent the Pricing Pool from showing competing prices.
     */
    private function computeB2BPrice(Product $product, float $basePrice): float
    {
        if ($basePrice <= 0) {
            return $basePrice;
        }

        // Must be approved B2B customer
        $approvalStatus = $this->getCustomerApprovalStatus();
        if ($approvalStatus !== 'approved') {
            return $basePrice;
        }

        // Customer has ERP code → price comes from ERP
        $erpCode = $this->getCustomerErpCode();
        if ($erpCode !== null) {
            $sku = $product->getSku();
            $erpPrice = $this->customerPriceProvider->getCustomerPrice($erpCode, $sku);
            if ($erpPrice !== null && $erpPrice > 0) {
                // ERP is the sole pricing authority: neutralize all Magento price overrides
                // so the Pricing Pool only sees the ERP price.
                // special_price and catalog_rule_price are cleared from the data array
                // because getSpecialPrice() is a magic method (__call) that reads from
                // getData(), so after-plugins on it don't fire reliably.
                $product->setData('special_price', null);
                $product->setData('special_from_date', null);
                $product->setData('special_to_date', null);
                $product->setData('catalog_rule_price', null);

                return $erpPrice;
            }
        }

        // No ERP code, or NACIONAL list, or SKU not in customer's list
        // → use base Magento price (VLRVDSUG NACIONAL, already synced from ERP)
        // ERP is the pricing authority — no group discounts applied
        return $basePrice;
    }

    /**
     * Get customer's ERP code.
     * Primary: erp_code customer attribute (single definitive value).
     * Fallback: entity_map table (may have duplicates).
     */
    private function getCustomerErpCode(): ?int
    {
        if ($this->erpCodeCache !== false) {
            return $this->erpCodeCache;
        }

        try {
            $customerId = (int) $this->customerSession->getCustomerId();
            if ($customerId <= 0) {
                return null;
            }
            $customer = $this->customerRepository->getById($customerId);

            if ($this->erpCodeResolver !== null) {
                $this->erpCodeCache = $this->erpCodeResolver->resolveForCustomerId($customerId, $customer);
                return $this->erpCodeCache;
            }

            // Primary: erp_code attribute (definitive, single value)
            $attr = $customer->getCustomAttribute('erp_code');
            $erpCode = ($attr && $attr->getValue()) ? $attr->getValue() : null;

            // Fallback: entity_map table
            if ($erpCode === null) {
                $erpCode = $this->syncLogResource->getErpCodeByMagentoId('customer', $customerId);
            }

            $this->erpCodeCache = ($erpCode !== null && is_numeric($erpCode)) ? (int) $erpCode : null;
        } catch (\Exception $e) {
            $this->logger->error('[B2B GroupPricePlugin] getCustomerErpCode error: ' . $e->getMessage());
            $this->erpCodeCache = null;
        }

        return $this->erpCodeCache;
    }

    /**
     * Get customer approval status via repository
     */
    private function getCustomerApprovalStatus(): ?string
    {
        if ($this->approvalStatusCache !== false) {
            return $this->approvalStatusCache;
        }

        try {
            $customerId = $this->customerSession->getCustomerId();
            if ($customerId <= 0) {
                return null;
            }
            $customer = $this->customerRepository->getById($customerId);
            $attr = $customer->getCustomAttribute('b2b_approval_status');
            $this->approvalStatusCache = $attr ? $attr->getValue() : null;
        } catch (\Exception $e) {
            $this->approvalStatusCache = null;
        }

        return $this->approvalStatusCache;
    }
}
