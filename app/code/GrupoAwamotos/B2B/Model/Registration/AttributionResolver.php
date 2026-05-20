<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Model\Registration;

use Magento\Framework\App\RequestInterface;

/**
 * Normalises UTM/CNAME/vendedor params from the registration POST.
 */
class AttributionResolver
{
    private const MAX_LENGTH = 255;

    /**
     * @param array<string, mixed> $params
     */
    public function fromParams(array $params): AttributionData
    {
        $campaign = $this->sanitize(
            (string) ($params['cname'] ?? $params['campaign'] ?? $params['b2b_registration_campaign'] ?? '')
        );
        $attendantId = $this->parsePositiveInt($params['attendant'] ?? $params['attendant_id'] ?? null);
        $erpSellerCode = $this->parsePositiveInt($params['vendedor'] ?? $params['erp_seller_code'] ?? null);

        return new AttributionData(
            campaignName: $campaign,
            utmSource: $this->sanitize((string) ($params['utm_source'] ?? '')),
            utmMedium: $this->sanitize((string) ($params['utm_medium'] ?? '')),
            utmCampaign: $this->sanitize((string) ($params['utm_campaign'] ?? '')),
            utmContent: $this->sanitize((string) ($params['utm_content'] ?? '')),
            utmTerm: $this->sanitize((string) ($params['utm_term'] ?? '')),
            landingPath: $this->sanitize((string) ($params['registration_landing'] ?? ''), 512),
            attendantId: $attendantId,
            erpSellerCode: $erpSellerCode
        );
    }

    public function fromRequest(RequestInterface $request): AttributionData
    {
        return $this->fromParams($request->getParams());
    }

    private function sanitize(string $value, int $maxLength = self::MAX_LENGTH): string
    {
        $value = trim(strip_tags($value));
        if ($value === '') {
            return '';
        }

        return mb_substr($value, 0, $maxLength);
    }

    private function parsePositiveInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $int = (int) preg_replace('/\D/', '', (string) $value);

        return $int > 0 ? $int : null;
    }
}
