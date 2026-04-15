<?php
/**
 * Rokanthemes_SlideBanner — SlideRepository
 * Implementação básica para gestão de Slides.
 */
namespace Rokanthemes\SlideBanner\Model;

use Rokanthemes\SlideBanner\Model\SlideFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\CouldNotDeleteException;

class SlideRepository
{
    /**
     * @var SlideFactory
     */
    protected $slideFactory;

    /**
     * @param SlideFactory $slideFactory
     */
    public function __construct(SlideFactory $slideFactory)
    {
        $this->slideFactory = $slideFactory;
    }

    /**
     * @param int $slideId
     * @return \Rokanthemes\SlideBanner\Model\Slide
     * @throws NoSuchEntityException
     */
    public function getById(int $slideId)
    {
        $slide = $this->slideFactory->create();
        $slide->load($slideId);
        if (!$slide->getId()) {
            throw new NoSuchEntityException(__('Slide with id "%1" does not exist.', $slideId));
        }
        return $slide;
    }

    /**
     * @param \Rokanthemes\SlideBanner\Model\Slide $slide
     * @return \Rokanthemes\SlideBanner\Model\Slide
     * @throws CouldNotSaveException
     */
    public function save(Slide $slide)
    {
        try {
            $slide->save();
        } catch (\Exception $e) {
            throw new CouldNotSaveException(__($e->getMessage()));
        }
        return $slide;
    }

    /**
     * @param int $slideId
     * @return bool
     * @throws CouldNotDeleteException
     */
    public function deleteById(int $slideId): bool
    {
        $slide = $this->getById($slideId);
        try {
            $slide->delete();
        } catch (\Exception $e) {
            throw new CouldNotDeleteException(__($e->getMessage()));
        }
        return true;
    }
}
