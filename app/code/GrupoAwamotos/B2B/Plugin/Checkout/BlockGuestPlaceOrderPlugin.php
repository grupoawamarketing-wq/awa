<?php
/**
 * Block guest order placement in strict B2B mode.
 * Intercepts the guest REST/GraphQL payment+placeOrder endpoint.
 */
declare(strict_types=1);

namespace GrupoAwamotos\B2B\Plugin\Checkout;

use GrupoAwamotos\B2B\Helper\Config;
use Magento\Checkout\Api\GuestPaymentInformationManagementInterface;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Quote\Api\Data\AddressInterface;
use Magento\Quote\Api\Data\PaymentInterface;

class BlockGuestPlaceOrderPlugin
{
    /**
     * @var Config
     */
    private $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    /**
     * Before savePaymentInformationAndPlaceOrder - block guests in strict B2B mode
     *
     * @param GuestPaymentInformationManagementInterface $subject
     * @param string $cartId
     * @param string $email
     * @param PaymentInterface $paymentMethod
     * @param AddressInterface|null $billingAddress
     * @return array
     * @throws CouldNotSaveException
     */
    public function beforeSavePaymentInformationAndPlaceOrder(
        GuestPaymentInformationManagementInterface $subject,
        $cartId,
        $email,
        PaymentInterface $paymentMethod,
        ?AddressInterface $billingAddress = null
    ): array {
        $this->assertGuestCheckoutAllowed();

        return [$cartId, $email, $paymentMethod, $billingAddress];
    }

    /**
     * Before savePaymentInformation - block guests before progressing through checkout APIs in strict B2B.
     *
     * @param GuestPaymentInformationManagementInterface $subject
     * @param string $cartId
     * @param string $email
     * @param PaymentInterface $paymentMethod
     * @param AddressInterface|null $billingAddress
     * @return array
     * @throws CouldNotSaveException
     */
    public function beforeSavePaymentInformation(
        GuestPaymentInformationManagementInterface $subject,
        $cartId,
        $email,
        PaymentInterface $paymentMethod,
        ?AddressInterface $billingAddress = null
    ): array {
        $this->assertGuestCheckoutAllowed();

        return [$cartId, $email, $paymentMethod, $billingAddress];
    }

    /**
     * @throws CouldNotSaveException
     */
    private function assertGuestCheckoutAllowed(): void
    {
        if ($this->config->isEnabled() && $this->config->isStrictB2B()) {
            throw new CouldNotSaveException(
                __('Esta loja opera exclusivamente no modo B2B. Cadastre-se como empresa para realizar compras.')
            );
        }
    }
}
