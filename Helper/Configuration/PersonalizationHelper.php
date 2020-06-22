<?php

namespace Algolia\AlgoliaSearch\Helper\Configuration;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class PersonalizationHelper extends \Magento\Framework\App\Helper\AbstractHelper
{
    // Enable / Disable
    const IS_PERSO_ENABLED = 'algoliasearch_personalization/personalization_group/enable';
    // View events
    const VIEW_PRODUCT = 'algoliasearch_personalization/personalization_group/personalization_view_events_group/view_product';
    // Click events
    const PRODUCT_CLICKED = 'algoliasearch_personalization/personalization_group/personalization_click_events_group/product_clicked';
    const PRODUCT_CLICKED_SELECTOR = 'algoliasearch_personalization/personalization_group/personalization_click_events_group/product_clicked_selector';
    const FILTER_CLICKED = 'algoliasearch_personalization/personalization_group/personalization_click_events_group/filter_clicked';
    const PRODUCT_RECOMMENDED = 'algoliasearch_personalization/personalization_group/personalization_click_events_group/product_recommended_clicked';
    const PRODUCT_RECOMMENDED_SELECTOR = 'algoliasearch_personalization/personalization_group/personalization_click_events_group/product_recommended_clicked_selector';

    // Conversion events
    const WISHLIST_ADD = 'algoliasearch_personalization/personalization_group/personalization_conversion_events_group/conversion_wishist_add';
    const WISHLIST_ADD_SELECTOR = 'algoliasearch_personalization/personalization_group/personalization_conversion_events_group/conversion_wishist_add_selector';
    const CART_ADD = 'algoliasearch_personalization/personalization_group/personalization_conversion_events_group/conversion_cart_add';
    const CART_ADD_SELECTOR = 'algoliasearch_personalization/personalization_group/personalization_conversion_events_group/conversion_cart_add_selector';
    const ORDER_PLACED = 'algoliasearch_personalization/personalization_group/personalization_conversion_events_group/conversion_order_placed';

    const ALGOLIA_USER_COOKIE = '_ALGOLIA';

    /** @var ScopeConfigInterface */
    private $configInterface;

    public function __construct(
        \Magento\Framework\App\Helper\Context $context,
        ScopeConfigInterface $configInterface
    ) {
        $this->configInterface = $configInterface;
        parent::__construct($context);
    }

    /**
     * @param int|null $storeId
     *
     * @return bool
     */
    public function isPersoEnabled($storeId = null)
    {
        return $this->configInterface->isSetFlag(self::IS_PERSO_ENABLED, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * @param int|null $storeId
     *
     * @return bool
     */
    public function isViewProductTracked($storeId = null)
    {
        return $this->configInterface->isSetFlag(self::VIEW_PRODUCT, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * @param int|null $storeId
     *
     * @return bool
     */
    public function isProductClickedTracked($storeId = null)
    {
        return $this->configInterface->isSetFlag(self::PRODUCT_CLICKED, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * @param int|null $storeId
     *
     * @return string
     */
    public function getProductClickedSelector($storeId = null)
    {
        return $this->configInterface->getValue(self::PRODUCT_CLICKED_SELECTOR, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * @param int|null $storeId
     *
     * @return bool
     */
    public function isFilterClickedTracked($storeId = null)
    {
        return $this->configInterface->isSetFlag(self::FILTER_CLICKED, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * @param int|null $storeId
     *
     * @return bool
     */
    public function isWishlistAddTracked($storeId = null)
    {
        return $this->configInterface->isSetFlag(self::WISHLIST_ADD, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * @param int|null $storeId
     *
     * @return string
     */
    public function getWishlistAddSelector($storeId = null)
    {
        return $this->configInterface->getValue(self::WISHLIST_ADD_SELECTOR, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * @param int|null $storeId
     *
     * @return bool
     */
    public function isProductRecommendedTracked($storeId = null)
    {
        return $this->configInterface->isSetFlag(self::PRODUCT_RECOMMENDED, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * @param int|null $storeId
     *
     * @return string
     */
    public function getProductRecommendedSelector($storeId = null)
    {
        return $this->configInterface->getValue(self::PRODUCT_RECOMMENDED_SELECTOR, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * @param int|null $storeId
     *
     * @return bool
     */
    public function isCartAddTracked($storeId = null)
    {
        return $this->configInterface->isSetFlag(self::CART_ADD, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * @param int|null $storeId
     *
     * @return string
     */
    public function getCartAddSelector($storeId = null)
    {
        return $this->configInterface->getValue(self::CART_ADD_SELECTOR, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * @param int|null $storeId
     *
     * @return bool
     */
    public function isOrderPlacedTracked($storeId = null)
    {
        return $this->configInterface->isSetFlag(self::ORDER_PLACED, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * @return string|null
     */
    public function getUserToken()
    {
        return $this->_request->getCookie(self::ALGOLIA_USER_COOKIE);
    }
}
