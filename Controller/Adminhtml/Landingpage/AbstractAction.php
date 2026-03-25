<?php

namespace Algolia\AlgoliaSearch\Controller\Adminhtml\Landingpage;

use Algolia\AlgoliaSearch\Helper\MerchandisingHelper;
use Algolia\AlgoliaSearch\Model\LandingPageFactory;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Session\SessionManagerInterface;
use Magento\Store\Model\StoreManagerInterface;

abstract class AbstractAction extends \Magento\Backend\App\Action
{
    /** @var SessionManagerInterface */
    protected $backendSession;

    /** @var LandingPageFactory */
    protected $landingPageFactory;

    /** @var MerchandisingHelper */
    protected $merchandisingHelper;

    /** @var StoreManagerInterface */
    protected $storeManager;

    public function __construct(
        Context $context,
        SessionManagerInterface $backendSession,
        LandingPageFactory $landingPageFactory,
        MerchandisingHelper $merchandisingHelper,
        StoreManagerInterface $storeManager
    ) {
        parent::__construct($context);

        $this->backendSession = $backendSession;
        $this->landingPageFactory = $landingPageFactory;
        $this->merchandisingHelper = $merchandisingHelper;
        $this->storeManager = $storeManager;
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
            $landingPage->getResource()->load($landingPage, $landingPageId);
            if (!$landingPage->getId()) {
                return null;
            }
        }

        $this->backendSession->setData('algoliasearch_landing_page', $landingPage);

        return $landingPage;
    }
}
