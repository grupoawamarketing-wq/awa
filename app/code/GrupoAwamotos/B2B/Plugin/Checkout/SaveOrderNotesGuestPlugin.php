<?php
/**
 * Plugin para salvar Order Notes no Quote via guest checkout
 * P2-4.2: Order Notes
 */
declare(strict_types=1);

namespace GrupoAwamotos\B2B\Plugin\Checkout;

use Magento\Checkout\Api\GuestPaymentInformationManagementInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\PaymentInterface;
use Magento\Quote\Api\Data\AddressInterface;
use Magento\Quote\Model\MaskedQuoteIdToQuoteIdInterface;
use Psr\Log\LoggerInterface;

class SaveOrderNotesGuestPlugin
{
    private CartRepositoryInterface $cartRepository;
    private MaskedQuoteIdToQuoteIdInterface $maskedQuoteIdToQuoteId;
    private LoggerInterface $logger;

    public function __construct(
        CartRepositoryInterface $cartRepository,
        MaskedQuoteIdToQuoteIdInterface $maskedQuoteIdToQuoteId,
        LoggerInterface $logger
    ) {
        $this->cartRepository = $cartRepository;
        $this->maskedQuoteIdToQuoteId = $maskedQuoteIdToQuoteId;
        $this->logger = $logger;
    }

    /**
     * Before save payment info and place order — guest
     *
     * @param GuestPaymentInformationManagementInterface $subject
     * @param string $cartId
     * @param string $email
     * @param PaymentInterface $paymentMethod
     * @param AddressInterface|null $billingAddress
     * @return array
     */
    public function beforeSavePaymentInformationAndPlaceOrder(
        GuestPaymentInformationManagementInterface $subject,
        $cartId,
        $email,
        PaymentInterface $paymentMethod,
        ?AddressInterface $billingAddress = null
    ): array {
        $this->extractAndSaveOrderNotes($cartId, $paymentMethod);

        return [$cartId, $email, $paymentMethod, $billingAddress];
    }

    /**
     * Before save payment info (without place order) — guest
     *
     * @param GuestPaymentInformationManagementInterface $subject
     * @param string $cartId
     * @param string $email
     * @param PaymentInterface $paymentMethod
     * @param AddressInterface|null $billingAddress
     * @return array
     */
    public function beforeSavePaymentInformation(
        GuestPaymentInformationManagementInterface $subject,
        $cartId,
        $email,
        PaymentInterface $paymentMethod,
        ?AddressInterface $billingAddress = null
    ): array {
        $this->extractAndSaveOrderNotes($cartId, $paymentMethod);

        return [$cartId, $email, $paymentMethod, $billingAddress];
    }

    /**
     * Extract Order Notes from payment extension attributes and save to quote
     */
    private function extractAndSaveOrderNotes(string $cartId, PaymentInterface $paymentMethod): void
    {
        try {
            $extensionAttributes = $paymentMethod->getExtensionAttributes();
            if ($extensionAttributes === null) {
                return;
            }

            $orderNotes = $extensionAttributes->getB2bOrderNotes();
            if (empty($orderNotes)) {
                return;
            }

            $orderNotes = $this->sanitizeOrderNotes($orderNotes);
            if ($orderNotes === null) {
                return;
            }

            $quoteId = (int) $this->maskedQuoteIdToQuoteId->execute($cartId);

            if ($quoteId === 0) {
                return;
            }

            /** @var \Magento\Quote\Model\Quote $quote */
            $quote = $this->cartRepository->getActive($quoteId);
            $quote->setData('b2b_order_notes', $orderNotes);
            $this->cartRepository->save($quote);

            $this->logger->info('[B2B] Order Notes salvo no quote (guest)', [
                'quote_id' => $quoteId,
            ]);
        } catch (\Exception $e) {
            $this->logger->error('[B2B] Erro ao salvar Order Notes (guest): ' . $e->getMessage());
        }
    }

    /**
     * Sanitize Order Notes: trim, limit length, strip dangerous content.
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

        $notes = strip_tags($notes);

        if (mb_strlen($notes) > 500) {
            $this->logger->warning('[B2B] Order Notes (guest) truncated — exceeded 500 chars');
            $notes = mb_substr($notes, 0, 500);
        }

        return $notes;
    }
}
