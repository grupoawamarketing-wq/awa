<?php
/**
 * Plugin para salvar PO Number no Quote via guest checkout
 * P0-1: Purchase Order Number
 */
declare(strict_types=1);

namespace GrupoAwamotos\B2B\Plugin\Checkout;

use Magento\Checkout\Api\GuestPaymentInformationManagementInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\PaymentInterface;
use Magento\Quote\Api\Data\AddressInterface;
use Magento\Quote\Model\MaskedQuoteIdToQuoteIdInterface;
use Psr\Log\LoggerInterface;

class SavePoNumberGuestPlugin
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
     */
    public function beforeSavePaymentInformationAndPlaceOrder(
        GuestPaymentInformationManagementInterface $subject,
        $cartId,
        $email,
        PaymentInterface $paymentMethod,
        ?AddressInterface $billingAddress = null
    ): array {
        $this->extractAndSavePoNumber($cartId, $paymentMethod);
        return [$cartId, $email, $paymentMethod, $billingAddress];
    }

    /**
     * Before save payment info (without place order) — guest
     */
    public function beforeSavePaymentInformation(
        GuestPaymentInformationManagementInterface $subject,
        $cartId,
        $email,
        PaymentInterface $paymentMethod,
        ?AddressInterface $billingAddress = null
    ): array {
        $this->extractAndSavePoNumber($cartId, $paymentMethod);
        return [$cartId, $email, $paymentMethod, $billingAddress];
    }

    /**
     * Extract PO Number from payment extension attributes and save to quote
     */
    private function extractAndSavePoNumber(string $cartId, PaymentInterface $paymentMethod): void
    {
        try {
            $extensionAttributes = $paymentMethod->getExtensionAttributes();
            if ($extensionAttributes === null) {
                return;
            }

            $poNumber = $this->sanitizePoNumber($extensionAttributes->getB2bPoNumber());
            if ($poNumber === null) {
                return;
            }

            $quoteId = (int) $this->maskedQuoteIdToQuoteId->execute($cartId);

            if ($quoteId === 0) {
                return;
            }

            /** @var \Magento\Quote\Model\Quote $quote */
            $quote = $this->cartRepository->getActive($quoteId);
            $quote->setData('b2b_po_number', $poNumber);
            $this->cartRepository->save($quote);

            $this->logger->info('[B2B] PO Number salvo no quote (guest)', [
                'quote_id' => $quoteId,
                'po_number' => $poNumber
            ]);
        } catch (\Exception $e) {
            $this->logger->error('[B2B] Erro ao salvar PO Number (guest): ' . $e->getMessage());
        }
    }

    /**
     * Sanitize PO Number: trim, limit length, allow only safe characters.
     */
    private function sanitizePoNumber(mixed $poNumber): ?string
    {
        if ($poNumber === null || $poNumber === '') {
            return null;
        }

        $poNumber = trim((string) $poNumber);

        if ($poNumber === '') {
            return null;
        }

        if (mb_strlen($poNumber) > 64) {
            $this->logger->warning('[B2B] PO Number (guest) truncated — exceeded 64 chars');
            $poNumber = mb_substr($poNumber, 0, 64);
        }

        if (!preg_match('/^[a-zA-Z0-9\-\/\.\s]+$/', $poNumber)) {
            $this->logger->warning('[B2B] PO Number (guest) rejected — invalid characters', [
                'po_number' => substr($poNumber, 0, 20) . '...'
            ]);
            return null;
        }

        return $poNumber;
    }
}
