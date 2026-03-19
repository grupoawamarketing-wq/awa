<?php

namespace Rokanthemes\Themeoption\Helper;

class Themeconfig extends \Magento\Framework\App\Helper\AbstractHelper
{
    protected $_storeManager;
    protected $cssFolder;
    protected $cssPath;
    protected $cssDir;
    
    public function __construct(
        \Magento\Framework\App\Helper\Context $context,
        \Magento\Store\Model\StoreManagerInterface $storeManager
    ) {
        $this->_storeManager = $storeManager;        
        $base = BP;        
        $this->cssFolder = 'rokanthemes/theme_option/';
        $this->cssPath = 'pub/media/'.$this->cssFolder;
        $this->cssDir = $base.'/'.$this->cssPath;        
        parent::__construct($context);
    }
    
    public function getBaseMediaUrl()
    {
        return $this->_storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA);
    }
    
    public function getConfigDir()
    {
        return $this->cssDir;
    }
    
    public function getThemeOption()
    {
        $storeCode = (string) $this->_storeManager->getStore()->getCode();
        $fileName = 'custom_' . $storeCode . '.css';
        $absolutePath = $this->cssDir . $fileName;
        $version = 1;

        if (is_file($absolutePath)) {
            $mtime = filemtime($absolutePath);
            if (is_int($mtime) && $mtime > 0) {
                $version = $mtime;
            }
        }

        return $this->getBaseMediaUrl() . $this->cssFolder . $fileName . '?v=' . $version;
    }
    public function isEnableFakeOrder()
    {
        if($this->scopeConfig->getValue('themeoption/fake_order/enable_f_o', \Magento\Store\Model\ScopeInterface::SCOPE_STORE)){
            return true;
        }
        else{
            return false;
        }
    }
    
    public function getInfoFakeOrder($path)
    {
        return $this->scopeConfig->getValue('themeoption/fake_order/'.$path, \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
    }
	public function isEnableStickyHeader()
	{
		if($this->scopeConfig->getValue('themeoption/header/sticky_enable', \Magento\Store\Model\ScopeInterface::SCOPE_STORE)){
			return 1;
		}
		else{
			return 0;
		}
	}
	public function getStickyLogoHeader()
	{
		$logo = $this->scopeConfig->getValue('themeoption/header/sticky_logo', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
		if($logo != ''){
			$folderName = \Rokanthemes\Themeoption\Model\Config\Stickylogo::UPLOAD_DIR;
			$path = $folderName . '/' .$logo;
			$objectManager = \Magento\Framework\App\ObjectManager::getInstance();
			$storeManager = $objectManager->get('\Magento\Store\Model\StoreManagerInterface');
			return $storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA).$path;
		}
		else{
			return '';
		}
	}
}
