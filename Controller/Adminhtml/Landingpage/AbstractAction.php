<?php

namespace Algolia\AlgoliaSearch\Controller\Adminhtml\Landingpage;

use Algolia\AlgoliaSearch\Helper\MerchandisingHelper;
use Algolia\AlgoliaSearch\Model\LandingPageFactory;
use Algolia\AlgoliaSearch\Model\ResourceModel\LandingPage as LandingPageResource;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Session\SessionManagerInterface;
use Magento\Store\Model\StoreManagerInterface;

abstract class AbstractAction extends \Magento\Backend\App\Action
{
    public function __construct(
        Context                           $context,
        protected SessionManagerInterface $backendSession,
        protected LandingPageFactory      $landingPageFactory,
        protected MerchandisingHelper     $merchandisingHelper,
        protected StoreManagerInterface   $storeManager,
        protected LandingPageResource     $landingPageResource
    ) {
        parent::__construct($context);
    }

    /**
     * @return bool
     */
    protected function _isAllowed()
    {
        return $this->_authorization->isAllowed('Algolia_AlgoliaSearch::manage');
    }

    /**
     * @return \Algolia\AlgoliaSearch\Model\LandingPage
     */
    protected function initLandingPage()
    {
        $landingPageId = (int) $this->getRequest()->getParam('id');

        /** @var \Algolia\AlgoliaSearch\Model\LandingPage $landingPage */
        $landingPage = $this->landingPageFactory->create();

        if ($landingPageId) {
            $this->landingPageResource->load($landingPage, $landingPageId);
            if (!$landingPage->getId()) {
                return null;
            }
        }

        $this->backendSession->setData('algoliasearch_landing_page', $landingPage);

        return $landingPage;
    }
}
