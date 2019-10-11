<?php

namespace Algolia\AlgoliaSearch\Controller\Adminhtml\Query;

use Algolia\AlgoliaSearch\Helper\MerchandisingHelper;
use Algolia\AlgoliaSearch\Helper\ProxyHelper;
use Algolia\AlgoliaSearch\Model\QueryFactory;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Registry;
use Magento\Store\Model\StoreManagerInterface;

abstract class AbstractAction extends \Magento\Backend\App\Action
{
    /** @var Registry */
    protected $coreRegistry;

    /** @var QueryFactory */
    protected $queryFactory;

    /** @var MerchandisingHelper */
    protected $merchandisingHelper;

    /** @var ProxyHelper */
    protected $proxyHelper;

    /** @var StoreManagerInterface */
    protected $storeManager;

    /**
     * @param Context $context
     * @param Registry $coreRegistry
     * @param QueryFactory $queryFactory
     * @param MerchandisingHelper $merchandisingHelper
     * @param ProxyHelper $proxyHelper
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        Context $context,
        Registry $coreRegistry,
        QueryFactory $queryFactory,
        MerchandisingHelper $merchandisingHelper,
        ProxyHelper $proxyHelper,
        StoreManagerInterface $storeManager
    ) {
        parent::__construct($context);

        $this->coreRegistry = $coreRegistry;
        $this->queryFactory = $queryFactory;
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
        $planLevelInfo = $this->proxyHelper->getClientConfigurationData();
        $planLevel = isset($planLevelInfo['plan_level']) ? (int) $planLevelInfo['plan_level'] : 1;

        if ($planLevel < 3) {
            $this->_response->setStatusHeader(403, '1.1', 'Forbidden');
            if (!$this->_auth->isLoggedIn()) {
                return $this->_redirect('*/auth/login');
            }
            $this->_view->loadLayout(
                ['default', 'algolia_algoliasearch_handle_query_access_denied'],
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

    /** @return \Algolia\AlgoliaSearch\Model\Query */
    protected function initQuery()
    {
        $queryId = (int) $this->getRequest()->getParam('id');

        /** @var \Algolia\AlgoliaSearch\Model\Query $queryFactory */
        $query = $this->queryFactory->create();

        if ($queryId) {
            $query->getResource()->load($query, $queryId);
            if (!$query->getId()) {
                return null;
            }
        }

        $this->coreRegistry->register('algoliasearch_query', $query);

        return $query;
    }

    /** @return array */
    protected function getActiveStores()
    {
        $stores = [];
        foreach ($this->storeManager->getStores() as $store) {
            if ($store->getIsActive()) {
                $stores[] = $store->getId();
            }
        }

        return $stores;
    }
}
