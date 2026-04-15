<?php
 
namespace Rokanthemes\SlideBanner\Model;
 
class Slider extends \Magento\Framework\Model\AbstractModel
{
    /**
     * Define resource model
     */
    protected function _construct()
    {
        $this->_init('Rokanthemes\SlideBanner\Model\Resource\Slider');
    }
	public function getSliderSetting()
	{
		$defaultSetting = ['items'=>1, 'itemsDesktop'=>'[1199,1]', 'itemsDesktopSmall' => '[980,3]', 'itemsTablet' => '[768,2]', 'itemsMobile' => '[479,1]', 'slideSpeed' => 500, 'paginationSpeed' => 500, 'rewindSpeed'=>500];
		if (!$this->getData('slider_setting')) {
			return $defaultSetting;
		}
		$data = json_decode($this->getData('slider_setting'), true);
		return is_array($data) ? $data : $defaultSetting;
	}
	public function getSetting()
	{
		$data = $this->getData('slider_setting');
		return $data;
	}
}