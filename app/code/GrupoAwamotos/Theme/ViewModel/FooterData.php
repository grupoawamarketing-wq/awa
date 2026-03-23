<?php
declare(strict_types=1);

namespace GrupoAwamotos\Theme\ViewModel;

use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class FooterData implements ArgumentInterface
{
    private const XML_PATH_MOBILE_MENU_ENABLE = 'themeoption/footer/footer_menu_mobile';
    private const XML_PATH_CONTACT_PHONE = 'grupoawamotos_theme/contact/phone';
    private const XML_PATH_CONTACT_EMAIL = 'grupoawamotos_theme/contact/email';
    private const XML_PATH_CONTACT_WHATSAPP = 'grupoawamotos_theme/contact/whatsapp_number';
    private const XML_PATH_STORE_PHONE = 'general/store_information/phone';
    private const XML_PATH_STORE_NAME = 'general/store_information/name';

    private const XML_PATH_STORE_STREET = 'general/store_information/street_line1';
    private const XML_PATH_STORE_CITY = 'general/store_information/city';
    private const XML_PATH_STORE_POSTCODE = 'general/store_information/postcode';
    private const XML_PATH_SOCIAL_INSTAGRAM = 'grupoawamotos_theme/social/instagram_url';
    private const XML_PATH_SOCIAL_FACEBOOK = 'grupoawamotos_theme/social/facebook_url';
    private const XML_PATH_SOCIAL_YOUTUBE = 'grupoawamotos_theme/social/youtube_url';
    private const XML_PATH_FOOTER_EXPERIMENT_ENABLED = 'grupoawamotos_theme/footer_experiment/enabled';
    private const XML_PATH_FOOTER_EXPERIMENT_ROLLOUT = 'grupoawamotos_theme/footer_experiment/rollout_percentage';
    private const XML_PATH_FOOTER_EXPERIMENT_SEED = 'grupoawamotos_theme/footer_experiment/variant_seed';

    private const DEFAULT_PHONE = '(16) 3322-0000';
    private const DEFAULT_WHATSAPP = '(16) 99736-7588';
    private const DEFAULT_EMAIL = 'contato@awamotos.com.br';
    private const DEFAULT_STREET = 'Rua Castro Alves, 1234';
    private const DEFAULT_CITY = 'Araraquara-SP';
    private const DEFAULT_POSTCODE = '';
    private const DEFAULT_INSTAGRAM_URL = 'https://www.instagram.com/awamotos';
    private const DEFAULT_FACEBOOK_URL = 'https://www.facebook.com/awamotos';
    private const DEFAULT_YOUTUBE_URL = 'https://www.youtube.com/@awamotos';
    private const DEFAULT_FOOTER_EXPERIMENT_SEED = 'home5_footer_v1';

    public function __construct(
        private ScopeConfigInterface $scopeConfig
    ) {}

    /**
     * Check if the mobile footer menu is enabled in theme configuration.
     *
     * @return bool
     */
    public function isMobileMenuEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_MOBILE_MENU_ENABLE,
            ScopeInterface::SCOPE_STORE
        );
    }

    public function getStoreName(): string
    {
        $storeName = $this->getConfigValue(self::XML_PATH_STORE_NAME, '');

        return $storeName !== '' ? $storeName : 'AWA Motos';
    }

    public function getPhone(): string
    {
        $contactPhone = $this->getConfigValue(self::XML_PATH_CONTACT_PHONE, '');

        if ($contactPhone !== '') {
            return $contactPhone;
        }

        $storePhone = $this->getConfigValue(self::XML_PATH_STORE_PHONE, '');

        return $storePhone !== '' ? $storePhone : self::DEFAULT_PHONE;
    }

    public function getPhoneUrl(): string
    {
        $digits = $this->normalizePhone($this->getPhone());

        return $digits !== '' ? 'tel:+' . $digits : '';
    }

    public function getWhatsApp(): string
    {
        $whatsApp = $this->getConfigValue(self::XML_PATH_CONTACT_WHATSAPP, '');

        return $whatsApp !== '' ? $whatsApp : self::DEFAULT_WHATSAPP;
    }

    public function getWhatsAppUrl(): string
    {
        $digits = $this->normalizePhone($this->getWhatsApp());

        return $digits !== '' ? 'https://wa.me/' . $digits : '';
    }

    public function getEmail(): string
    {
        $email = $this->getConfigValue(self::XML_PATH_CONTACT_EMAIL, '');

        return $email !== '' ? $email : self::DEFAULT_EMAIL;
    }

    public function getEmailUrl(): string
    {
        $email = trim($this->getEmail());

        return $email !== '' ? 'mailto:' . $email : '';
    }

    public function getStreetLine1(): string
    {
        $street = $this->getConfigValue(self::XML_PATH_STORE_STREET, '');

        return $street !== '' ? $street : self::DEFAULT_STREET;
    }

    public function getCityLabel(): string
    {
        $city = $this->getConfigValue(self::XML_PATH_STORE_CITY, '');

        return $city !== '' ? $city : self::DEFAULT_CITY;
    }

    public function getPostcode(): string
    {
        $postcode = $this->getConfigValue(self::XML_PATH_STORE_POSTCODE, '');

        return $postcode !== '' ? $postcode : self::DEFAULT_POSTCODE;
    }

    public function getInstagramUrl(): string
    {
        return $this->getConfigValue(self::XML_PATH_SOCIAL_INSTAGRAM, self::DEFAULT_INSTAGRAM_URL);
    }

    public function getFacebookUrl(): string
    {
        return $this->getConfigValue(self::XML_PATH_SOCIAL_FACEBOOK, self::DEFAULT_FACEBOOK_URL);
    }

    public function getYoutubeUrl(): string
    {
        return $this->getConfigValue(self::XML_PATH_SOCIAL_YOUTUBE, self::DEFAULT_YOUTUBE_URL);
    }

    public function isFooterExperimentEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_FOOTER_EXPERIMENT_ENABLED,
            ScopeInterface::SCOPE_STORE
        );
    }

    public function getFooterExperimentRolloutPercentage(): int
    {
        $value = (int) $this->scopeConfig->getValue(
            self::XML_PATH_FOOTER_EXPERIMENT_ROLLOUT,
            ScopeInterface::SCOPE_STORE
        );

        return $this->normalizePercentage($value);
    }

    public function getFooterExperimentSeed(): string
    {
        return $this->getConfigValue(
            self::XML_PATH_FOOTER_EXPERIMENT_SEED,
            self::DEFAULT_FOOTER_EXPERIMENT_SEED
        );
    }

    public function getFormattedAddress(): string
    {
        $parts = [
            $this->getStreetLine1(),
            $this->getCityLabel(),
        ];

        $postcode = $this->getPostcode();
        if ($postcode !== '') {
            $parts[] = 'CEP: ' . $postcode;
        }

        return implode(' - ', array_filter($parts));
    }

    private function normalizePhone(string $value): string
    {
        return (string) preg_replace('/\D+/', '', $value);
    }

    private function getConfigValue(string $path, string $default): string
    {
        $value = trim((string) $this->scopeConfig->getValue(
            $path,
            ScopeInterface::SCOPE_STORE
        ));

        return $value !== '' ? $value : $default;
    }

    private function normalizePercentage(int $value): int
    {
        if ($value < 0) {
            return 0;
        }

        if ($value > 100) {
            return 100;
        }

        return $value;
    }
}
