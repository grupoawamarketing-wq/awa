<?php
declare(strict_types=1);

namespace GrupoAwamotos\ERPIntegration\Model;

/**
 * Normalizes dirty email values received from ERP integrations.
 */
class EmailSanitizer
{
    public function normalize(string $email): string
    {
        $email = html_entity_decode($email, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $email = $this->stripInvisibleCharacters($email);
        $email = trim($email);
        $email = preg_replace('/^mailto\s*:/iu', '', $email) ?? $email;
        $email = trim($email, " \t\n\r\0\x0B'\"<>");
        $email = preg_replace('/\s+/u', '', $email) ?? '';
        $email = strtolower($email);

        $atPosition = strrpos($email, '@');
        if ($atPosition === false) {
            return '';
        }

        $localPart = trim(substr($email, 0, $atPosition), '.');
        $domainPart = trim(substr($email, $atPosition + 1), '.');

        if ($localPart === '' || $domainPart === '') {
            return '';
        }

        $normalized = $localPart . '@' . $domainPart;

        if (!str_contains($domainPart, '.')) {
            return '';
        }

        if (!filter_var($normalized, FILTER_VALIDATE_EMAIL)) {
            return '';
        }

        return $normalized;
    }

    public function summarizeRaw(string $email): string
    {
        $email = html_entity_decode($email, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $email = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $email) ?? '';
        $email = preg_replace('/[\x{00A0}\x{2000}-\x{200D}\x{202F}\x{205F}\x{3000}\x{FEFF}]+/u', ' ', $email) ?? '';
        $email = preg_replace('/\s+/u', ' ', $email) ?? '';
        $email = trim($email);

        return $email !== '' ? mb_substr($email, 0, 120) : '[vazio]';
    }

    private function stripInvisibleCharacters(string $value): string
    {
        $value = preg_replace('/[\x00-\x1F\x7F]+/u', '', $value) ?? '';
        return preg_replace('/[\x{00A0}\x{2000}-\x{200D}\x{202F}\x{205F}\x{3000}\x{FEFF}]+/u', '', $value) ?? '';
    }
}
