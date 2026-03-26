<?php

namespace Algolia\AlgoliaSearch\Controller\Adminhtml\Query;

use Algolia\AlgoliaSearch\Helper\MerchandisingHelper;
use Algolia\AlgoliaSearch\Model\QueryFactory;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Session\SessionManagerInterface;
use Magento\Store\Model\StoreManagerInterface;

abstract class AbstractAction extends \Magento\Backend\App\Action
{
    /** @var SessionManagerInterface */
    protected $backendSession;

    /** @var QueryFactory */
    protected $queryFactory;

    /** @var MerchandisingHelper */
    protected $merchandisingHelper;

    /** @var StoreManagerInterface */
    protected $storeManager;

    public function __construct(
        Context $context,
        SessionManagerInterface $backendSession,
        QueryFactory $queryFactory,
        MerchandisingHelper $merchandisingHelper,
        StoreManagerInterface $storeManager
    ) {
        parent::__construct($context);

        $this->backendSession = $backendSession;
        $this->queryFactory = $queryFactory;
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
     * @return \Algolia\AlgoliaSearch\Model\Query
     */
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

        $this->backendSession->setData('algoliasearch_query', $query);

        return $query;
    }

    /**
     * @return array
     */
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
