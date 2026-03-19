<?php
/**
 * Block order placement for non-approved logged-in customers and enforce minimum order amount.
 * Intercepts the REST/GraphQL payment+placeOrder endpoint.
 */
declare(strict_types=1);

namespace GrupoAwamotos\B2B\Plugin\Checkout;

use GrupoAwamotos\B2B\Helper\Config;
use GrupoAwamotos\B2B\Model\CheckoutAccessValidator;
use GrupoAwamotos\B2B\Model\CreditService;
use GrupoAwamotos\ERPIntegration\Model\ResourceModel\SyncLog as SyncLogResource;
use Magento\Checkout\Api\PaymentInformationManagementInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\AddressInterface;
use Magento\Quote\Api\Data\PaymentInterface;
use Psr\Log\LoggerInterface;

class BlockPlaceOrderPlugin
{
    /**
     * @var Config
     */
    private $config;

    /**
     * @var CartRepositoryInterface
     */
    private $cartRepository;

    /**
     * @var CustomerSession
     */
    private $customerSession;

    /**
     * @var CustomerRepositoryInterface
     */
    private $customerRepository;

    /**
     * @var SyncLogResource
     */
    private $syncLogResource;

    /**
     * @var CreditService
     */
    private $creditService;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var CheckoutAccessValidator
     */
    private $checkoutAccessValidator;

    public function __construct(
        Config $config,
        CartRepositoryInterface $cartRepository,
        CustomerSession $customerSession,
        CustomerRepositoryInterface $customerRepository,
        SyncLogResource $syncLogResource,
        CreditService $creditService,
        CheckoutAccessValidator $checkoutAccessValidator,
        ?LoggerInterface $logger = null
    ) {
        $this->config = $config;
        $this->cartRepository = $cartRepository;
        $this->customerSession = $customerSession;
        $this->customerRepository = $customerRepository;
        $this->syncLogResource = $syncLogResource;
        $this->creditService = $creditService;
        $this->checkoutAccessValidator = $checkoutAccessValidator;
        $this->logger = $logger;
    }

    /**
     * Before savePaymentInformationAndPlaceOrder - block if user is not approved or below minimum
     *
     * @param PaymentInformationManagementInterface $subject
     * @param int $cartId
     * @param PaymentInterface $paymentMethod
     * @param AddressInterface|null $billingAddress
     * @return array
     * @throws CouldNotSaveException
     */
    public function beforeSavePaymentInformationAndPlaceOrder(
        PaymentInformationManagementInterface $subject,
        $cartId,
        PaymentInterface $paymentMethod,
        ?AddressInterface $billingAddress = null
    ): array {
        $this->validateCheckoutAccess((int) $cartId, $paymentMethod);

        return [$cartId, $paymentMethod, $billingAddress];
    }

    /**
     * Before savePaymentInformation - block unauthorized API checkout progression as early as possible.
     *
     * @param PaymentInformationManagementInterface $subject
     * @param int $cartId
     * @param PaymentInterface $paymentMethod
     * @param AddressInterface|null $billingAddress
     * @return array
     * @throws CouldNotSaveException
     */
    public function beforeSavePaymentInformation(
        PaymentInformationManagementInterface $subject,
        $cartId,
        PaymentInterface $paymentMethod,
        ?AddressInterface $billingAddress = null
    ): array {
        $this->validateCheckoutAccess((int) $cartId, $paymentMethod);

        return [$cartId, $paymentMethod, $billingAddress];
    }

    /**
     * Validate whether the current customer can continue through checkout APIs.
     *
     * @throws CouldNotSaveException
     */
    private function validateCheckoutAccess(int $cartId, PaymentInterface $paymentMethod): void
    {
        if (!$this->config->isEnabled()) {
            return;
        }

        /** @var \Magento\Quote\Model\Quote $quote */
        $quote = $this->cartRepository->getActive($cartId);
        $customerId = (int) $quote->getCustomerId();

        if ($customerId <= 0 && $this->customerSession->isLoggedIn()) {
            $customerId = (int) $this->customerSession->getCustomerId();
        }

        $customerState = $this->checkoutAccessValidator->resolveCustomerState($customerId);

        if ($customerState !== CheckoutAccessValidator::STATE_APPROVED) {
            if ($customerState === CheckoutAccessValidator::STATE_PENDING_ERP) {
                throw new CouldNotSaveException(
                    __('Seu cadastro ainda não está vinculado ao sistema ERP. Entre em contato com o departamento comercial para liberar seus pedidos.')
                );
            }

            throw new CouldNotSaveException(
                __('Sua conta precisa ser aprovada antes de realizar compras. Por favor, aguarde a aprovação.')
            );
        }

        // Validate ERP customer code exists (required for ERP order sync)
        if ($customerId > 0) {
            $erpCode = $this->getCustomerErpCode($customerId);
            if (!$erpCode) {
                throw new CouldNotSaveException(
                    __('Seu cadastro ainda não está vinculado ao sistema ERP. Entre em contato com o departamento comercial para liberar seus pedidos.')
                );
            }
        }

        // Validate B2B credit sufficiency when paying with b2b_credit
        if ($customerId > 0 && $paymentMethod->getMethod() === 'b2b_credit') {
            try {
                $grandTotal = (float) $quote->getBaseGrandTotal();

                if (!$this->creditService->hasSufficientCredit($customerId, $grandTotal)) {
                    throw new CouldNotSaveException(
                        __('Crédito B2B insuficiente para este pedido. Verifique seu limite disponível ou escolha outra forma de pagamento.')
                    );
                }
            } catch (CouldNotSaveException $e) {
                throw $e;
            } catch (\Exception $e) {
                if ($this->logger) {
                    $this->logger->error('[B2B] Credit check failed: ' . $e->getMessage(), ['exception' => $e]);
                }
            }
        }

        // Enforce minimum order amount
        if ($this->config->isMinQtyEnabled()) {
            $minAmount = $this->config->getMinOrderAmount();
            if ($minAmount > 0) {
                try {
                    $subtotal = (float) $quote->getBaseSubtotal();

                    if ($subtotal < $minAmount) {
                        throw new CouldNotSaveException(
                            __($this->config->getMinOrderMessage())
                        );
                    }
                } catch (CouldNotSaveException $e) {
                    throw $e;
                } catch (\Exception $e) {
                    // If we can't load the quote, allow the order to proceed
                    if ($this->logger) {
                        $this->logger->debug('[B2B] Exception: ' . $e->getMessage(), ['exception' => $e]);
                    }
                }
            }
        }
    }

    /**
     * Get customer ERP code from attribute or entity_map fallback
     */
    private function getCustomerErpCode(int $customerId): ?int
    {
        try {
            $customer = $this->customerRepository->getById($customerId);
            $attr = $customer->getCustomAttribute('erp_code');
            $erpCode = ($attr && $attr->getValue()) ? $attr->getValue() : null;

            if ($erpCode === null) {
                $erpCode = $this->syncLogResource->getErpCodeByMagentoId('customer', $customerId);
            }

            return ($erpCode !== null && is_numeric($erpCode)) ? (int) $erpCode : null;
        } catch (\Exception $e) {
            return null;
        }
    }
}
