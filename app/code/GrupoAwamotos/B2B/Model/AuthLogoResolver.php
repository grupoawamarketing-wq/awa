<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Directory\ReadInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Asset\Repository as AssetRepository;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

class AuthLogoResolver
{
    private const XML_PATH_LOGO_SRC = 'design/header/logo_src';
    private const XML_PATH_LOGO_ALT = 'design/header/logo_alt';
    private const FALLBACK_ASSET = 'GrupoAwamotos_B2B::images/auth-logo.svg';
    private const DEFAULT_ALT = 'Grupo AWA Motos';

    private ScopeConfigInterface $scopeConfig;
    private StoreManagerInterface $storeManager;
    private ReadInterface $mediaDirectory;
    private AssetRepository $assetRepository;

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        StoreManagerInterface $storeManager,
        Filesystem $filesystem,
        AssetRepository $assetRepository
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->storeManager = $storeManager;
        $this->mediaDirectory = $filesystem->getDirectoryRead(DirectoryList::MEDIA);
        $this->assetRepository = $assetRepository;
    }

    public function getLogoSrc(?int $storeId = null): string
    {
        $store = $this->storeManager->getStore($storeId);
        $configured = trim((string)$this->scopeConfig->getValue(
            self::XML_PATH_LOGO_SRC,
            ScopeInterface::SCOPE_STORE,
            (int)$store->getId()
        ));

        if ($configured !== '') {
            if (preg_match('#^https?://#i', $configured)) {
                return $configured;
            }

            $mediaPath = $this->resolveMediaPath($configured);
            if ($mediaPath !== null) {
                return rtrim($store->getBaseUrl(UrlInterface::URL_TYPE_MEDIA), '/') . '/' . $mediaPath;
            }
        }

        return $this->assetRepository->getUrlWithParams(
            self::FALLBACK_ASSET,
            ['_secure' => $store->isCurrentlySecure()]
        );
    }

    private function resolveMediaPath(string $configured): ?string
    {
        $normalizedPath = ltrim(trim($configured), '/');

        if ($normalizedPath === '') {
            return null;
        }

        if (str_starts_with($normalizedPath, 'media/')) {
            $normalizedPath = substr($normalizedPath, strlen('media/'));
        }

        $candidates = [$normalizedPath];
        if (!str_starts_with($normalizedPath, 'logo/') && !str_starts_with($normalizedPath, '.thumbslogo/')) {
            $candidates[] = 'logo/' . $normalizedPath;
        }

        foreach (array_unique($candidates) as $candidate) {
            if ($candidate !== '' && $this->mediaDirectory->isExist($candidate)) {
                return ltrim($candidate, '/');
            }
        }

        return null;
    }

    public function getLogoAlt(?int $storeId = null): string
    {
        $store = $this->storeManager->getStore($storeId);
        $configuredAlt = trim((string)$this->scopeConfig->getValue(
            self::XML_PATH_LOGO_ALT,
            ScopeInterface::SCOPE_STORE,
            (int)$store->getId()
        ));

        if ($configuredAlt !== '') {
            return $configuredAlt;
        }

        $storeName = trim((string)$store->getFrontendName());

        return $storeName !== '' ? $storeName : self::DEFAULT_ALT;
    }
}
