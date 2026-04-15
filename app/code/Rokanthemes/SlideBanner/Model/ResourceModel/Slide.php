<?php
/**
 * Rokanthemes_SlideBanner — ResourceModel para a entidade Slide
 */
namespace Rokanthemes\SlideBanner\Model\ResourceModel;

class Slide extends \Magento\Framework\Model\ResourceModel\Db\AbstractDb
{
    /**
     * Define resource model
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init('rokanthemes_slide', 'slide_id');
    }
}
