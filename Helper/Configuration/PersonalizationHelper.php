<?php

namespace Algolia\AlgoliaSearch\Helper\Configuration;

use Algolia\AlgoliaSearch\Helper\InsightsHelper;
use Magento\Framework\App\Config\ConfigResource\ConfigInterface as ConfigResourceInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class PersonalizationHelper extends \Magento\Framework\App\Helper\AbstractHelper
{
    // Enable / Disable
    public const IS_PERSO_ENABLED = 'algoliasearch_personalization/personalization_group/enable';
    // View events
    public const VIEW_PRODUCT = 'algoliasearch_personalization/personalization_group/personalization_view_events_group/view_product';
    // Click events
    public const PRODUCT_CLICKED = 'algoliasearch_personalization/personalization_group/personalization_click_events_group/product_clicked';
    public const PRODUCT_CLICKED_SELECTOR = 'algoliasearch_personalization/personalization_group/personalization_click_events_group/product_clicked_selector';
    public const FILTER_CLICKED = 'algoliasearch_personalization/personalization_group/personalization_click_events_group/filter_clicked';
    public const PRODUCT_RECOMMENDED = 'algoliasearch_personalization/personalization_group/personalization_click_events_group/product_recommended_clicked';
    public const PRODUCT_RECOMMENDED_SELECTOR = 'algoliasearch_personalization/personalization_group/personalization_click_events_group/product_recommended_clicked_selector';

    // Conversion events
    public const WISHLIST_ADD = 'algoliasearch_personalization/personalization_group/personalization_conversion_events_group/conversion_wishist_add';
    public const WISHLIST_ADD_SELECTOR = 'algoliasearch_personalization/personalization_group/personalization_conversion_events_group/conversion_wishist_add_selector';
    public const CART_ADD = 'algoliasearch_personalization/personalization_group/personalization_conversion_events_group/conversion_cart_add';
    public const CART_ADD_SELECTOR = 'algoliasearch_personalization/personalization_group/personalization_conversion_events_group/conversion_cart_add_selector';
    public const ORDER_PLACED = 'algoliasearch_personalization/personalization_group/personalization_conversion_events_group/conversion_order_placed';

    private ScopeConfigInterface $configInterface;

    private ConfigResourceInterface $configResourceInterface;

    /**
     * @param \Magento\Framework\App\Helper\Context $context
     * @param ScopeConfigInterface $configInterface
     * @param ConfigResourceInterface $configResourceInterface
     */
    public function __construct(
        \Magento\Framework\App\Helper\Context $context,
        ScopeConfigInterface                  $configInterface,
        ConfigResourceInterface               $configResourceInterface
    ) {
        $this->configInterface = $configInterface;
        $this->configResourceInterface = $configResourceInterface;
        parent::__construct($context);
    }

    /**
     * @param int|null $storeId
     *
     * @return bool
     */
    public function isPersoEnabled(int $storeId = null): bool
    {
        return $this->configInterface->isSetFlag(self::IS_PERSO_ENABLED, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * @param int|null $storeId
     *
     * @return void
     */
    public function disablePerso(int $storeId = null): void
    {
        $this->configResourceInterface->saveConfig(self::IS_PERSO_ENABLED, 0, 'default', 0);
    }

    /**
     * @param int|null $storeId
     *
     * @return bool
     */
    public function isViewProductTracked(int $storeId = null): bool
    {
        return $this->configInterface->isSetFlag(self::VIEW_PRODUCT, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * @param int|null $storeId
     *
     * @return bool
     */
    public function isProductClickedTracked(int $storeId = null): bool
    {
        return $this->configInterface->isSetFlag(self::PRODUCT_CLICKED, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * @param int|null $storeId
     *
     * @return string
     */
    public function getProductClickedSelector(int $storeId = null): string
    {
        return $this->configInterface->getValue(self::PRODUCT_CLICKED_SELECTOR, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * @param int|null $storeId
     *
     * @return bool
     */
    public function isFilterClickedTracked(int $storeId = null): bool
    {
        return $this->configInterface->isSetFlag(self::FILTER_CLICKED, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * @param int|null $storeId
     *
     * @return bool
     */
    public function isWishlistAddTracked(int $storeId = null): bool
    {
        return $this->configInterface->isSetFlag(self::WISHLIST_ADD, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * @param int|null $storeId
     *
     * @return string
     */
    public function getWishlistAddSelector(int $storeId = null): string
    {
        return $this->configInterface->getValue(self::WISHLIST_ADD_SELECTOR, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * @param int|null $storeId
     *
     * @return bool
     */
    public function isProductRecommendedTracked(int $storeId = null): bool
    {
        return $this->configInterface->isSetFlag(self::PRODUCT_RECOMMENDED, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * @param int|null $storeId
     *
     * @return string
     */
    public function getProductRecommendedSelector(int $storeId = null): string
    {
        return $this->configInterface->getValue(self::PRODUCT_RECOMMENDED_SELECTOR, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * @param int|null $storeId
     *
     * @return bool
     */
    public function isCartAddTracked(int $storeId = null): bool
    {
        return $this->configInterface->isSetFlag(self::CART_ADD, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * @param int|null $storeId
     *
     * @return string
     */
    public function getCartAddSelector(int $storeId = null): string
    {
        return $this->configInterface->getValue(self::CART_ADD_SELECTOR, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * @param int|null $storeId
     *
     * @return bool
     */
    public function isOrderPlacedTracked(int $storeId = null): bool
    {
        return $this->configInterface->isSetFlag(self::ORDER_PLACED, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * @return string|null
     * @internal Algolia user tokens can now be anonymous or authenticated. This function should no longer be used.
     */
    public function getUserToken(): ?string
    {
        return $this->_request->getCookie(InsightsHelper::ALGOLIA_ANON_USER_TOKEN_COOKIE_NAME);
    }
}
