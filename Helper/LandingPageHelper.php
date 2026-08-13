<?php

namespace Algolia\AlgoliaSearch\Helper;

use Algolia\AlgoliaSearch\Model\LandingPage;
use Algolia\AlgoliaSearch\Model\LandingPageFactory;
use Algolia\AlgoliaSearch\Model\ResourceModel\LandingPage as LandingPageResource;
use Magento\Framework\App\Action\Action;
use Magento\Framework\Registry;
use Magento\Framework\View\Result\PageFactory;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Landing Page Helper
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @SuppressWarnings(PHPMD.CyclomaticComplexity)
 * @SuppressWarnings(PHPMD.NPathComplexity)
 */
class LandingPageHelper extends \Magento\Framework\App\Helper\AbstractHelper
{
    /**
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        \Magento\Framework\App\Helper\Context $context,
        protected LandingPage                 $landingPage,
        protected LandingPageFactory          $landingPageFactory,
        private Registry                      $registry,
        protected StoreManagerInterface       $storeManager,
        protected PageFactory                 $resultPageFactory,
        protected LandingPageResource         $landingPageResource
    ) {
        parent::__construct($context);
    }

    public function getLandingPage($pageId): LandingPage|null|false
    {
        if ($pageId !== null && $pageId !== $this->landingPage->getId()) {
            $this->landingPage->setStoreId($this->storeManager->getStore()->getId());
            if (!$this->landingPageResource->load($this->landingPage, $pageId)) {
                return false;
            }
            $this->registry->register('current_landing_page', $this->landingPage);
        }

        return $this->landingPage;
    }

    /**
     * Return result Landing page
     *
     * @param int $pageId
     *
     * @return \Magento\Framework\View\Result\Page|bool
     */
    public function prepareResultPage(Action $action, $pageId = null)
    {
        $page = $this->getLandingPage($pageId);

        if (!$page->getId()) {
            return false;
        }

        /** @var \Magento\Framework\View\Result\Page $resultPage */
        $resultPage = $this->resultPageFactory->create();
        $resultPage->addHandle('algolia_algoliasearch_landingpage_view');
        $resultPage->addPageLayoutHandles(
            ['id' => str_replace('/', '_', $page->getUrlKey())]
        );

        $this->_eventManager->dispatch(
            'algolia_landingpage_render',
            ['page' => $this->landingPage, 'controller_action' => $action, 'request' => $this->_getRequest()]
        );

        return $resultPage;
    }

    /**
     * Retrieve landing page direct URL
     *
     * @param string $pageId
     *
     * @return string
     */
    public function getPageUrl($pageId = null)
    {
        $page = $this->getLandingPage($pageId);

        if (!$page->getId()) {
            return false;
        }

        return $this->_urlBuilder->getUrl(null, ['_direct' => $page->getUrlKey()]);
    }
}
