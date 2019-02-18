<?php

namespace Algolia\AlgoliaSearch\Controller\Adminhtml\Landingpage;

use Algolia\AlgoliaSearch\Helper\MerchandisingHelper;
use Algolia\AlgoliaSearch\Helper\ProxyHelper;
use Algolia\AlgoliaSearch\Model\LandingPageFactory;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Registry;
use Magento\Store\Model\StoreManagerInterface;

abstract class AbstractAction extends \Magento\Backend\App\Action
{
    /** @var Registry */
    protected $coreRegistry;

    /** @var LandingPageFactory */
    protected $landingPageFactory;

    /** @var MerchandisingHelper */
    protected $merchandisingHelper;

    /** @var ProxyHelper */
    protected $proxyHelper;

    /** @var StoreManagerInterface */
    protected $storeManager;

    /**
     * @param Context $context
     * @param Registry $coreRegistry
     * @param LandingPageFactory $landingPageFactory
     * @param MerchandisingHelper $merchandisingHelper
     * @param ProxyHelper $proxyHelper
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        Context $context,
        Registry $coreRegistry,
        LandingPageFactory $landingPageFactory,
        MerchandisingHelper $merchandisingHelper,
        ProxyHelper $proxyHelper,
        StoreManagerInterface $storeManager
    ) {
        parent::__construct($context);

        $this->coreRegistry = $coreRegistry;
        $this->landingPageFactory = $landingPageFactory;
        $this->merchandisingHelper = $merchandisingHelper;
        $this->proxyHelper = $proxyHelper;
        $this->storeManager = $storeManager;
    }

    /** @return bool */
    protected function _isAllowed()
    {
        return $this->_authorization->isAllowed('Algolia_AlgoliaSearch::manage');
    }

    /**
     * {@inheritdoc}
     */
    public function dispatch(\Magento\Framework\App\RequestInterface $request)
    {
        $planLevel = 1;
        $planLevelInfo = $this->proxyHelper->getInfo(ProxyHelper::INFO_TYPE_PLAN_LEVEL);

        if (isset($planLevelInfo['plan_level'])) {
            $planLevel = (int) $planLevelInfo['plan_level'];
        }

        if ($planLevel <= 1) {
            $this->_response->setStatusHeader(403, '1.1', 'Forbidden');
            if (!$this->_auth->isLoggedIn()) {
                return $this->_redirect('*/auth/login');
            }
            $this->_view->loadLayout(
                ['default', 'algolia_algoliasearch_handle_access_denied'],
                true,
                true,
                false
            );
            $this->_view->getLayout();
            $this->_view->renderLayout();
            $this->_request->setDispatched(true);

            return $this->_response;
        }

        return parent::dispatch($request);
    }

    /** @return Algolia\AlgoliaSearch\Model\LandingPage */
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

        $this->coreRegistry->register('algoliasearch_landing_page', $landingPage);

        return $landingPage;
    }
}
