<?php
 
namespace Rokanthemes\SlideBanner\Controller\Adminhtml\Slide;
use Magento\Framework\App\Filesystem\DirectoryList;
class Save extends \Magento\Backend\App\Action
{
    /**
     * Core registry
     *
     * @var \Magento\Framework\Registry
     */
    protected $_coreRegistry;
 
    /**
     * Result page factory
     *
     * @var \Magento\Framework\View\Result\PageFactory
     */
    protected $_resultPageFactory;
 
    /**
     * News model factory
     *
     * @var \Tutorial\SimpleNews\Model\NewsFactory
     */
    protected $_slideFactory;
 
    /**
     * @param Context $context
     * @param Registry $coreRegistry
     * @param PageFactory $resultPageFactory
     * @param NewsFactory $newsFactory
     */
    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        \Magento\Framework\Registry $coreRegistry,
        \Magento\Framework\View\Result\PageFactory $resultPageFactory
    ) {
        $this->_coreRegistry = $coreRegistry;
        $this->_resultPageFactory = $resultPageFactory;
       parent::__construct($context);
    }
 
    /**
     * News access rights checking
     *
     * @return bool
     */
    protected function _isAllowed()
    {
        return $this->_authorization->isAllowed('Rokanthemes_SlideBanner::slide');
    }
	protected function _initAction()
    {
        // load layout, set active menu and breadcrumbs
        /** @var \Magento\Backend\Model\View\Result\Page $resultPage */
        $resultPage = $this->_resultPageFactory->create();
        // $resultPage->setActiveMenu('Webkul_Grid::grid')
            // ->addBreadcrumb(__('Grid'), __('Grid'))
            // ->addBreadcrumb(__('Manage Grid'), __('Manage Grid'));
        return $resultPage;
    }
    /**
     * @return void
     */
	public function execute()
    {
        if ($data = $this->getRequest()->getPostValue('slide')) {
			$model = $this->_objectManager->create('Rokanthemes\SlideBanner\Model\Slide');
			$storeViewId = $this->getRequest()->getParam("store");
			
			if ($id = $this->getRequest()->getParam('slide_id')) {
				$model->load($id);
			}
			
			/**
			 * Save image upload
			 */
			try {
				$uploader = $this->_objectManager->create(
					'Magento\MediaStorage\Model\File\Uploader',
					['fileId' => 'slide_image']
				);
				$uploader->setAllowedExtensions(['jpg', 'jpeg', 'gif', 'png', 'webp']);

				/** @var \Magento\Framework\Image\Adapter\AdapterInterface $imageAdapter */
				$imageAdapter = $this->_objectManager->get('Magento\Framework\Image\AdapterFactory')->create();

				$uploader->addValidateCallback('banner_image', $imageAdapter, 'validateUploadFile');
				$uploader->setAllowRenameFiles(true);
				$uploader->setFilesDispersion(true);

				/** @var \Magento\Framework\Filesystem\Directory\Read $mediaDirectory */
				$mediaDirectory = $this->_objectManager->get('Magento\Framework\Filesystem')
				                       ->getDirectoryRead(DirectoryList::MEDIA);
				$result = $uploader->save($mediaDirectory->getAbsolutePath(\Rokanthemes\SlideBanner\Model\Slide::BASE_MEDIA_PATH));
				$data['slide_image'] = \Rokanthemes\SlideBanner\Model\Slide::BASE_MEDIA_PATH . $result['file'];
			} catch (\Exception $e) {
				if ($e->getCode() == 0) {
					$this->messageManager->addError($e->getMessage());
				}
				if (isset($data['slide_image']) && isset($data['slide_image']['value'])) {
					if (isset($data['slide_image']['delete'])) {
						$data['slide_image'] = null;
						$data['delete_image'] = true;
					} else if (isset($data['slide_image']['value'])) {
						$data['slide_image'] = $data['slide_image']['value'];
					} else {
						$data['slide_image'] = null;
					}
				}
			}
			
			try {
				$uploader2 = $this->_objectManager->create(
					'Magento\MediaStorage\Model\File\Uploader',
					['fileId' => 'slide_image_mobile']
				);
				$uploader2->setAllowedExtensions(['jpg', 'jpeg', 'gif', 'png', 'webp']);

				/** @var \Magento\Framework\Image\Adapter\AdapterInterface $imageAdapter */
				$imageAdapter2 = $this->_objectManager->get('Magento\Framework\Image\AdapterFactory')->create();

				$uploader2->addValidateCallback('slide_image_mobile', $imageAdapter2, 'validateUploadFile');
				$uploader2->setAllowRenameFiles(true);
				$uploader2->setFilesDispersion(true);

				/** @var \Magento\Framework\Filesystem\Directory\Read $mediaDirectory */
				$mediaDirectory2 = $this->_objectManager->get('Magento\Framework\Filesystem')
				                       ->getDirectoryRead(DirectoryList::MEDIA);
				$result2 = $uploader2->save($mediaDirectory2->getAbsolutePath(\Rokanthemes\SlideBanner\Model\Slide::BASE_MEDIA_PATH));
				$data['slide_image_mobile'] = \Rokanthemes\SlideBanner\Model\Slide::BASE_MEDIA_PATH . $result2['file'];
			} catch (\Exception $e) {
				if ($e->getCode() == 0) {
					$this->messageManager->addError($e->getMessage());
				}
				if (isset($data['slide_image_mobile']) && isset($data['slide_image_mobile']['value'])) {
					if (isset($data['slide_image_mobile']['delete'])) {
						$data['slide_image_mobile'] = null;
					} else if (isset($data['slide_image_mobile']['value'])) {
						$data['slide_image_mobile'] = $data['slide_image_mobile']['value'];
					} else {
						$data['slide_image_mobile'] = null;
					}
				}
			}
			
			$model->addData($data);
			try {
				$model->save();

				$this->messageManager->addSuccess(__('The banner has been saved.'));
				$this->_getSession()->setFormData(false);

				if ($this->getRequest()->getParam('back') === 'edit') {
					$this->_redirect(
						'*/*/edit',
						[
							'slide_id' => $model->getId(),
							'_current' => true,
							'current_slide_id' => $this->getRequest()->getParam('current_slide_id'),
							'saveandclose' => $this->getRequest()->getParam('saveandclose'),
						]
					);

					return;
				} elseif ($this->getRequest()->getParam('back') === "new") {
					$this->_redirect('*/*/new', array('_current' => true));
					return;
				}
				$this->_redirect('*/*/');
				return;
			} catch (\RuntimeException $e) {
				$this->messageManager->addError($e->getMessage());
			} catch (\Exception $e) {
				$this->messageManager->addError($e->getMessage());
				$this->messageManager->addException($e, __('Something went wrong while saving the banner.'));
			}

			$this->_getSession()->setFormData($data);
			$this->_redirect('*/*/edit', array('slide_id' => $this->getRequest()->getParam('slide_id')));
			return;
		}
		$this->_redirect('*/*/');
    }
}