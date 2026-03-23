<?php
/**
 * Plugin para salvar Order Notes no Quote via checkout
 * P2-4.2: Order Notes
 */
declare(strict_types=1);

namespace GrupoAwamotos\B2B\Plugin\Checkout;

use Magento\Checkout\Api\PaymentInformationManagementInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\PaymentInterface;
use Magento\Quote\Api\Data\AddressInterface;
use Psr\Log\LoggerInterface;

class SaveOrderNotesPlugin
{
    private CartRepositoryInterface $cartRepository;
    private LoggerInterface $logger;

    public function __construct(
        CartRepositoryInterface $cartRepository,
        LoggerInterface $logger
    ) {
        $this->cartRepository = $cartRepository;
        $this->logger = $logger;
    }

    /**
     * Before save payment info — extract Order Notes from extension attributes
     *
     * @param PaymentInformationManagementInterface $subject
     * @param int $cartId
     * @param PaymentInterface $paymentMethod
     * @param AddressInterface|null $billingAddress
     * @return array
     */
    public function beforeSavePaymentInformationAndPlaceOrder(
        PaymentInformationManagementInterface $subject,
        $cartId,
        PaymentInterface $paymentMethod,
        ?AddressInterface $billingAddress = null
    ): array {
        $this->extractAndSaveOrderNotes((int) $cartId, $paymentMethod);

        return [$cartId, $paymentMethod, $billingAddress];
    }

    /**
     * Before save payment info (without place order)
     *
     * @param PaymentInformationManagementInterface $subject
     * @param int $cartId
     * @param PaymentInterface $paymentMethod
     * @param AddressInterface|null $billingAddress
     * @return array
     */
    public function beforeSavePaymentInformation(
        PaymentInformationManagementInterface $subject,
        $cartId,
        PaymentInterface $paymentMethod,
        ?AddressInterface $billingAddress = null
    ): array {
        $this->extractAndSaveOrderNotes((int) $cartId, $paymentMethod);

        return [$cartId, $paymentMethod, $billingAddress];
    }

    /**
     * Extract Order Notes from payment extension attributes and save to quote
     */
    private function extractAndSaveOrderNotes(int $cartId, PaymentInterface $paymentMethod): void
    {
        try {
            $extensionAttributes = $paymentMethod->getExtensionAttributes();
            if ($extensionAttributes === null) {
                return;
            }

            $orderNotes = $this->sanitizeOrderNotes($extensionAttributes->getB2bOrderNotes());
            if ($orderNotes === null) {
                return;
            }

            $quote = $this->cartRepository->getActive($cartId);
            $quote->setData('b2b_order_notes', $orderNotes);
            $this->cartRepository->save($quote);

            $this->logger->info('[B2B] Order Notes salvo no quote', [
                'quote_id' => $cartId,
            ]);
        } catch (\Exception $e) {
            $this->logger->error('[B2B] Erro ao salvar Order Notes: ' . $e->getMessage());
        }
    }

    /**
     * Sanitize Order Notes: trim, limit length, strip dangerous content
     */
    private function sanitizeOrderNotes(mixed $notes): ?string
    {
        if ($notes === null || $notes === '') {
            return null;
        }

        $notes = trim((string) $notes);

        if ($notes === '') {
            return null;
        }

        // Strip HTML tags for security
        $notes = strip_tags($notes);

        // Limit to 500 chars (matches db_schema.xml varchar(500))
        if (mb_strlen($notes) > 500) {
            $this->logger->warning('[B2B] Order Notes truncated — exceeded 500 chars');
            $notes = mb_substr($notes, 0, 500);
        }

        return $notes;
    }
}
