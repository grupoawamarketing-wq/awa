<?php

declare(strict_types=1);

namespace GrupoAwamotos\WhatsAppCommerce\Api;

/**
 * WhatsApp Commerce Opt-in/Opt-out API
 *
 * Manages whatsapp_optin customer attribute via phone number.
 * Used by Typebot when customer opts in or out via WhatsApp.
 */
interface OptinInterface
{
    /**
     * Set WhatsApp opt-in for a customer identified by phone.
     *
     * @param string $phone Customer phone number
     * @param int $optin 1 = opt-in, 0 = opt-out
     * @param string|null $source Source of the change (bot, whatsapp, admin)
     * @return mixed[] Result with success status
     */
    public function setOptin(string $phone, int $optin, ?string $source = null): array;

    /**
     * Get WhatsApp opt-in status for a customer identified by phone.
     *
     * @param string $phone Customer phone number
     * @return mixed[] Result with optin status
     */
    public function getOptin(string $phone): array;
}
