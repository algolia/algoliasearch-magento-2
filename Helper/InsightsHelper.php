<?php

namespace Algolia\AlgoliaSearch\Helper;

use Algolia\AlgoliaSearch\Api\Insights\EventProcessorInterface;
use Algolia\AlgoliaSearch\Api\Insights\EventProcessorInterfaceFactory;
use Algolia\AlgoliaSearch\Api\InsightsClient;
use Algolia\AlgoliaSearch\Helper\Configuration\PersonalizationHelper;
use Algolia\AlgoliaSearch\Logger\Logger;
use Magento\Customer\Model\Customer;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Stdlib\Cookie\CookieMetadataFactory;
use Magento\Framework\Stdlib\CookieManagerInterface;
use Magento\Store\Model\StoreManagerInterface;

class InsightsHelper
{
    /** @var string  */
    public const ALGOLIA_ANON_USER_TOKEN_COOKIE_NAME = '_ALGOLIA';
    /** @var string  */
    public const ALGOLIA_CUSTOMER_USER_TOKEN_COOKIE_NAME = '_ALGOLIA_MAGENTO_AUTH';
    /** @var string */
    public const ALGOLIA_CUSTOMER_USER_TOKEN_PREFIX = 'aa-';
    /**
     * Up to 129 chars per https://www.algolia.com/doc/rest-api/insights/#method-param-usertoken
     * But capping at legacy 64 chars for backward compat
     */
    /** @var int */
    public const ALGOLIA_USER_TOKEN_MAX_LENGTH = 64;

    /** @var string */
    public const QUOTE_ITEM_QUERY_PARAM = 'algoliasearch_query_param';

    public const CONVERSION_ANALYTICS_MODE_DISABLE = 'disable';
    public const CONVERSION_ANALYTICS_MODE_ALL = 'all';
    // Legacy options - retain in case future granularity is needed
    public const CONVERSION_ANALYTICS_MODE_CART = 'add_to_cart';
    public const CONVERSION_ANALYTICS_MODE_PURCHASE = 'place_order';

    /** @var InsightsClient|null */
    protected ?InsightsClient $insightsClient = null;

    /** @var EventProcessorInterface|null  */
    protected ?EventProcessorInterface $eventProcessor = null;

    public function __construct(
        private readonly ConfigHelper                   $configHelper,
        private readonly PersonalizationHelper          $personalizationHelper,
        private readonly CookieManagerInterface         $cookieManager,
        private readonly CookieMetadataFactory          $cookieMetadataFactory,
        private readonly CustomerSession                $customerSession,
        private readonly EventProcessorInterfaceFactory $eventProcessorFactory,
        private readonly StoreManagerInterface          $storeManager,
        private readonly Logger                         $logger
    )
    {}

    public function getPersonalizationHelper(): PersonalizationHelper
    {
        return $this->personalizationHelper;
    }

    public function getConfigHelper(): ConfigHelper
    {
        return $this->configHelper;
    }

    /**
     * @internal Intended for internal use only - visibility may change at a future time
     * @return InsightsClient
     */
    public function getInsightsClient(): InsightsClient
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
     * @return EventProcessorInterface
     */
    public function getEventProcessor(): EventProcessorInterface
    {
        if (!$this->eventProcessor) {
            $this->eventProcessor = $this->eventProcessorFactory->create([
                'client'                 => $this->getInsightsClient(),
                'userToken'              => $this->getAnonymousUserToken(),
                'authenticatedUserToken' => $this->getAuthenticatedUserToken(),
                'storeManager'           => $this->storeManager
            ]);
        }
        return $this->eventProcessor;
    }

    public function getAnonymousUserToken(): string
    {
        return (string) $this->cookieManager->getCookie(self::ALGOLIA_ANON_USER_TOKEN_COOKIE_NAME) ?? "";
    }

    public function getAuthenticatedUserToken(): string
    {
        $userToken = $this->cookieManager->getCookie(self::ALGOLIA_CUSTOMER_USER_TOKEN_COOKIE_NAME);
        if (!$userToken) {
            if ($this->customerSession->isLoggedIn()) {
                // set logged in user
                $userToken = $this->setAuthenticatedUserToken($this->customerSession->getCustomer());
            }
        }
        return $userToken ?? "";
    }

