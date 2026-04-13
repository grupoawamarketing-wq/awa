<?php

declare(strict_types=1);

namespace GrupoAwamotos\WhatsAppCommerce\Api;

/**
 * WhatsApp Commerce Attendant Routing API
 *
 * Returns the assigned attendant for a customer (by phone number).
 * Used by N8N/Chatwoot to auto-assign conversations.
 */
interface AttendantInterface
{
    /**
     * Get the assigned attendant for a phone number
     *
     * @param string $phone Phone number (with or without country code)
     * @return mixed[] Attendant data with chatwoot_agent_id
     */
    public function getByPhone(string $phone): array;
}
