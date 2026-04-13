<?php

declare(strict_types=1);

namespace GrupoAwamotos\AbandonedCart\Model;

use GrupoAwamotos\AbandonedCart\Api\Data\AbandonedCartInterface;
use GrupoAwamotos\AbandonedCart\Helper\Data as Helper;
use GrupoAwamotos\SmartSuggestions\Api\WhatsappSenderInterface;
use Magento\Customer\Api\AddressRepositoryInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Sends abandoned cart WhatsApp notifications via SmartSuggestions sender.
 */
class WhatsAppSender
{
    public function __construct(
        private readonly WhatsappSenderInterface $whatsappSender,
        private readonly Helper $helper,
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly AddressRepositoryInterface $addressRepository,
        private readonly CartRepositoryInterface $cartRepository,
        private readonly StoreManagerInterface $storeManager,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Send WhatsApp message for abandoned cart wave.
     *
     * @param AbandonedCartInterface $abandonedCart
     * @param int $waveNumber 1, 2 or 3
     * @param string|null $couponCode Coupon code for waves 2/3
     * @return bool
     */
    public function send(AbandonedCartInterface $abandonedCart, int $waveNumber, ?string $couponCode = null): bool
    {
        $storeId = $abandonedCart->getStoreId();

        if (!$this->helper->isWhatsappEnabled($storeId)) {
            return false;
        }

        if (!$this->helper->isWhatsappEnabledForWave($waveNumber, $storeId)) {
            return false;
        }

        $phone = $this->getCustomerPhone($abandonedCart);
        if ($phone === '') {
            return false;
        }

        if (!$this->hasWhatsappOptin($abandonedCart)) {
            $this->logger->debug('[AbandonedCart] WhatsApp skipped - no opt-in', [
                'cart_id' => $abandonedCart->getEntityId(),
            ]);
            return false;
        }

        $message = $this->buildMessage($abandonedCart, $waveNumber, $couponCode);

        try {
            $result = $this->whatsappSender->sendMessage($phone, $message);
            $success = (bool) ($result['success'] ?? false);

            if ($success) {
                $this->logger->info(sprintf(
                    '[AbandonedCart] WhatsApp wave %d sent for cart %d',
                    $waveNumber,
                    $abandonedCart->getEntityId()
                ));
            } else {
                $this->logger->warning('[AbandonedCart] WhatsApp send failed', [
                    'cart_id' => $abandonedCart->getEntityId(),
                    'wave' => $waveNumber,
                    'error' => $result['message'] ?? 'unknown',
                ]);
            }

            return $success;
        } catch (\Exception $e) {
            $this->logger->error('[AbandonedCart] WhatsApp error: ' . $e->getMessage(), [
                'cart_id' => $abandonedCart->getEntityId(),
                'wave' => $waveNumber,
            ]);
            return false;
        }
    }

    /**
     * Build WhatsApp message for the given wave.
     */
    private function buildMessage(AbandonedCartInterface $abandonedCart, int $waveNumber, ?string $couponCode): string
    {
        $name = $abandonedCart->getCustomerName() ?: 'Cliente';
        $value = 'R$ ' . number_format($abandonedCart->getCartValue(), 2, ',', '.');
        $items = $abandonedCart->getItemsCount();
        $cartUrl = $this->getCartRecoveryUrl($abandonedCart);

        return match ($waveNumber) {
            1 => "Oi {$name}! Vi que você deixou {$items} item(ns) no carrinho 🛒\n"
                . "Valor: {$value}\n\n"
                . "Finalize sua compra: {$cartUrl}",
            2 => "Oi {$name}! Ainda pensando? 🤔\n"
                . "Separei um cupom especial pra você! 🎁\n"
                . ($couponCode ? "Use o cupom: *{$couponCode}*\n" : '')
                . "Carrinho: {$value} ({$items} item(ns))\n\n"
                . "Finalizar: {$cartUrl}",
            3 => "Oi {$name}! Últimas unidades! ⏰\n"
                . "Seu carrinho de {$value} expira em breve.\n"
                . ($couponCode ? "Cupom exclusivo: *{$couponCode}*\n" : '')
                . "\nGaranta agora: {$cartUrl}",
            default => "Oi {$name}! Você tem itens esperando no carrinho. Finalize: {$cartUrl}",
        };
    }

    /**
     * Get cart recovery URL.
     */
    private function getCartRecoveryUrl(AbandonedCartInterface $abandonedCart): string
    {
        try {
            $baseUrl = $this->storeManager->getStore((int) $abandonedCart->getStoreId())->getBaseUrl();
        } catch (\Exception $e) {
            $baseUrl = 'https://awamotos.com/';
        }

        return rtrim($baseUrl, '/') . '/checkout/cart';
    }

    /**
     * Get customer phone number (BR format with country code).
     */
    private function getCustomerPhone(AbandonedCartInterface $abandonedCart): string
    {
        $customerId = $abandonedCart->getCustomerId();
        if (!$customerId) {
            return '';
        }

        try {
            $customer = $this->customerRepository->getById($customerId);
            $billing = $customer->getDefaultBilling();

            if ($billing) {
                $address = $this->addressRepository->getById((int) $billing);
                $phone = $address->getTelephone();
            } else {
                $phone = '';
            }
        } catch (\Exception $e) {
            try {
                $quote = $this->cartRepository->get($abandonedCart->getQuoteId());
                $billingAddress = $quote->getBillingAddress();
                $phone = $billingAddress ? (string) $billingAddress->getTelephone() : '';
            } catch (\Exception $e2) {
                return '';
            }
        }

        if (empty($phone)) {
            return '';
        }

        $digits = preg_replace('/\D/', '', $phone) ?? '';
        if (strlen($digits) <= 11 && !str_starts_with($digits, '55')) {
            $digits = '55' . $digits;
        }

        return $digits;
    }

    /**
     * Check if customer has WhatsApp opt-in (LGPD).
     */
    private function hasWhatsappOptin(AbandonedCartInterface $abandonedCart): bool
    {
        $customerId = $abandonedCart->getCustomerId();
        if (!$customerId) {
            return false;
        }

        try {
            $customer = $this->customerRepository->getById($customerId);
            $optin = $customer->getCustomAttribute('whatsapp_optin');
            return $optin !== null && (int) $optin->getValue() === 1;
        } catch (\Exception $e) {
            return false;
        }
    }
}
