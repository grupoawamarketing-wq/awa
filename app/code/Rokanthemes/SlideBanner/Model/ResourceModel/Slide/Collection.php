<?php
/**
 * Rokanthemes_SlideBanner — Collection para a entidade Slide
 */
namespace Rokanthemes\SlideBanner\Model\ResourceModel\Slide;

use Rokanthemes\SlideBanner\Model\ResourceModel\AbstractCollection;

class Collection extends AbstractCollection
{
    /**
     * @var string
     */
    protected $_idFieldName = 'slide_id';

    /**
     * Define resource model
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init(
            'Rokanthemes\\SlideBanner\\Model\\Slide',
            'Rokanthemes\\SlideBanner\\Model\\ResourceModel\\Slide'
        );
        $this->_map['fields']['slide_id'] = 'main_table.slide_id';
    }

    /**
     * Add filter by store
     *
     * @param int|array|\Magento\Store\Model\Store $store
     * @param bool $withAdmin
     * @return $this
     */
    public function addStoreFilter($store, $withAdmin = true)
    {
        return $this;
    }
}
