<?php

/**
 * Plugin para salvar PO Number no Quote via checkout
 * P0-1: Purchase Order Number
 */

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Plugin\Checkout;

use Magento\Checkout\Api\PaymentInformationManagementInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\PaymentInterface;
use Magento\Quote\Api\Data\AddressInterface;
use Psr\Log\LoggerInterface;

class SavePoNumberPlugin
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
     * Before save payment info — extract PO Number from extension attributes
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
        $this->extractAndSavePoNumber($cartId, $paymentMethod);
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
        $this->extractAndSavePoNumber($cartId, $paymentMethod);
        return [$cartId, $paymentMethod, $billingAddress];
    }

    /**
     * Extract PO Number from payment extension attributes and save to quote
     */
    private function extractAndSavePoNumber(int $cartId, PaymentInterface $paymentMethod): void
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

            $quote = $this->cartRepository->getActive($cartId);
            $quote->setData('b2b_po_number', $poNumber);
            $this->cartRepository->save($quote);

            $this->logger->info('[B2B] PO Number salvo no quote', [
                'quote_id' => $cartId,
                'po_number' => $poNumber
            ]);
        } catch (\Exception $e) {
            $this->logger->error('[B2B] Erro ao salvar PO Number: ' . $e->getMessage());
        }
    }

    /**
     * Sanitize PO Number: trim, limit length, allow only safe characters
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

        // Limit to 64 chars (matches db_schema.xml varchar(64))
        if (mb_strlen($poNumber) > 64) {
            $this->logger->warning('[B2B] PO Number truncated — exceeded 64 chars');
            $poNumber = mb_substr($poNumber, 0, 64);
        }

        // Allow only alphanumeric, hyphens, slashes, dots, spaces
        if (!preg_match('/^[a-zA-Z0-9\-\/\.\s]+$/', $poNumber)) {
            $this->logger->warning('[B2B] PO Number rejected — invalid characters', [
                'po_number' => substr($poNumber, 0, 20) . '...'
            ]);
            return null;
        }

        return $poNumber;
    }
}
