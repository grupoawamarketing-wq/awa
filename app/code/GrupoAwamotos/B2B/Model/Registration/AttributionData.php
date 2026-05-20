<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Model\Registration;

/**
 * Marketing attribution captured at B2B registration.
 */
final class AttributionData
{
    public function __construct(
        public readonly string $campaignName = '',
        public readonly string $utmSource = '',
        public readonly string $utmMedium = '',
        public readonly string $utmCampaign = '',
        public readonly string $utmContent = '',
        public readonly string $utmTerm = '',
        public readonly string $landingPath = '',
        public readonly ?int $attendantId = null,
        public readonly ?int $erpSellerCode = null
    ) {
    }

    /**
     * @return array<string, string>
     */
    public function toCustomerAttributes(): array
    {
        $data = [];

        if ($this->campaignName !== '') {
            $data['b2b_registration_campaign'] = $this->campaignName;
        }
        if ($this->utmSource !== '') {
            $data['b2b_utm_source'] = $this->utmSource;
        }
        if ($this->utmMedium !== '') {
            $data['b2b_utm_medium'] = $this->utmMedium;
        }
        if ($this->utmCampaign !== '') {
            $data['b2b_utm_campaign'] = $this->utmCampaign;
        }
        if ($this->utmContent !== '') {
            $data['b2b_utm_content'] = $this->utmContent;
        }
        if ($this->utmTerm !== '') {
            $data['b2b_utm_term'] = $this->utmTerm;
        }
        if ($this->landingPath !== '') {
            $data['b2b_registration_landing'] = $this->landingPath;
        }

        return $data;
    }

    public function hasAttendantReferral(): bool
    {
        return ($this->attendantId !== null && $this->attendantId > 0)
            || ($this->erpSellerCode !== null && $this->erpSellerCode > 0);
    }
}
