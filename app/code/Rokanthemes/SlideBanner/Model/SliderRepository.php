<?php
/**
 * Rokanthemes_SlideBanner — SliderRepository
 * Implementação básica do SliderRepositoryInterface.
 */
namespace Rokanthemes\SlideBanner\Model;

use Rokanthemes\SlideBanner\Api\SliderRepositoryInterface;
use Rokanthemes\SlideBanner\Model\SliderFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\CouldNotDeleteException;

class SliderRepository implements SliderRepositoryInterface
{
    /**
     * @var SliderFactory
     */
    protected $sliderFactory;

    /**
     * @param SliderFactory $sliderFactory
     */
    public function __construct(SliderFactory $sliderFactory)
    {
        $this->sliderFactory = $sliderFactory;
    }

    /**
     * {@inheritdoc}
     */
    public function save(\Magento\Cms\Api\Data\PageInterface $page)
    {
        throw new \Magento\Framework\Exception\LocalizedException(__('Not implemented.'));
    }

    /**
     * {@inheritdoc}
     */
    public function getById($sliderId)
    {
        $slider = $this->sliderFactory->create();
        $slider->load($sliderId);
        if (!$slider->getId()) {
            throw new NoSuchEntityException(__('Slider with id "%1" does not exist.', $sliderId));
        }
        return $slider;
    }

    /**
     * {@inheritdoc}
     */
    public function getList(\Magento\Framework\Api\SearchCriteriaInterface $searchCriteria)
    {
        throw new \Magento\Framework\Exception\LocalizedException(__('Not implemented.'));
    }

    /**
     * {@inheritdoc}
     */
    public function delete(\Magento\Cms\Api\Data\PageInterface $page)
    {
        throw new \Magento\Framework\Exception\LocalizedException(__('Not implemented.'));
    }

    /**
     * {@inheritdoc}
     */
    public function deleteById($sliderId)
    {
        $slider = $this->getById($sliderId);
        try {
            $slider->delete();
        } catch (\Exception $e) {
            throw new CouldNotDeleteException(__($e->getMessage()));
        }
        return true;
    }
}
