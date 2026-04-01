<?php

/**
 * B2B Checkout Validation Service
 *
 * Centralizes validation of all B2B checkout fields:
 * - Delivery Date (format, required)
 * - Order Notes (max length, required)
 * - PO Number (already handled by SavePoNumberPlugin, included for completeness)
 *
 * P2-4.2: Centralized validation service to reduce plugin scatter
 */

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Model\Checkout;

use GrupoAwamotos\B2B\Helper\Config;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Api\Data\PaymentInterface;
use Psr\Log\LoggerInterface;

class B2BCheckoutValidationService
{
    private const DELIVERY_DATE_FORMAT = 'Y-m-d';
    private const PO_NUMBER_MAX_LENGTH = 50;
    private const ORDER_NOTES_MAX_LENGTH = 500;

    public function __construct(
        private readonly Config $config,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Validate all B2B checkout fields
     *
     * @param CartInterface $quote
     * @param PaymentInterface $paymentMethod
     * @return void
     * @throws LocalizedException
     */
    public function validateCheckoutData(CartInterface $quote, PaymentInterface $paymentMethod): void
    {
        try {
            $this->validateDeliveryDate($quote);
            $this->validateOrderNotes($quote);
            $this->validatePoNumber($paymentMethod);
        } catch (LocalizedException $e) {
            throw $e;
        } catch (\Exception $e) {
            $this->logger->error('[B2B] Erro na validação de checkout: ' . $e->getMessage(), [
                'quote_id' => $quote->getId(),
                'exception' => $e,
            ]);
            throw new LocalizedException(__('Erro ao validar dados de checkout. Por favor, tente novamente.'));
        }
    }

    /**
     * Validate delivery date
     *
     * @param CartInterface $quote
     * @return void
     * @throws LocalizedException
     */
    private function validateDeliveryDate(CartInterface $quote): void
    {
        if (!$this->config->isDeliveryDateEnabled()) {
            return;
        }

        $deliveryDate = $quote->getData('b2b_delivery_date');

        if ($this->config->isDeliveryDateRequired() && empty($deliveryDate)) {
            throw new LocalizedException(__('Data de entrega é obrigatória.'));
        }

        if (!empty($deliveryDate)) {
            if (!$this->isValidDeliveryDate((string) $deliveryDate)) {
                throw new LocalizedException(__('Data de entrega inválida. Use o formato YYYY-MM-DD.'));
            }

            if (!$this->isDeliveryDateInFuture((string) $deliveryDate)) {
                throw new LocalizedException(__('Data de entrega deve ser no futuro.'));
            }
        }
    }

    /**
     * Validate order notes
     *
     * @param CartInterface $quote
     * @return void
     * @throws LocalizedException
     */
    private function validateOrderNotes(CartInterface $quote): void
    {
        if (!$this->config->isOrderNotesEnabled()) {
            return;
        }

        $orderNotes = $quote->getData('b2b_order_notes') ?? '';

        if ($this->config->isOrderNotesRequired() && empty(trim($orderNotes))) {
            throw new LocalizedException(__('Notas do pedido são obrigatórias.'));
        }

        if (!empty($orderNotes) && strlen($orderNotes) > self::ORDER_NOTES_MAX_LENGTH) {
            throw new LocalizedException(__(
                'Notas do pedido não podem exceder %1 caracteres.',
                self::ORDER_NOTES_MAX_LENGTH
            ));
        }
    }

    /**
     * Validate PO Number (from payment extension attributes)
     *
     * @param PaymentInterface $paymentMethod
     * @return void
     * @throws LocalizedException
     */
    private function validatePoNumber(PaymentInterface $paymentMethod): void
    {
        if (!$this->config->isPoNumberEnabled()) {
            return;
        }

        $extensionAttributes = $paymentMethod->getExtensionAttributes();
        if ($extensionAttributes === null) {
            if ($this->config->isPoNumberRequired()) {
                throw new LocalizedException(__('Número de pedido é obrigatório.'));
            }
            return;
        }

        $poNumber = $extensionAttributes->getB2bPoNumber();

        if ($this->config->isPoNumberRequired() && empty($poNumber)) {
            throw new LocalizedException(__('Número de pedido é obrigatório.'));
        }

        if (!empty($poNumber)) {
            if (strlen((string) $poNumber) > self::PO_NUMBER_MAX_LENGTH) {
                throw new LocalizedException(__(
                    'Número de pedido não pode exceder %1 caracteres.',
                    self::PO_NUMBER_MAX_LENGTH
                ));
            }

            if (!$this->isValidPoNumber((string) $poNumber)) {
                throw new LocalizedException(__('Número de pedido contém caracteres inválidos.'));
            }
        }
    }

    /**
     * Check if delivery date is valid
     *
     * @param string $dateString
     * @return bool
     */
    private function isValidDeliveryDate(string $dateString): bool
    {
        $date = \DateTime::createFromFormat(self::DELIVERY_DATE_FORMAT, $dateString);
        return $date !== false && $date->format(self::DELIVERY_DATE_FORMAT) === $dateString;
    }

    /**
     * Check if delivery date is in the future
     *
     * @param string $dateString
     * @return bool
     */
    private function isDeliveryDateInFuture(string $dateString): bool
    {
        try {
            $deliveryDate = new \DateTime($dateString);
            $today = new \DateTime('today');
            return $deliveryDate > $today;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Check if PO Number is valid (alphanumeric + common separators)
     *
     * @param string $poNumber
     * @return bool
     */
    private function isValidPoNumber(string $poNumber): bool
    {
        // Allow alphanumeric, spaces, hyphens, slashes, periods
        return (bool) preg_match('/^[a-zA-Z0-9\s\-\/\.]+$/', $poNumber);
    }

    /**
     * Get delivery date validation error messages
     *
     * @return array<string, string>
     */
    public function getDeliveryDateErrors(): array
    {
        return [
            'required' => 'Data de entrega é obrigatória.',
            'invalid_format' => 'Data de entrega inválida. Use o formato YYYY-MM-DD.',
            'past_date' => 'Data de entrega deve ser no futuro.',
        ];
    }

    /**
     * Get order notes validation error messages
     *
     * @return array<string, string>
     */
    public function getOrderNotesErrors(): array
    {
        return [
            'required' => 'Notas do pedido são obrigatórias.',
            'max_length' => sprintf('Notas do pedido não podem exceder %d caracteres.', self::ORDER_NOTES_MAX_LENGTH),
        ];
    }

    /**
     * Get PO number validation error messages
     *
     * @return array<string, string>
     */
    public function getPoNumberErrors(): array
    {
        return [
            'required' => 'Número de pedido é obrigatório.',
            'max_length' => sprintf('Número de pedido não pode exceder %d caracteres.', self::PO_NUMBER_MAX_LENGTH),
            'invalid_chars' => 'Número de pedido contém caracteres inválidos.',
        ];
    }
}
