<?php

namespace Algolia\AlgoliaSearch\Controller;

use Algolia\AlgoliaSearch\Model\LandingPageFactory;
use Magento\Framework\App\ActionFactory;
use Magento\Framework\Stdlib\DateTime;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class Router implements \Magento\Framework\App\RouterInterface
{
    /** @var ActionFactory */
    protected $actionFactory;

    /** @var StoreManagerInterface */
    protected $storeManager;

    /** @var TimezoneInterface */
    protected $localeDate;

    /** @var DateTime */
    protected $dateTime;

    /** @var LandingPageFactory */
    protected $landingPageFactory;

    /**
     * @param ActionFactory $actionFactory
     * @param LandingPageFactory $landingPageFactory
     * @param TimezoneInterface $localeDate
     * @param DateTime $dateTime
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        ActionFactory $actionFactory,
        LandingPageFactory $landingPageFactory,
        TimezoneInterface $localeDate,
        DateTime $dateTime,
        StoreManagerInterface $storeManager
    ) {
        $this->actionFactory = $actionFactory;
        $this->landingPageFactory = $landingPageFactory;
        $this->localeDate = $localeDate;
        $this->dateTime = $dateTime;
        $this->storeManager = $storeManager;
    }

    /**
     * Validate and match landing pages from Algolia and modify request
     *
     * @param \Magento\Framework\App\RequestInterface $request
     *
     * @return \Magento\Framework\App\ActionInterface|null
     */
    public function match(\Magento\Framework\App\RequestInterface $request)
    {
        $identifier = trim($request->getPathInfo(), '/');

        /** @var \Algolia\AlgoliaSearch\Model\LandingPage $landingPage */
        $landingPage = $this->landingPageFactory->create();
        $storeId = $this->storeManager->getStore()->getId();
        $date = $this->dateTime->formatDate($this->localeDate->scopeTimeStamp($storeId), false);
        $pageId = $landingPage->checkIdentifier($identifier, $storeId, $date);

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
