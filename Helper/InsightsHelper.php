<?php

namespace Algolia\AlgoliaSearch\Helper;

use Algolia\AlgoliaSearch\Helper\Configuration\PersonalizationHelper;
use Algolia\AlgoliaSearch\Insights\UserInsightsClient;
use Algolia\AlgoliaSearch\InsightsClient;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Stdlib\Cookie\CookieMetadataFactory;
use Magento\Framework\Stdlib\CookieManagerInterface;

class InsightsHelper
{
    const ALGOLIA_ANON_USER_TOKEN_COOKIE_NAME = '_ALGOLIA';

    const ALGOLIA_CUSTOMER_USER_TOKEN_COOKIE_NAME = 'aa-search';

    /** @var ConfigHelper */
    private $configHelper;

    /** @var PersonalizationHelper */
    private $personalizationHelper;

    /** @var CookieManagerInterface */
    private $cookieManager;

    /** @var CookieMetadataFactory */
    private $cookieMetadataFactory;

    /** @var InsightsClient */
    private $insightsClient;

    /** @var UserInsightsClient */
    private $userInsightsClient;

    /** @var CustomerSession */
    private $customerSession;

    /**
     * InsightsHelper constructor.
     *
     * @param ConfigHelper $configHelper
     * @param PersonalizationHelper $personalizationHelper
     * @param CookieManagerInterface $cookieManager
     * @param CookieMetadataFactory $cookieMetadataFactory
     * @param CustomerSession $customerSession
     */
    public function __construct(
        ConfigHelper $configHelper,
        PersonalizationHelper $personalizationHelper,
        CookieManagerInterface $cookieManager,
        CookieMetadataFactory $cookieMetadataFactory,
        CustomerSession $customerSession
    ) {
        $this->configHelper = $configHelper;
        $this->personalizationHelper = $personalizationHelper;
        $this->cookieManager = $cookieManager;
        $this->cookieMetadataFactory = $cookieMetadataFactory;
        $this->customerSession = $customerSession;
    }

    public function getPersonalizationHelper()
    {
        return $this->personalizationHelper;
    }

    public function getConfigHelper()
    {
        return $this->configHelper;
    }

    /**
     * @return InsightsClient
     */
    public function getInsightsClient()
    {
        if (!$this->insightsClient) {
            $this->insightsClient = InsightsClient::create(
                $this->configHelper->getApplicationID(),
                $this->configHelper->getAPIKey()
            );
        }

        return $this->insightsClient;
    }

    /**
     * @return UserInsightsClient
     */
    public function getUserInsightsClient()
    {
        if (!$this->userInsightsClient) {
            $this->userInsightsClient = new UserInsightsClient($this->getInsightsClient(), $this->getUserToken());
        }

        return $this->userInsightsClient;
    }

    /**
     * @param null $storeId
     *
     * @return bool
     */
    public function isOrderPlacedTracked($storeId = null)
    {
        return ($this->personalizationHelper->isPersoEnabled($storeId)
                && $this->personalizationHelper->isOrderPlacedTracked($storeId))
            || ($this->configHelper->isClickConversionAnalyticsEnabled($storeId)
                && $this->configHelper->getConversionAnalyticsMode($storeId) === 'place_order');
    }

    /**
     * @param null $storeId
     *
     * @return bool
     */
    public function isAddedToCartTracked($storeId = null)
    {
        return ($this->personalizationHelper->isPersoEnabled($storeId)
                && $this->personalizationHelper->isCartAddTracked($storeId))
            || ($this->configHelper->isClickConversionAnalyticsEnabled($storeId)
                && $this->configHelper->getConversionAnalyticsMode($storeId) === 'add_to_cart');
    }

    /**
     * @return string|null
     */
    private function getUserToken()
    {
        $userToken = $this->cookieManager->getCookie(self::ALGOLIA_CUSTOMER_USER_TOKEN_COOKIE_NAME);
        if (!$userToken) {
            if ($this->customerSession->isLoggedIn()) {
                // set logged in user
                $userToken = $this->setUserToken($this->customerSession->getCustomer());
            } else {
                //return anonymous user
                $userToken = $this->cookieManager->getCookie(self::ALGOLIA_ANON_USER_TOKEN_COOKIE_NAME);
            }
        }

        return $userToken;
    }

    /**
     * @param \Magento\Customer\Model\Customer $customer
     *
     * @return string
     */
    public function setUserToken(\Magento\Customer\Model\Customer $customer)
    {
        $userToken = base64_encode('customer-' . $customer->getEmail() . '-' . $customer->getId());
        $userToken = 'aa-' . preg_replace('/[^A-Za-z0-9\-]/', '', $userToken);

        try {
            $metaData = $this->cookieMetadataFactory->createPublicCookieMetadata()
                ->setDurationOneYear()
                ->setPath('/')
                ->setHttpOnly(false)
                ->setSecure(false);
            $this->cookieManager->setPublicCookie(self::ALGOLIA_CUSTOMER_USER_TOKEN_COOKIE_NAME, $userToken, $metaData);
        } catch (\Exception $e) {
            // return anonymous
            $userToken = $this->cookieManager->getCookie(self::ALGOLIA_ANON_USER_TOKEN_COOKIE_NAME);
        }

        return $userToken;
    }
}
