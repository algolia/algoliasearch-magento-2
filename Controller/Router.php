<?php

namespace Algolia\AlgoliaSearch\Controller;

use Algolia\AlgoliaSearch\Model\ResourceModel\LandingPage as LandingPageResource;
use Magento\Framework\App\ActionFactory;
use Magento\Framework\Stdlib\DateTime;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class Router implements \Magento\Framework\App\RouterInterface
{
    public function __construct(
        protected ActionFactory         $actionFactory,
        protected LandingPageResource   $landingPageResource,
        protected TimezoneInterface     $localeDate,
        protected DateTime              $dateTime,
        protected StoreManagerInterface $storeManager
    ) {
    }

    /**
     * Validate and match landing pages from Algolia and modify request
     *
     *
     * @return \Magento\Framework\App\ActionInterface|null
     */
    public function match(\Magento\Framework\App\RequestInterface $request)
    {
        $identifier = trim($request->getPathInfo(), '/');

        $storeId = $this->storeManager->getStore()->getId();
        $date = $this->dateTime->formatDate($this->localeDate->scopeTimeStamp($storeId), false);
        $pageId = $this->landingPageResource->checkIdentifier($identifier, $storeId, $date);

        if (!$pageId) {
            return null;
        }

        $request
            ->setModuleName('algolia')
            ->setControllerName('landingpage')
            ->setActionName('view')
            ->setParam('landing_page_id', $pageId);

        $request->setAlias(\Magento\Framework\Url::REWRITE_REQUEST_PATH_ALIAS, $identifier);

        return $this->actionFactory->create(\Magento\Framework\App\Action\Forward::class);
    }
}