    /**
     * Generate a pseudo anonymous token compliant with Algolia spec:
     * https://www.algolia.com/doc/api-reference/api-methods/set-authenticated-user-token/#method-param-authenticatedusertoken
     * Uniquely identify the user for this Magento store but obfuscate any PII
     *
     * @param Customer $customer
     * @return string
     */
    public function generateAuthenticatedUserToken(Customer $customer): string
    {
        $hash = hash('sha256', $customer->getEmail());
        $userToken = base64_encode('customer-' . $hash . '-' . $customer->getId());
        $userToken = self::ALGOLIA_CUSTOMER_USER_TOKEN_PREFIX . preg_replace('/[^A-Za-z0-9_=+\/\-]/', '', $userToken);
        $userToken = mb_substr($userToken, 0, self::ALGOLIA_USER_TOKEN_MAX_LENGTH);
        return $userToken;
    }

    /**
     * For a Magento customer, generated an authentication token to be used for personalization and insights
     * and store as a cookie in the browser (requires customer consent)
     * @param Customer $customer
     * @return string|null The user token that was generated for the customer, null if unable to create
     */
    public function setAuthenticatedUserToken(Customer $customer): string|null
    {
        $userToken = $this->generateAuthenticatedUserToken($customer);

        $metaData = $this->cookieMetadataFactory->createPublicCookieMetadata()
            ->setDuration($this->configHelper->getCookieLifetime())
            ->setPath('/')
            ->setHttpOnly(false)
            ->setSecure(false);

        try {
            $this->cookieManager->setPublicCookie(self::ALGOLIA_CUSTOMER_USER_TOKEN_COOKIE_NAME, $userToken, $metaData);
        } catch (LocalizedException $e) {
            $this->logger->error("Error writing Algolia customer cookie: " . $e->getMessage());
            $userToken = null;
        }

        return $userToken;
    }

    /**
     * @param int|null $storeId
     * @return bool
     */
    public function isInsightsEnabled(int $storeId = null): bool
    {
        return $this->configHelper->isClickConversionAnalyticsEnabled($storeId)
            || $this->personalizationHelper->isPersoEnabled($storeId);
    }

    /**
     * A general check - should any kind of tracking be applied to the "place order" operation?
     * @param int|null $storeId
     *
     * @return bool
     */
    public function isOrderPlacedTracked(int $storeId = null): bool
    {
        return $this->personalizationHelper->isPersoEnabled($storeId)
            && $this->personalizationHelper->isOrderPlacedTracked($storeId)
            || $this->isConversionTrackedPlaceOrder($storeId);
    }

    /**
     * Conversion tracking is not the same as perso tracking!
     * Conversions must track usage of the queryID
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isConversionTrackedPlaceOrder(int $storeId = null): bool
    {
        return $this->configHelper->isClickConversionAnalyticsEnabled($storeId)
            && in_array($this->configHelper->getConversionAnalyticsMode($storeId),
                [
                    InsightsHelper::CONVERSION_ANALYTICS_MODE_PURCHASE,
                    InsightsHelper::CONVERSION_ANALYTICS_MODE_ALL
                ]
            );
    }

    /**
     * A general check - should any kind of tracking be applied to the "add to cart" operation?
     *
     * @param int|null $storeId
     *
     * @return bool
     */
    public function isAddedToCartTracked(int $storeId = null): bool
    {
        return $this->personalizationHelper->isPersoEnabled($storeId)
            && $this->personalizationHelper->isCartAddTracked($storeId)
            || $this->isConversionTrackedAddToCart($storeId);
    }

    /**
     * Conversion tracking is not the same as perso tracking!
     * Conversions must track usage of the queryID
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isConversionTrackedAddToCart(int $storeId = null): bool
    {
        return $this->configHelper->isClickConversionAnalyticsEnabled($storeId)
            && in_array($this->configHelper->getConversionAnalyticsMode($storeId),
                [
                    InsightsHelper::CONVERSION_ANALYTICS_MODE_CART,
                    InsightsHelper::CONVERSION_ANALYTICS_MODE_ALL
                ]
            );
    }

    /**
     * @param Customer $customer
     * @return string|null
     * @deprecated This function has been supplanted by setAuthenticatedUserToken for clarity of intent and may be removed in a future release.
     */
    public function setUserToken(Customer $customer): string|null
    {
        return $this->setAuthenticatedUserToken($customer);
    }

    /**
     * Indicates whether we have user consent to use cookies
     * @return bool
     */
    public function getUserAllowedSavedCookie(): bool
    {
        return !$this->configHelper->isCookieRestrictionModeEnabled()
            || !!$this->cookieManager->getCookie($this->configHelper->getDefaultConsentCookieName());
    }
}
