<?php

namespace Algolia\AlgoliaSearch\Helper;

use Algolia\AlgoliaSearch\Api\Product\ProductRecordFieldsInterface;
use Algolia\AlgoliaSearch\Api\Product\ReplicaManagerInterface;
use Algolia\AlgoliaSearch\Helper\Configuration\AutocompleteHelper;
use Algolia\AlgoliaSearch\Helper\Configuration\InstantSearchHelper;
use Algolia\AlgoliaSearch\Helper\Configuration\QueueHelper;
use Algolia\AlgoliaSearch\Service\AlgoliaConnector;
use Algolia\AlgoliaSearch\Service\Serializer;
use Magento\Cookie\Helper\Cookie as CookieHelper;
use Magento\Customer\Api\GroupExcludedWebsiteRepositoryInterface;
use Magento\Customer\Model\ResourceModel\Group\Collection as GroupCollection;
use Magento\Directory\Model\Currency as DirCurrency;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\DataObject;
use Magento\Framework\Locale\Currency;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Weee\Helper\Data as WeeeHelper;

class ConfigHelper
{
    // --- Credentials & Basic Setup --- //

    public const ENABLE_FRONTEND = 'algoliasearch_credentials/credentials/enable_frontend';
    public const LOGGING_ENABLED = 'algoliasearch_credentials/credentials/debug';
    public const APPLICATION_ID = 'algoliasearch_credentials/credentials/application_id';
    public const API_KEY = 'algoliasearch_credentials/credentials/api_key';
    public const SEARCH_ONLY_API_KEY = 'algoliasearch_credentials/credentials/search_only_api_key';
    public const INDEX_PREFIX = 'algoliasearch_credentials/credentials/index_prefix';
    public const COOKIE_DEFAULT_CONSENT_COOKIE_NAME = 'algoliasearch_credentials/algolia_cookie_configuration/default_consent_cookie_name';
    public const ALLOW_COOKIE_BUTTON_SELECTOR = 'algoliasearch_credentials/algolia_cookie_configuration/allow_cookie_button_selector';
    public const ALGOLIA_COOKIE_DURATION = 'algoliasearch_credentials/algolia_cookie_configuration/cookie_duration';

    // --- Products --- //

    public const PRODUCT_ATTRIBUTES = 'algoliasearch_products/products/product_additional_attributes';
    public const PRODUCT_CUSTOM_RANKING = 'algoliasearch_products/products/custom_ranking_product_attributes';
    public const USE_ADAPTIVE_IMAGE = 'algoliasearch_products/products/use_adaptive_image';
    public const ENABLE_VISUAL_MERCHANDISING = 'algoliasearch_products/products/enable_visual_merchandising';
    public const CATEGORY_PAGE_ID_ATTRIBUTE_NAME = 'algoliasearch_products/products/category_page_id_attribute_name';
    public const INCLUDE_NON_VISIBLE_PRODUCTS_IN_INDEX = 'algoliasearch_products/products/include_non_visible_products_in_index';

    // --- Categories --- //

    public const CATEGORY_ATTRIBUTES = 'algoliasearch_categories/categories/category_additional_attributes';
    public const CATEGORY_CUSTOM_RANKING = 'algoliasearch_categories/categories/custom_ranking_category_attributes';
    public const SHOW_CATS_NOT_INCLUDED_IN_NAV = 'algoliasearch_categories/categories/show_cats_not_included_in_navigation';
    public const INDEX_EMPTY_CATEGORIES = 'algoliasearch_categories/categories/index_empty_categories';
    public const CATEGORY_SEPARATOR = 'algoliasearch_categories/categories/category_separator';

    // --- Recommend Products Settings --- //

    public const IS_RECOMMEND_FREQUENTLY_BOUGHT_TOGETHER_ENABLED = 'algoliasearch_recommend/recommend/frequently_bought_together/is_frequently_bought_together_enabled';
    public const IS_RECOMMEND_RELATED_PRODUCTS_ENABLED = 'algoliasearch_recommend/recommend/related_product/is_related_products_enabled';
    public const IS_RECOMMEND_FREQUENTLY_BOUGHT_TOGETHER_ENABLED_ON_CART_PAGE = 'algoliasearch_recommend/recommend/frequently_bought_together/is_frequently_bought_together_enabled_in_cart_page';
    public const IS_RECOMMEND_RELATED_PRODUCTS_ENABLED_ON_CART_PAGE = 'algoliasearch_recommend/recommend/related_product/is_related_products_enabled_in_cart_page';
    protected const NUM_OF_RECOMMEND_FREQUENTLY_BOUGHT_TOGETHER_PRODUCTS = 'algoliasearch_recommend/recommend/frequently_bought_together/num_of_frequently_bought_together_products';
    protected const NUM_OF_RECOMMEND_RELATED_PRODUCTS = 'algoliasearch_recommend/recommend/related_product/num_of_related_products';
    protected const IS_REMOVE_RELATED_PRODUCTS_BLOCK = 'algoliasearch_recommend/recommend/related_product/is_remove_core_related_products_block';
    protected const IS_REMOVE_UPSELL_PRODUCTS_BLOCK = 'algoliasearch_recommend/recommend/frequently_bought_together/is_remove_core_upsell_products_block';
    public const IS_RECOMMEND_TRENDING_ITEMS_ENABLED = 'algoliasearch_recommend/recommend/trends_item/is_trending_items_enabled';
    protected const IS_RECOMMEND_LOOKING_SIMILAR_ENABLED = 'algoliasearch_recommend/recommend/looking_similar/is_looking_similar_enabled';
    protected const NUM_OF_LOOKING_SIMILAR = 'algoliasearch_recommend/recommend/looking_similar/num_of_products';
    protected const NUM_OF_TRENDING_ITEMS = 'algoliasearch_recommend/recommend/trends_item/num_of_trending_items';
    protected const TREND_ITEMS_FACET_NAME = 'algoliasearch_recommend/recommend/trends_item/facet_name';
    protected const TREND_ITEMS_FACET_VALUE = 'algoliasearch_recommend/recommend/trends_item/facet_value';
    public const IS_TREND_ITEMS_ENABLED_IN_PDP = 'algoliasearch_recommend/recommend/trends_item/is_trending_items_enabled_on_pdp';
    public const IS_TREND_ITEMS_ENABLED_IN_SHOPPING_CART = 'algoliasearch_recommend/recommend/trends_item/is_trending_items_enabled_on_cart_page';
    protected const IS_ADDTOCART_ENABLED_IN_FREQUENTLY_BOUGHT_TOGETHER = 'algoliasearch_recommend/recommend/frequently_bought_together/is_addtocart_enabled';
    protected const IS_ADDTOCART_ENABLED_IN_RELATED_PRODUCTS = 'algoliasearch_recommend/recommend/related_product/is_addtocart_enabled';
    protected const IS_ADDTOCART_ENABLED_IN_TRENDS_ITEM = 'algoliasearch_recommend/recommend/trends_item/is_addtocart_enabled';
    protected const IS_ADDTOCART_ENABLED_IN_LOOKING_SIMILAR = 'algoliasearch_recommend/recommend/looking_similar/is_addtocart_enabled';
    public const IS_LOOKING_SIMILAR_ENABLED_IN_PDP = 'algoliasearch_recommend/recommend/looking_similar/is_looking_similar_enabled_on_pdp';
    public const IS_LOOKING_SIMILAR_ENABLED_IN_SHOPPING_CART = 'algoliasearch_recommend/recommend/looking_similar/is_looking_similar_enabled_on_cart_page';
    protected const LOOKING_SIMILAR_TITLE = 'algoliasearch_recommend/recommend/looking_similar/title';
    protected const FREQUENTLY_BOUGHT_TOGETHER_TITLE = 'algoliasearch_recommend/recommend/frequently_bought_together/title';
    protected const RELATED_PRODUCTS_TITLE = 'algoliasearch_recommend/recommend/related_product/title';
    protected const TRENDING_ITEMS_TITLE = 'algoliasearch_recommend/recommend/trends_item/title';

    // --- Images --- //

    public const XML_PATH_IMAGE_WIDTH = 'algoliasearch_images/image/width';
    public const XML_PATH_IMAGE_HEIGHT = 'algoliasearch_images/image/height';
    public const XML_PATH_IMAGE_TYPE = 'algoliasearch_images/image/type';

    // --- Indexing Manager --- //

    public const ENABLE_INDEXING = 'algoliasearch_indexing_manager/algolia_indexing/enable_indexing';
    public const ENABLE_QUERY_SUGGESTIONS_INDEX = 'algoliasearch_indexing_manager/algolia_indexing/enable_query_suggestions_index';
    public const ENABLE_PAGES_INDEX = 'algoliasearch_indexing_manager/algolia_indexing/enable_pages_index';
    public const ENABLE_INDEXER_PRODUCTS = 'algoliasearch_indexing_manager/full_indexing/products';
    public const ENABLE_INDEXER_CATEGORIES = 'algoliasearch_indexing_manager/full_indexing/categories';
    public const ENABLE_INDEXER_PAGES = 'algoliasearch_indexing_manager/full_indexing/pages';
    public const ENABLE_INDEXER_SUGGESTIONS = 'algoliasearch_indexing_manager/full_indexing/suggestions';
    public const ENABLE_INDEXER_ADDITIONAL_SECTIONS = 'algoliasearch_indexing_manager/full_indexing/additional_sections';
    public const ENABLE_INDEXER_DELETE_PRODUCTS = 'algoliasearch_indexing_manager/full_indexing/delete_products';
    public const ENABLE_INDEXER_QUEUE = 'algoliasearch_indexing_manager/full_indexing/queue';

    // --- Click & Conversion Analytics --- //

    public const CC_ANALYTICS_ENABLE = 'algoliasearch_cc_analytics/cc_analytics_group/enable';
    public const CC_ANALYTICS_IS_SELECTOR = 'algoliasearch_cc_analytics/cc_analytics_group/is_selector';
    public const CC_CONVERSION_ANALYTICS_MODE = 'algoliasearch_cc_analytics/cc_analytics_group/conversion_analytics_mode';
    public const CC_ADD_TO_CART_SELECTOR = 'algoliasearch_cc_analytics/cc_analytics_group/add_to_cart_selector';

    // --- Google Analytics --- //

    public const GA_ENABLE = 'algoliasearch_analytics/analytics_group/enable';
    public const GA_DELAY = 'algoliasearch_analytics/analytics_group/delay';
    public const GA_TRIGGER_ON_UI_INTERACTION = 'algoliasearch_analytics/analytics_group/trigger_on_ui_interaction';
    public const GA_PUSH_INITIAL_SEARCH = 'algoliasearch_analytics/analytics_group/push_initial_search';

    // --- Advanced --- //

    public const REMOVE_IF_NO_RESULT = 'algoliasearch_advanced/advanced/remove_words_if_no_result';
    public const PARTIAL_UPDATES = 'algoliasearch_advanced/advanced/partial_update';
    public const CUSTOMER_GROUPS_ENABLE = 'algoliasearch_advanced/advanced/customer_groups_enable';
    public const FPT_ENABLE = 'algoliasearch_advanced/advanced/fpt_enable';
    public const REMOVE_PUB_DIR_IN_URL = 'algoliasearch_advanced/advanced/remove_pub_dir_in_url';
    public const REMOVE_BRANDING = 'algoliasearch_advanced/advanced/remove_branding';
    public const IDX_PRODUCT_ON_CAT_PRODUCTS_UPD = 'algoliasearch_advanced/advanced/index_product_on_category_products_update';
    public const PREVENT_BACKEND_RENDERING = 'algoliasearch_advanced/advanced/prevent_backend_rendering';
    public const PREVENT_BACKEND_RENDERING_DISPLAY_MODE =
        'algoliasearch_advanced/advanced/prevent_backend_rendering_display_mode';
    public const BACKEND_RENDERING_ALLOWED_USER_AGENTS =
        'algoliasearch_advanced/advanced/backend_rendering_allowed_user_agents';
    public const NON_CASTABLE_ATTRIBUTES = 'algoliasearch_advanced/advanced/non_castable_attributes';
    public const NUMBER_OF_ELEMENT_BY_PAGE = 'algoliasearch_advanced/advanced/number_of_element_by_page';
    public const MAX_RECORD_SIZE_LIMIT = 'algoliasearch_advanced/advanced/max_record_size_limit';
    public const ANALYTICS_REGION = 'algoliasearch_advanced/advanced/analytics_region';
    public const CONNECTION_TIMEOUT = 'algoliasearch_advanced/advanced/connection_timeout';
    public const READ_TIMEOUT = 'algoliasearch_advanced/advanced/read_timeout';
    public const WRITE_TIMEOUT = 'algoliasearch_advanced/advanced/write_timeout';
    protected const FORWARD_TO_REPLICAS = 'algoliasearch_advanced/advanced/forward_to_replicas';
    public const AUTO_PRICE_INDEXING_ENABLED = 'algoliasearch_advanced/advanced/auto_price_indexing';
    public const PROFILER_ENABLED = 'algoliasearch_advanced/advanced/enable_profiler';

    // Indexing Queue advanced settings
    public const ENHANCED_QUEUE_ARCHIVE = 'algoliasearch_advanced/queue/enhanced_archive';
    public const ARCHIVE_LOG_CLEAR_LIMIT = 'algoliasearch_advanced/queue/archive_clear_limit';

    // --- Extra index settings --- //

    public const EXTRA_SETTINGS_PRODUCTS = 'algoliasearch_extra_settings/extra_settings/products_extra_settings';
    public const EXTRA_SETTINGS_CATEGORIES = 'algoliasearch_extra_settings/extra_settings/categories_extra_settings';
    public const EXTRA_SETTINGS_PAGES = 'algoliasearch_extra_settings/extra_settings/pages_extra_settings';
    public const EXTRA_SETTINGS_SUGGESTIONS = 'algoliasearch_extra_settings/extra_settings/suggestions_extra_settings';
    public const EXTRA_SETTINGS_ADDITIONAL_SECTIONS =
        'algoliasearch_extra_settings/extra_settings/additional_sections_extra_settings';

    // --- Magento Core --- //

    public const SHOW_OUT_OF_STOCK = 'cataloginventory/options/show_out_of_stock';
    public const USE_SECURE_IN_FRONTEND = 'web/secure/use_in_frontend';
    public const MAGENTO_DEFAULT_CACHE_TIME = 'system/full_page_cache/ttl';
    public const COOKIE_LIFETIME = 'web/cookie/cookie_lifetime';

    public function __construct(
        protected \Magento\Framework\App\Config\ScopeConfigInterface    $configInterface,
        protected \Magento\Framework\App\Config\Storage\WriterInterface $configWriter,
        protected StoreManagerInterface                                 $storeManager,
        protected Currency                                              $currency,
        protected DirCurrency                                           $dirCurrency,
        protected DirectoryList                                         $directoryList,
        protected \Magento\Framework\Module\ResourceInterface           $moduleResource,
        protected \Magento\Framework\App\ProductMetadataInterface       $productMetadata,
        protected \Magento\Framework\Event\ManagerInterface             $eventManager,
        protected Serializer                                            $serializer,
        protected GroupCollection                                       $groupCollection,
        protected GroupExcludedWebsiteRepositoryInterface               $groupExcludedWebsiteRepository,
        protected CookieHelper                                          $cookieHelper,
        protected AutocompleteHelper                                    $autocompleteConfig,
        protected InstantSearchHelper                                   $instantSearchConfig,
        protected QueueHelper                                           $queueHelper,
        protected WeeeHelper                                            $weeeHelper
    )
    {}

    // --- Credentials & Basic Setup --- //

    /**
     * @param $storeId
     * @return bool
     * @deprecated Use Algolia\AlgoliaSearch\Service\AlgoliaCredentialsManager instead
     */
    public function credentialsAreConfigured($storeId = null): bool
    {
        return $this->getApplicationID($storeId) &&
            $this->getAPIKey($storeId) &&
            $this->getSearchOnlyAPIKey($storeId);
    }

    /**
     * @param $storeId
     * @return mixed'
     */
    public function getApplicationID($storeId = null)
    {
        return $this->configInterface->getValue(self::APPLICATION_ID, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * @param $storeId
     * @return mixed
     */
    public function getAPIKey($storeId = null)
    {
        return $this->configInterface->getValue(self::API_KEY, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * @param $storeId
     * @return mixed
     */
    public function getSearchOnlyAPIKey($storeId = null)
    {
        return $this->configInterface->getValue(self::SEARCH_ONLY_API_KEY, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * @param int|null $storeId
     * @return string
     */
    public function getIndexPrefix(?int $storeId = null): string
    {
        return (string) $this->configInterface->getValue(self::INDEX_PREFIX, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * @param $storeId
     * @return bool
     */
    public function isEnabledFrontEnd($storeId = null): bool
    {
        return $this->configInterface->isSetFlag(self::ENABLE_FRONTEND, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * @param $storeId
     * @return bool
     */
    public function isLoggingEnabled($storeId = null): bool
    {
        return $this->configInterface->isSetFlag(self::LOGGING_ENABLED, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * Used for front end search API key generation
     * @param $groupId
     * @return array
     */
    public function getAttributesToFilter($groupId)
    {
        $transport = new DataObject();
        $this->eventManager->dispatch(
            'algolia_get_attributes_to_filter',
            ['filter_object' => $transport, 'customer_group_id' => $groupId]
        );
        $attributes = $transport->getData();
        $attributes = array_unique($attributes);
        $attributes = array_values($attributes);
        return count($attributes) ? ['filters' => implode(' AND ', $attributes)] : [];
    }

    // Algolia Cookie Configuration
    /**
     * @param $storeId
     * @return mixed
     */
    public function getDefaultConsentCookieName($storeId = null)
    {
        return $this->configInterface->getValue(
            self::COOKIE_DEFAULT_CONSENT_COOKIE_NAME,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * @param $storeId
     * @return mixed
     */
    public function getAllowCookieButtonSelector($storeId = null)
    {
        return $this->configInterface->getValue(
            self::ALLOW_COOKIE_BUTTON_SELECTOR,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * @param $storeId
     * @return mixed
     */
    public function getAlgoliaCookieDuration($storeId = null)
    {
        return $this->configInterface->getValue(
            self::ALGOLIA_COOKIE_DURATION,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    // --- Products --- //

    /**
     * @param $storeId
     * @return array
     */
    public function getProductAdditionalAttributes($storeId = null)
    {
        $attributes = $this->getProductAttributesList($storeId);

        $facets = $this->serializer->unserialize($this->configInterface->getValue(
            self::FACETS,
            ScopeInterface::SCOPE_STORE,
            $storeId
        ));
        $attributes = $this->addIndexableAttributes($attributes, $facets, '0');

        $sorts = $this->serializer->unserialize($this->configInterface->getValue(
            self::SORTING_INDICES,
            ScopeInterface::SCOPE_STORE,
            $storeId
        ));
        $attributes = $this->addIndexableAttributes($attributes, $sorts, '0');

        $customRankings = $this->serializer->unserialize($this->configInterface->getValue(
            self::PRODUCT_CUSTOM_RANKING,
            ScopeInterface::SCOPE_STORE,
            $storeId
        ));
        $customRankings = $customRankings ?: [];
        $customRankings = array_filter($customRankings, fn($customRanking) => $customRanking['attribute'] !== 'custom_attribute');
        $attributes = $this->addIndexableAttributes($attributes, $customRankings, '0', '0');
        if (is_array($attributes)) {
            return $attributes;
        }
        return [];
    }

    /**
     * @param $attributes
     * @param $addedAttributes
     * @param $searchable
     * @param $retrievable
     * @param $indexNoValue
     * @return mixed
     */
    protected function addIndexableAttributes(
        $attributes,
        $addedAttributes,
        $searchable = '1',
        $retrievable = '1',
        $indexNoValue = '1'
    ) {
        foreach ((array)$addedAttributes as $addedAttribute) {
            foreach ((array)$attributes as $attribute) {
                if ($addedAttribute['attribute'] === $attribute['attribute']) {
                    continue 2;
                }
            }
            $attributes[] = [
                'attribute' => $addedAttribute['attribute'],
                'searchable' => $searchable,
                'retrievable' => $retrievable,
                'index_no_value' => $indexNoValue,
            ];
        }
        return $attributes;
    }

    /**
     * @param $storeId
     * @return array
     */
    public function getProductCustomRanking($storeId = null)
    {
        $attrs = $this->serializer->unserialize($this->getRawProductCustomRanking($storeId));
        if (is_array($attrs)) {
            return $attrs;
        }
        return [];
    }

    /**
     * @param $storeId
     * @return mixed
     */
    public function getRawProductCustomRanking($storeId = null)
    {
        return $this->configInterface->getValue(
            self::PRODUCT_CUSTOM_RANKING,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * @param $storeId
     * @return bool
     */
    public function useAdaptiveImage($storeId = null): bool
    {
        return $this->configInterface->isSetFlag(self::USE_ADAPTIVE_IMAGE, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * @param $storeId
     * @return bool
     */
    public function isVisualMerchEnabled($storeId = null): bool
    {
        return $this->configInterface->isSetFlag(self::ENABLE_VISUAL_MERCHANDISING, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * @param $storeId
     * @return string
     */
    public function getCategoryPageIdAttributeName($storeId = null): string
    {
        return (string) $this->configInterface->getValue(self::CATEGORY_PAGE_ID_ATTRIBUTE_NAME, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * Returns config flag
     *
     * @param $storeId
     * @return bool
     */
    public function includeNonVisibleProductsInIndex($storeId = null): bool
    {
        return $this->configInterface->isSetFlag(
            self::INCLUDE_NON_VISIBLE_PRODUCTS_IN_INDEX,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * NOTE: This method is currently only used in integration tests and was removed from the general implementation
     * TODO: Evaluate use cases with product permissions where we may need to restore this functionality
     * @param $groupId
     * @return array
     */
    public function getAttributesToRetrieve($groupId)
    {
        if (false === $this->isCustomerGroupsEnabled()) {
            return [];
        }
        $attributes = [];
        foreach ($this->getProductAdditionalAttributes() as $attribute) {
            if ($attribute['attribute'] !== 'price' && $attribute['retrievable'] === '1') {
                $attributes[] = $attribute['attribute'];
            }
        }
        foreach ($this->getCategoryAdditionalAttributes() as $attribute) {
            if ($attribute['retrievable'] === '1') {
                $attributes[] = $attribute['attribute'];
            }
        }
        $attributes = array_merge($attributes, [
            AlgoliaConnector::ALGOLIA_API_OBJECT_ID,
            'name',
            'url',
            ProductRecordFieldsInterface::VISIBILITY_SEARCH,
            ProductRecordFieldsInterface::VISIBILITY_CATALOG,
            'categories',
            'categories_without_path',
            'thumbnail_url',
            'image_url',
            'images_data',
            'in_stock',
            'type_id',
            'value',
            'query', # suggestions
            'path', # categories
            'default_bundle_options',
        ]);
        $currencies = $this->dirCurrency->getConfigAllowCurrencies();
        foreach ($currencies as $currency) {
            $attributes[] = 'price.' . $currency . '.default';
            $attributes[] = 'price.' . $currency . '.default_tier';
            $attributes[] = 'price.' . $currency . '.default_max';
            $attributes[] = 'price.' . $currency . '.default_formated';
            $attributes[] = 'price.' . $currency . '.default_original_formated';
            $attributes[] = 'price.' . $currency . '.default_tier_formated';
            $attributes[] = 'price.' . $currency . '.group_' . $groupId;
            $attributes[] = 'price.' . $currency . '.group_' . $groupId . '_tier';
            $attributes[] = 'price.' . $currency . '.group_' . $groupId . '_max';
            $attributes[] = 'price.' . $currency . '.group_' . $groupId . '_formated';
            $attributes[] = 'price.' . $currency . '.group_' . $groupId . '_tier_formated';
            $attributes[] = 'price.' . $currency . '.group_' . $groupId . '_original_formated';
            $attributes[] = 'price.' . $currency . '.special_from_date';
            $attributes[] = 'price.' . $currency . '.special_to_date';
        }
        $transport = new DataObject($attributes);
        $this->eventManager->dispatch('algolia_get_retrievable_attributes', ['attributes' => $transport]);
        $attributes = $transport->getData();
        $attributes = array_unique($attributes);
        $attributes = array_values($attributes);
        return ['attributesToRetrieve' => $attributes];
    }

    /**
     * @param int|null $storeId
     * @return bool
     */
    public function useVirtualReplica(?int $storeId = null): bool
    {
        return (bool) count(array_filter(
            $this->getSorting($storeId),
            fn($sort) => $sort[ReplicaManagerInterface::SORT_KEY_VIRTUAL_REPLICA]
        ));
    }

    /**
     * @param $attributes
     * @param $attributeName
     * @return bool
     */
    public function isAttributeInList($attributes, $attributeName): bool
    {
        foreach ($attributes as $attr) {
            if ($attr['attribute'] === $attributeName) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param $storeId
     * @return mixed
     */
    public function getProductAttributesList($storeId = null)
    {
        return $this->serializer->unserialize($this->configInterface->getValue(
            self::PRODUCT_ATTRIBUTES,
            ScopeInterface::SCOPE_STORE,
            $storeId
        ));
    }

    // --- Categories --- //
    /**
     * @param $storeId
     * @return array
     */
    public function getCategoryAdditionalAttributes($storeId = null)
    {
        $attributes = $this->serializer->unserialize($this->configInterface->getValue(
            self::CATEGORY_ATTRIBUTES,
            ScopeInterface::SCOPE_STORE,
            $storeId
        ));
        $customRankings = $this->serializer->unserialize($this->configInterface->getValue(
            self::CATEGORY_CUSTOM_RANKING,
            ScopeInterface::SCOPE_STORE,
            $storeId
        ));
        $customRankings = $customRankings ?: [];
        $customRankings = array_filter($customRankings, fn($customRanking) => $customRanking['attribute'] !== 'custom_attribute');
        $attributes = $this->addIndexableAttributes($attributes, $customRankings, '0', '0');
        if (is_array($attributes)) {
            return $attributes;
        }
        return [];
    }

    /**
     * @param $storeId
     * @return array
     */
    public function getCategoryCustomRanking($storeId = null): array
    {
        $attrs = $this->serializer->unserialize($this->configInterface->getValue(
            self::CATEGORY_CUSTOM_RANKING,
            ScopeInterface::SCOPE_STORE,
            $storeId
        ));
        if (is_array($attrs)) {
            return $attrs;
        }
        return [];
    }

    /**
     * @param $storeId
     * @return bool
     */
    public function showCatsNotIncludedInNavigation($storeId = null)
    {
        return $this->configInterface->isSetFlag(
            self::SHOW_CATS_NOT_INCLUDED_IN_NAV,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * @param $storeId
     * @return bool
     */
    public function shouldIndexEmptyCategories($storeId = null): bool
    {
        return $this->configInterface->isSetFlag(self::INDEX_EMPTY_CATEGORIES, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * @param $storeId
     * @return string
     */
    public function getCategorySeparator($storeId = null): string
    {
        return (string) $this->configInterface->getValue(self::CATEGORY_SEPARATOR, ScopeInterface::SCOPE_STORE, $storeId);
    }

    // --- Recommend Product Settings --- //

    /**
     * @param $storeId
     * @return bool
     */
    public function isRecommendFrequentlyBroughtTogetherEnabled($storeId = null): bool
    {
        return $this->configInterface->isSetFlag(self::IS_RECOMMEND_FREQUENTLY_BOUGHT_TOGETHER_ENABLED, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * @param $storeId
     * @return bool
     */
    public function isRecommendRelatedProductsEnabled($storeId = null): bool
    {
        return $this->configInterface->isSetFlag(self::IS_RECOMMEND_RELATED_PRODUCTS_ENABLED, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * @param $storeId
     * @return bool
     */
    public function isRecommendFrequentlyBroughtTogetherEnabledOnCartPage($storeId = null): bool
    {
        return $this->configInterface->isSetFlag(self::IS_RECOMMEND_FREQUENTLY_BOUGHT_TOGETHER_ENABLED_ON_CART_PAGE, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * @param $storeId
     * @return bool
     */
    public function isRecommendRelatedProductsEnabledOnCartPage($storeId = null): bool
    {
        return $this->configInterface->isSetFlag(self::IS_RECOMMEND_RELATED_PRODUCTS_ENABLED_ON_CART_PAGE, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * @param $storeId
     * @return bool
     */
    public function isRemoveCoreRelatedProductsBlock($storeId = null): bool
    {
        return $this->configInterface->isSetFlag(self::IS_REMOVE_RELATED_PRODUCTS_BLOCK, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * @param $storeId
     * @return bool
     */
    public function isRemoveUpsellProductsBlock($storeId = null): bool
    {
        return $this->configInterface->isSetFlag(self::IS_REMOVE_UPSELL_PRODUCTS_BLOCK, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * @param $storeId
     * @return int
     */
    public function getNumberOfRelatedProducts($storeId = null): int
    {
        return (int) $this->configInterface->getValue(
            self::NUM_OF_RECOMMEND_RELATED_PRODUCTS,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * @param $storeId
     * @return int
     */
    public function getNumberOfFrequentlyBoughtTogetherProducts($storeId = null): int
    {
        return (int) $this->configInterface->getValue(
            self::NUM_OF_RECOMMEND_FREQUENTLY_BOUGHT_TOGETHER_PRODUCTS,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * @param int $storeId
     * @return int
     */
    public function isRecommendTrendingItemsEnabled($storeId = null)
    {
        return (int)$this->configInterface->getValue(
            self::IS_RECOMMEND_TRENDING_ITEMS_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * @param $storeId
     * @return int
     */
    public function getNumberOfTrendingItems($storeId = null): int
    {
        return (int) $this->configInterface->getValue(
            self::NUM_OF_TRENDING_ITEMS,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Returns number of looking similar products to display
     *
     * @param $storeId
     * @return int
     */
    public function getNumberOfLookingSimilar($storeId = null): int
    {
        return (int) $this->configInterface->getValue(
            self::NUM_OF_LOOKING_SIMILAR,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * @param $storeId
     * @return mixed
     */
    public function getTrendingItemsFacetName($storeId = null)
    {
        return $this->configInterface->getValue(
            self::TREND_ITEMS_FACET_NAME,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * @param $storeId
     * @return mixed
     */
    public function getTrendingItemsFacetValue($storeId = null)
    {
        return $this->configInterface->getValue(
            self::TREND_ITEMS_FACET_VALUE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Determines whether Looking Similar enabled (for widgets))
     *
     * @param $storeId
     * @return int
     */
    public function isRecommendLookingSimilarEnabled($storeId = null)
    {
        return (int)$this->configInterface->getValue(
            self::IS_RECOMMEND_LOOKING_SIMILAR_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Determines whether Looking Similar enabled on PDP
     *
     * @param $storeId
     * @return int
     */
    public function isLookingSimilarEnabledInPDP($storeId = null)
    {
        return (int)$this->configInterface->getValue(
            self::IS_LOOKING_SIMILAR_ENABLED_IN_PDP,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function getLookingSimilarTitle($storeId = null)
    {
        return $this->configInterface->getValue(
            self::LOOKING_SIMILAR_TITLE,
            ScopeInterface::SCOPE_STORE, $storeId
        );
    }

    /**
     * @param $storeId
     * @return int
     */
    public function isLookingSimilarEnabledInShoppingCart($storeId = null)
    {
        return (int)$this->configInterface->getValue(
            self::IS_LOOKING_SIMILAR_ENABLED_IN_SHOPPING_CART,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * @param $storeId
     * @return int
     */
    public function isTrendItemsEnabledInPDP($storeId = null)
    {
        return (int)$this->configInterface->getValue(
            self::IS_TREND_ITEMS_ENABLED_IN_PDP,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * @param $storeId
     * @return int
     */
    public function isTrendItemsEnabledInShoppingCart($storeId = null)
    {
        return (int)$this->configInterface->getValue(
            self::IS_TREND_ITEMS_ENABLED_IN_SHOPPING_CART,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * @param $storeId
     * @return bool
     */
    public function isAddToCartEnabledInFrequentlyBoughtTogether($storeId = null): bool
    {
        return $this->configInterface->isSetFlag(self::IS_ADDTOCART_ENABLED_IN_FREQUENTLY_BOUGHT_TOGETHER, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * @param $storeId
     * @return bool
     */
    public function isAddToCartEnabledInRelatedProducts($storeId = null): bool
    {
        return $this->configInterface->isSetFlag(self::IS_ADDTOCART_ENABLED_IN_RELATED_PRODUCTS, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * @param $storeId
     * @return bool
     */
    public function isAddToCartEnabledInTrendsItem($storeId = null): bool
    {
        return $this->configInterface->isSetFlag(self::IS_ADDTOCART_ENABLED_IN_TRENDS_ITEM, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * Determines whether add to cart is enabled in Looking Similar
     *
     * @param $storeId
     * @return bool
     */
    public function isAddToCartEnabledInLookingSimilar($storeId = null): bool
    {
        return $this->configInterface->isSetFlag(
            self::IS_ADDTOCART_ENABLED_IN_LOOKING_SIMILAR,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * @param $storeId
     * @return string
     */
    public function getFBTTitle($storeId = null)
    {
        return $this->configInterface->getValue(self::FREQUENTLY_BOUGHT_TOGETHER_TITLE, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * @param $storeId
     * @return string
     */
    public function getRelatedProductsTitle($storeId = null)
    {
        return $this->configInterface->getValue(self::RELATED_PRODUCTS_TITLE, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * @param $storeId
     * @return string
     */
    public function getTrendingItemsTitle($storeId = null)
    {
        return $this->configInterface->getValue(self::TRENDING_ITEMS_TITLE, ScopeInterface::SCOPE_STORE, $storeId);
    }

    // --- Images --- //

    /**
     * @param $storeId
     * @return int
     */
    public function getImageWidth($storeId = null)
    {
        $imageWidth = $this->configInterface->getValue(
            self::XML_PATH_IMAGE_WIDTH,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        if (!$imageWidth) {
            return 265;
        }

        return (int)$imageWidth;
    }

    /**
     * @param $storeId
     * @return int
     */
    public function getImageHeight($storeId = null)
    {
        $imageHeight = $this->configInterface->getValue(
            self::XML_PATH_IMAGE_HEIGHT,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        if (!$imageHeight) {
            return 265;
        }

        return (int)$imageHeight;
    }

    /**
     * @param $storeId
     * @return mixed
     */
    public function getImageType($storeId = null)
    {
        return $this->configInterface->getValue(self::XML_PATH_IMAGE_TYPE, ScopeInterface::SCOPE_STORE, $storeId);
    }

    // --- Indexing Manager --- //

    /**
     * @param $storeId
     * @return bool
     */
    public function isIndexingEnabled($storeId = null): bool
    {
        return $this->configInterface->isSetFlag(self::ENABLE_INDEXING, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * @param $storeId
     * @return bool
     */
    public function isQuerySuggestionsIndexEnabled($storeId = null): bool
    {
        return $this->configInterface->isSetFlag(self::ENABLE_QUERY_SUGGESTIONS_INDEX, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * @param $storeId
     * @return bool
     */
    public function isPagesIndexEnabled($storeId = null): bool
    {
        return $this->configInterface->isSetFlag(self::ENABLE_PAGES_INDEX, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * @return bool
     */
    public function isProductsIndexerEnabled(): bool
    {
        return $this->configInterface->isSetFlag(
            self::ENABLE_INDEXER_PRODUCTS,
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * @return bool
     */
    public function isCategoriesIndexerEnabled(): bool
    {
        return $this->configInterface->isSetFlag(
            self::ENABLE_INDEXER_CATEGORIES,
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * @return bool
     */
    public function isPagesIndexerEnabled(): bool
    {
        return $this->configInterface->isSetFlag(
            self::ENABLE_INDEXER_PAGES,
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * @return bool
     */
    public function isSuggestionsIndexerEnabled(): bool
    {
        return $this->configInterface->isSetFlag(
            self::ENABLE_INDEXER_SUGGESTIONS,
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * @return bool
     */
    public function isAdditionalSectionsIndexerEnabled(): bool
    {
        return $this->configInterface->isSetFlag(
            self::ENABLE_INDEXER_ADDITIONAL_SECTIONS,
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * @return bool
     */
    public function isDeleteProductsIndexerEnabled(): bool
    {
        return $this->configInterface->isSetFlag(
            self::ENABLE_INDEXER_DELETE_PRODUCTS,
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * @return bool
     */
    public function isQueueIndexerEnabled(): bool
    {
        return $this->configInterface->isSetFlag(
            self::ENABLE_INDEXER_QUEUE,
            ScopeInterface::SCOPE_STORE
        );
    }

    // --- Click & Conversion Analytics --- //

    /**
     * @param $storeId
     * @return bool
     */
    public function isClickConversionAnalyticsEnabled($storeId = null): bool
    {
        return $this->configInterface->isSetFlag(self::CC_ANALYTICS_ENABLE, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * @param $storeId
     * @return mixed
     */
    public function getClickConversionAnalyticsISSelector($storeId = null)
    {
        return $this->configInterface->getValue(self::CC_ANALYTICS_IS_SELECTOR, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * @param $storeId
     * @return mixed
     */
    public function getConversionAnalyticsMode($storeId = null)
    {
        return $this->configInterface->getValue(
            self::CC_CONVERSION_ANALYTICS_MODE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * @param $storeId
     * @return mixed
     */
    public function getConversionAnalyticsAddToCartSelector($storeId = null)
    {
        return $this->configInterface->getValue(self::CC_ADD_TO_CART_SELECTOR, ScopeInterface::SCOPE_STORE, $storeId);
    }

    // --- Google Analytics --- //

    /**
     * @param $storeId
     * @return array
     */
    public function getAnalyticsConfig($storeId = null)
    {
        return [
            'enabled' => $this->isAnalyticsEnabled(),
            'delay' => $this->configInterface->getValue(self::GA_DELAY, ScopeInterface::SCOPE_STORE, $storeId),
            'triggerOnUiInteraction' => $this->configInterface->getValue(
                self::GA_TRIGGER_ON_UI_INTERACTION,
                ScopeInterface::SCOPE_STORE,
                $storeId
            ),
            'pushInitialSearch' => $this->configInterface->getValue(
                self::GA_PUSH_INITIAL_SEARCH,
                ScopeInterface::SCOPE_STORE,
                $storeId
            ),
        ];
    }

    /**
     * @param $storeId
     * @return bool
     */
    public function isAnalyticsEnabled($storeId = null): bool
    {
        return $this->configInterface->isSetFlag(self::GA_ENABLE, ScopeInterface::SCOPE_STORE, $storeId);
    }

    // --- Advanced --- //

    /**
     * @param $storeId
     * @return mixed
     */
    public function getRemoveWordsIfNoResult($storeId = null)
    {
        return $this->configInterface->getValue(self::REMOVE_IF_NO_RESULT, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * @param $storeId
     * @return bool
     */
    public function isPartialUpdateEnabled($storeId = null): bool
    {
        return $this->configInterface->isSetFlag(self::PARTIAL_UPDATES, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * @param $storeId
     * @return bool
     */
    public function isCustomerGroupsEnabled($storeId = null): bool
    {
        return $this->configInterface->isSetFlag(self::CUSTOMER_GROUPS_ENABLE, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * @param $storeId
     * @return bool
     */
    public function isFptEnabled($storeId = null): bool
    {
        return $this->weeeHelper->isEnabled($storeId) &&
            $this->configInterface->isSetFlag(self::FPT_ENABLE, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function setCustomerGroupsEnabled(bool $val, ?string $scope = null, ?int $scopeId = null): void
    {
        $this->configWriter->save(
            self::CUSTOMER_GROUPS_ENABLE,
            $val ? '1' : '0',
            $scope,
            $scopeId
        );
    }

    /**
     * @param $storeId
     * @return bool
     */
    public function shouldRemovePubDirectory($storeId = null): bool
    {
        return $this->configInterface->isSetFlag(self::REMOVE_PUB_DIR_IN_URL, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * @param $storeId
     * @return bool
     */
    public function isRemoveBranding($storeId = null): bool
    {
        return $this->configInterface->isSetFlag(self::REMOVE_BRANDING, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * @param $storeId
     * @return mixed
     */
    public function indexProductOnCategoryProductsUpdate($storeId = null)
    {
        return $this->configInterface->getValue(
            self::IDX_PRODUCT_ON_CAT_PRODUCTS_UPD,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }


    /**
     * @param $storeId
     * @return bool
     * @deprecated This feature is deprecated and will be replaced in an upcoming version
     */
    public function preventBackendRendering($storeId = null): bool
    {
        $preventBackendRendering = $this->configInterface->isSetFlag(
            self::PREVENT_BACKEND_RENDERING,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
        if ($preventBackendRendering === false) {
            return false;
        }
        if (!isset($_SERVER['HTTP_USER_AGENT'])) {
            return false;
        }
        $userAgent = mb_strtolower((string) filter_input(INPUT_SERVER, 'HTTP_USER_AGENT', FILTER_SANITIZE_SPECIAL_CHARS), 'utf-8');

        $allowedUserAgents = $this->configInterface->getValue(
            self::BACKEND_RENDERING_ALLOWED_USER_AGENTS,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
        $allowedUserAgents = trim((string) $allowedUserAgents);
        if ($allowedUserAgents === '') {
            return true;
        }
        $allowedUserAgents = preg_split('/\n|\r\n?/', $allowedUserAgents);
        $allowedUserAgents = array_filter($allowedUserAgents);
        foreach ($allowedUserAgents as $allowedUserAgent) {
            $allowedUserAgent = mb_strtolower($allowedUserAgent, 'utf-8');
            if (mb_strpos($userAgent, $allowedUserAgent) !== false) {
                return false;
            }
        }
        return true;
    }

    /**
     * @param $storeId
     * @return mixed
     */
    public function getBackendRenderingDisplayMode($storeId = null)
    {
        return $this->configInterface->getValue(
            self::PREVENT_BACKEND_RENDERING_DISPLAY_MODE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * @param $storeId
     * @return array
     */
    public function getNonCastableAttributes($storeId = null)
    {
        $nonCastableAttributes = [];
        $config = $this->serializer->unserialize($this->configInterface->getValue(
            self::NON_CASTABLE_ATTRIBUTES,
            ScopeInterface::SCOPE_STORE,
            $storeId
        ));
        if (is_array($config)) {
            foreach ($config as $attributeData) {
                if (isset($attributeData['attribute'])) {
                    $nonCastableAttributes[] = $attributeData['attribute'];
                }
            }
        }
        return $nonCastableAttributes;
    }

    /**
     * @param $storeId
     * @return int
     */
    public function getNumberOfElementByPage($storeId = null): int
    {
        return (int) $this->configInterface->getValue(self::NUMBER_OF_ELEMENT_BY_PAGE, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * @param $storeId
     * @return int
     */
    public function getMaxRecordSizeLimit($storeId = null)
    {
        return (int) $this->configInterface->getValue(
            self::MAX_RECORD_SIZE_LIMIT,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * @param $storeId
     * @return string
     */
    public function getAnalyticsRegion($storeId = null)
    {
        return $this->configInterface->getValue(
            self::ANALYTICS_REGION,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * @param $storeId
     * @return mixed'
     */
    public function getConnectionTimeout($storeId = null)
    {
        return $this->configInterface->getValue(self::CONNECTION_TIMEOUT, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * @param $storeId
     * @return mixed'
     */
    public function getReadTimeout($storeId = null)
    {
        return $this->configInterface->getValue(self::READ_TIMEOUT, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * @param $storeId
     * @return mixed'
     */
    public function getWriteTimeout($storeId = null)
    {
        return $this->configInterface->getValue(self::WRITE_TIMEOUT, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function shouldForwardPrimaryIndexSettingsToReplicas(?int $storeId = null): bool
    {
        return $this->configInterface->isSetFlag(
            self::FORWARD_TO_REPLICAS,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function isAutoPriceIndexingEnabled(?int $storeId = null): bool
    {
        return $this->configInterface->isSetFlag(
            self::AUTO_PRICE_INDEXING_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function isProfilerEnabled(?int $storeId = null): bool
    {
        return $this->configInterface->isSetFlag(self::PROFILER_ENABLED, ScopeInterface::SCOPE_STORE, $storeId);
    }

    // Indexing Queue advanced settingg
    /**
     * @param $storeId
     * @return int
     */
    public function getArchiveLogClearLimit($storeId = null)
    {
        return (int)$this->configInterface->getValue(
            self::ARCHIVE_LOG_CLEAR_LIMIT,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * @param $storeId
     * @return bool
     */
    public function isEnhancedQueueArchiveEnabled($storeId = null): bool
    {
        return $this->configInterface->isSetFlag(self::ENHANCED_QUEUE_ARCHIVE, ScopeInterface::SCOPE_STORE, $storeId);
    }

    // --- Extra index settings --- //

    /**
     * @param $section
     * @param $storeId
     * @return string
     */
    public function getExtraSettings($section, $storeId = null): string
    {
        $constant = 'EXTRA_SETTINGS_' . mb_strtoupper((string) $section);
        $value = $this->configInterface->getValue(constant('self::' . $constant), ScopeInterface::SCOPE_STORE, $storeId);
        return trim((string)$value);
    }

    // --- Magento Core --- //

    /**
     * @return string
     */
    public function getMagentoVersion()
    {
        return $this->productMetadata->getVersion();
    }

    /**
     * @return string
     */
    public function getMagentoEdition()
    {
        return $this->productMetadata->getEdition();
    }

    /**
     * @return false|string
     */
    public function getExtensionVersion()
    {
        return $this->moduleResource->getDbVersion('Algolia_AlgoliaSearch');
    }

    /**
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getCurrencyCode(?int $storeId = null): string
    {
        /** @var \Magento\Store\Model\Store $store */
        $store = $this->storeManager->getStore($storeId);
        return $store->getCurrentCurrencyCode();
    }

    /**
     * Obtain the store scoped currency configuration or fall back to all allowed currencies
     * @return array
     */
    public function getAllowedCurrencies(?int $storeId = null): array
    {
        $configured = explode(
            ',',
            $this->configInterface->getValue(
                DirCurrency::XML_PATH_CURRENCY_ALLOW,
                ScopeInterface::SCOPE_STORE,
                $storeId
            ) ?? ''
        );
        return $configured ?: $this->dirCurrency->getConfigAllowCurrencies();
    }

    /**
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getStoreId(): int
    {
        return $this->storeManager->getStore()->getId();
    }

    /**
     * @param $storeId
     * @return mixed
     */
    public function getStoreLocale($storeId)
    {
        return $this->configInterface->getValue(
            \Magento\Directory\Helper\Data::XML_PATH_DEFAULT_LOCALE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * @param $storeId
     * @return string|null
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getCurrency($storeId = null)
    {
        /** @var \Magento\Store\Model\Store $store */
        $store = $this->storeManager->getStore($storeId);
        return $this->currency->getCurrency($store->getCurrentCurrencyCode())->getSymbol();
    }

    /**
     * @param $storeId
     * @return bool
     */
    public function getShowOutOfStock($storeId = null): bool
    {
        return $this->configInterface->isSetFlag(self::SHOW_OUT_OF_STOCK, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * @param $storeId
     * @return bool
     */
    public function useSecureUrlsInFrontend($storeId = null): bool
    {
        return $this->configInterface->isSetFlag(
            self::USE_SECURE_IN_FRONTEND,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * @return bool
     */
    public function isCookieRestrictionModeEnabled(): bool
    {
        return (bool) $this->cookieHelper->isCookieRestrictionModeEnabled();
    }

    /**
     * @param $storeId
     * @return mixed
     */
    public function getCookieLifetime($storeId = null)
    {
        return $this->configInterface->getValue(self::COOKIE_LIFETIME, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * @param $storeId
     * @return mixed
     */
    public function getCacheTime($storeId = null)
    {
        return $this->configInterface->getValue(
            self::MAGENTO_DEFAULT_CACHE_TIME,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /***************************************
     *   DEPRECATED CONSTANTS & METHODS    *
     **************************************/

    /*** CONSTANTS ***/

    // --- Autocomplete --- //

    /**
     * @deprecated This constant has been moved to a domain specific config helper and will be removed in a future release
     * @see \Algolia\AlgoliaSearch\Helper\Configuration\AutocompleteHelper::IS_ENABLED
     */
    public const IS_POPUP_ENABLED = AutocompleteHelper::IS_ENABLED;

    /**
     * @deprecated This constant has been moved to a domain specific config helper and will be removed in a future release
     * @see \Algolia\AlgoliaSearch\Helper\Configuration\AutocompleteHelper::DOM_SELECTOR
     */
    public const AUTOCOMPLETE_SELECTOR = AutocompleteHelper::DOM_SELECTOR;

    /**
     * @deprecated This constant has been moved to a domain specific config helper and will be removed in a future release
     * @see \Algolia\AlgoliaSearch\Helper\Configuration\AutocompleteHelper::ADDITIONAL_SECTIONS
     */
    public const AUTOCOMPLETE_SECTIONS = AutocompleteHelper::ADDITIONAL_SECTIONS;

    /**
     * @deprecated This constant has been moved to a domain specific config helper and will be removed in a future release
     * @see \Algolia\AlgoliaSearch\Helper\Configuration\AutocompleteHelper::NB_OF_PRODUCTS_SUGGESTIONS
     */
    public const NB_OF_PRODUCTS_SUGGESTIONS = AutocompleteHelper::NB_OF_PRODUCTS_SUGGESTIONS;

    /**
     * @deprecated This constant has been moved to a domain specific config helper and will be removed in a future release
     * @see \Algolia\AlgoliaSearch\Helper\Configuration\AutocompleteHelper::NB_OF_CATEGORIES_SUGGESTIONS
     */
    public const NB_OF_CATEGORIES_SUGGESTIONS = AutocompleteHelper::NB_OF_CATEGORIES_SUGGESTIONS;

    /**
     * @deprecated This constant has been moved to a domain specific config helper and will be removed in a future release
     * @see \Algolia\AlgoliaSearch\Helper\Configuration\AutocompleteHelper::NB_OF_QUERIES_SUGGESTIONS
     */
    public const NB_OF_QUERIES_SUGGESTIONS = AutocompleteHelper::NB_OF_QUERIES_SUGGESTIONS;

    /**
     * @deprecated This constant has been moved to a domain specific config helper and will be removed in a future release
     * @see \Algolia\AlgoliaSearch\Helper\Configuration\AutocompleteHelper::MIN_QUERY_POPULARITY
     */
    public const MIN_POPULARITY = AutocompleteHelper::MIN_QUERY_POPULARITY;

    /**
     * @deprecated This constant has been moved to a domain specific config helper and will be removed in a future release
     * @see \Algolia\AlgoliaSearch\Helper\Configuration\AutocompleteHelper::MIN_QUERY_NUMBER_OF_RESULTS
     */
    public const MIN_NUMBER_OF_RESULTS = AutocompleteHelper::MIN_QUERY_NUMBER_OF_RESULTS;

    /**
     * @deprecated This constant has been moved to a domain specific config helper and will be removed in a future release
     * @see \Algolia\AlgoliaSearch\Helper\Configuration\AutocompleteHelper::EXCLUDED_PAGES
     */
    public const EXCLUDED_PAGES = AutocompleteHelper::EXCLUDED_PAGES;

    /**
     * @deprecated This constant has been moved to a domain specific config helper and will be removed in a future release
     * @see \Algolia\AlgoliaSearch\Helper\Configuration\AutocompleteHelper::RENDER_TEMPLATE_DIRECTIVES
     */
    public const RENDER_TEMPLATE_DIRECTIVES = AutocompleteHelper::RENDER_TEMPLATE_DIRECTIVES;

    /**
     * @deprecated This constant has been moved to a domain specific config helper and will be removed in a future release
     * @see \Algolia\AlgoliaSearch\Helper\Configuration\AutocompleteHelper::IS_DEBUG_ENABLED
     */
    public const AUTOCOMPLETE_MENU_DEBUG = AutocompleteHelper::IS_DEBUG_ENABLED;

    /**
     * @deprecated This constant has been moved to a domain specific config helper and will be removed in a future release
     * @see \Algolia\AlgoliaSearch\Helper\Configuration\AutocompleteHelper::DEBOUNCE_MILLISEC
     */
    public const AUTOCOMPLETE_DEBOUNCE_MILLISEC = AutocompleteHelper::DEBOUNCE_MILLISEC;

    /**
     * @deprecated This constant has been moved to a domain specific config helper and will be removed in a future release
     * @see \Algolia\AlgoliaSearch\Helper\Configuration\AutocompleteHelper::MINIMUM_CHAR_LENGTH
     */
    public const AUTOCOMPLETE_MINIMUM_CHAR_LENGTH = AutocompleteHelper::MINIMUM_CHAR_LENGTH;

    // --- InstantSearch --- //

    /**
     * @deprecated This constant has been moved to a domain specific config helper and will be removed in a future release
     * @see \Algolia\AlgoliaSearch\Helper\Configuration\InstantSearchHelper::IS_ENABLED
     */
    public const IS_INSTANT_ENABLED = InstantSearchHelper::IS_ENABLED;

    /**
     * @deprecated This constant has been moved to a domain specific config helper and will be removed in a future release
     * @see \Algolia\AlgoliaSearch\Helper\Configuration\InstantSearchHelper::DOM_SELECTOR
     */
    public const INSTANT_SELECTOR = InstantSearchHelper::DOM_SELECTOR;

    /**
     * @deprecated This constant has been moved to a domain specific config helper and will be removed in a future release
     * @see \Algolia\AlgoliaSearch\Helper\Configuration\InstantSearchHelper::NUMBER_OF_PRODUCT_RESULTS
     */
    public const NUMBER_OF_PRODUCT_RESULTS = InstantSearchHelper::NUMBER_OF_PRODUCT_RESULTS;

    /**
     * @deprecated This constant has been moved to a domain specific config helper and will be removed in a future release
     * @see \Algolia\AlgoliaSearch\Helper\Configuration\InstantSearchHelper::REPLACE_CATEGORIES
     */
    public const REPLACE_CATEGORIES = InstantSearchHelper::REPLACE_CATEGORIES;

    /**
     * @deprecated This constant has been moved to a domain specific config helper and will be removed in a future release
     * @see \Algolia\AlgoliaSearch\Helper\Configuration\InstantSearchHelper::FACETS
     */
    public const FACETS = InstantSearchHelper::FACETS;

    /**
     * @deprecated This constant has been moved to a domain specific config helper and will be removed in a future release
     * @see \Algolia\AlgoliaSearch\Helper\Configuration\InstantSearchHelper::MAX_VALUES_PER_FACET
     */
    public const MAX_VALUES_PER_FACET = InstantSearchHelper::MAX_VALUES_PER_FACET;

    /**
     * @deprecated This constant has been moved to a domain specific config helper and will be removed in a future release
     * @see \Algolia\AlgoliaSearch\Helper\Configuration\InstantSearchHelper::IS_DYNAMIC_FACETS_ENABLED
     */
    public const ENABLE_DYNAMIC_FACETS = InstantSearchHelper::IS_DYNAMIC_FACETS_ENABLED;

    /**
     * @deprecated This constant has been moved to a domain specific config helper and will be removed in a future release
     * @see \Algolia\AlgoliaSearch\Helper\Configuration\InstantSearchHelper::SORTING_INDICES
     */
    public const SORTING_INDICES = InstantSearchHelper::SORTING_INDICES;

    /**
     * @deprecated This constant has been moved to a domain specific config helper and will be removed in a future release
     * @see \Algolia\AlgoliaSearch\Helper\Configuration\InstantSearchHelper::SHOW_SUGGESTIONS_NO_RESULTS
     */
    public const SHOW_SUGGESTIONS_NO_RESULTS = InstantSearchHelper::SHOW_SUGGESTIONS_NO_RESULTS;

    /**
     * @deprecated This constant has been moved to a domain specific config helper and will be removed in a future release
     * @see \Algolia\AlgoliaSearch\Helper\Configuration\InstantSearchHelper::IS_SEARCHBOX_ENABLED
     */
    public const SEARCHBOX_ENABLE = InstantSearchHelper::IS_SEARCHBOX_ENABLED;

    /**
     * @deprecated This constant has been moved to a domain specific config helper and will be removed in a future release
     * @see \Algolia\AlgoliaSearch\Helper\Configuration\InstantSearchHelper::IS_ADD_TO_CART_ENABLED
     */
    public const XML_ADD_TO_CART_ENABLE = InstantSearchHelper::IS_ADD_TO_CART_ENABLED;

    /**
     * @deprecated This constant has been moved to a domain specific config helper and will be removed in a future release
     * @see \Algolia\AlgoliaSearch\Helper\Configuration\InstantSearchHelper::IS_INFINITE_SCROLL_ENABLED
     */
    public const INFINITE_SCROLL_ENABLE = InstantSearchHelper::IS_INFINITE_SCROLL_ENABLED;

    /**
     * @deprecated This constant has been moved to a domain specific config helper and will be removed in a future release
     * @see \Algolia\AlgoliaSearch\Helper\Configuration\InstantSearchHelper::HIDE_PAGINATION
     */
    public const HIDE_PAGINATION = InstantSearchHelper::HIDE_PAGINATION;

    /**
     * @deprecated This constant is retained purely for data patches to migrate from older versions
     */
    public const LEGACY_USE_VIRTUAL_REPLICA_ENABLED = 'algoliasearch_instant/instant/use_virtual_replica';

    // --- Indexing Queue / Cron --- //

    /**
     * @deprecated This constant has been moved to a domain specific config helper and will be removed in a future release
     * @see \Algolia\AlgoliaSearch\Helper\Configuration\QueueHelper::IS_ACTIVE
     */
    public const IS_ACTIVE = QueueHelper::IS_ACTIVE;

    /**
     * @deprecated This constant has been moved to a domain specific config helper and will be removed in a future release
     * @see \Algolia\AlgoliaSearch\Helper\Configuration\QueueHelper::USE_BUILT_IN_CRON
     */
    public const USE_BUILT_IN_CRON =  QueueHelper::USE_BUILT_IN_CRON;

    /**
     * @deprecated This constant has been moved to a domain specific config helper and will be removed in a future release
     * @see \Algolia\AlgoliaSearch\Helper\Configuration\QueueHelper::NUMBER_OF_JOB_TO_RUN
     */
    public const NUMBER_OF_JOB_TO_RUN =  QueueHelper::NUMBER_OF_JOB_TO_RUN;

    /**
     * @deprecated This constant has been moved to a domain specific config helper and will be removed in a future release
     * @see \Algolia\AlgoliaSearch\Helper\Configuration\QueueHelper::RETRY_LIMIT
     */
    public const RETRY_LIMIT =  QueueHelper::RETRY_LIMIT;

    // --- Indexing Manager --- //

    /**
     * @deprecated This constant has been renamed to be more meaningful and to avoid confusion with "backend rendering" statements
     * @see \Algolia\AlgoliaSearch\Helper\ConfigHelper::ENABLE_INDEXING
     */
    public const ENABLE_BACKEND = self::ENABLE_INDEXING;

    // --- Advanced --- //
    /**
     * @deprecated This configuration is no longer being used and will be removed in a future release
     */
    public const MAKE_SEO_REQUEST = 'algoliasearch_advanced/advanced/make_seo_request';

    /*** METHODS ***/

    // --- Autocomplete --- //

    /**
     * @param $storeId
     * @return bool
     * @deprecated This method has been moved to the Autocomplete config helper and will be removed in a future version
     * @see \Algolia\AlgoliaSearch\Helper\Configuration\AutocompleteHelper::isEnabled
     */
    public function isAutoCompleteEnabled($storeId = null)
    {
        return $this->autocompleteConfig->isEnabled($storeId);
    }

    /**
     * @param $storeId
     * @return bool
     * @internal This is an internal function only and should not be used by customizations
     */
    public function isDefaultSelector($storeId = null)
    {
        return '.algolia-search-input' === $this->getAutocompleteSelector($storeId);
    }

    /**
     * @param $storeId
     * @return mixed
     * @deprecated This method has been moved to the Autocomplete config helper and will be removed in a future version
     * @see \Algolia\AlgoliaSearch\Helper\Configuration\AutocompleteHelper::getDomSelector()
     */
    public function getAutocompleteSelector($storeId = null)
    {
        return $this->autocompleteConfig->getDomSelector($storeId);
    }

    /**
     * @param $storeId
     * @return array
     * @deprecated This method has been moved to the Autocomplete config helper and will be removed in a future version
     * @see \Algolia\AlgoliaSearch\Helper\Configuration\AutocompleteHelper::getAdditionalSections()
     */
    public function getAutocompleteSections($storeId = null)
    {
        return $this->autocompleteConfig->getAdditionalSections($storeId);
    }

    /**
     * @param $storeId
     * @return int
     * @deprecated This method has been moved to the Autocomplete config helper and will be removed in a future version
     * @see \Algolia\AlgoliaSearch\Helper\Configuration\AutocompleteHelper::getNumberOfProductsSuggestions()
     */
    public function getNumberOfProductsSuggestions($storeId = null)
    {
        return $this->autocompleteConfig->getNumberOfProductsSuggestions($storeId);
    }

    /**
     * @param $storeId
     * @return int
     * @deprecated This method has been moved to the Autocomplete config helper and will be removed in a future version
     * @see \Algolia\AlgoliaSearch\Helper\Configuration\AutocompleteHelper::getNumberOfCategoriesSuggestions()
     */
    public function getNumberOfCategoriesSuggestions($storeId = null)
    {
        return $this->autocompleteConfig->getNumberOfCategoriesSuggestions($storeId);
    }

    /**
     * @param $storeId
     * @return int
     * @deprecated This method has been moved to the Autocomplete config helper and will be removed in a future version
     * @see \Algolia\AlgoliaSearch\Helper\Configuration\AutocompleteHelper::getNumberOfQueriesSuggestions()
     */
    public function getNumberOfQueriesSuggestions($storeId = null)
    {
        return $this->autocompleteConfig->getNumberOfQueriesSuggestions($storeId);
    }

    /**
     * @param $storeId
     * @return int
     * @deprecated This method has been moved to the Autocomplete config helper and will be removed in a future version
     * @see \Algolia\AlgoliaSearch\Helper\Configuration\AutocompleteHelper::getMinQueryPopularity()
     */
    public function getMinPopularity($storeId = null)
    {
        return $this->autocompleteConfig->getMinQueryPopularity($storeId);
    }

    /**
     * @param $storeId
     * @return int
     * @deprecated This method has been moved to the Autocomplete config helper and will be removed in a future version
     * @see \Algolia\AlgoliaSearch\Helper\Configuration\AutocompleteHelper::getMinQueryNumberOfResults()
     */
    public function getMinNumberOfResults($storeId = null)
    {
        return $this->autocompleteConfig->getMinQueryNumberOfResults($storeId);
    }

    /**
     * @param $storeId
     * @return array
     * @deprecated This method has been moved to the Autocomplete config helper and will be removed in a future version
     * @see \Algolia\AlgoliaSearch\Helper\Configuration\AutocompleteHelper::getExcludedPages()
     */
    public function getExcludedPages($storeId = null)
    {
        return $this->autocompleteConfig->getExcludedPages($storeId);
    }

    /**
     * @param $storeId
     * @return mixed
     * @deprecated This method has been moved to the Autocomplete config helper and will be removed in a future version
     * @see \Algolia\AlgoliaSearch\Helper\Configuration\AutocompleteHelper::shouldRenderTemplateDirectives()
     */
    public function getRenderTemplateDirectives($storeId = null)
    {
        return $this->autocompleteConfig->shouldRenderTemplateDirectives($storeId);
    }

    /**
     * @param $storeId
     * @return bool
     * @deprecated This method has been moved to the Autocomplete config helper and will be removed in a future version
     * @see \Algolia\AlgoliaSearch\Helper\Configuration\AutocompleteHelper::isDebugEnabled()
     */
    public function isAutocompleteDebugEnabled($storeId = null)
    {
        return $this->autocompleteConfig->isDebugEnabled($storeId);
    }

    /**
     * @param $storeId
     * @return mixed
     * @deprecated This method has been moved to the Autocomplete config helper and will be removed in a future version
     * @see \Algolia\AlgoliaSearch\Helper\Configuration\AutocompleteHelper::isKeyboardNavigationEnabled()
     */
    public function isAutocompleteNavigatorEnabled($storeId = null)
    {
        return $this->autocompleteConfig->isKeyboardNavigationEnabled($storeId);
    }

    /**
     * @deprecated This method has been moved to the Autocomplete config helper and will be removed in a future version
     * @see \Algolia\AlgoliaSearch\Helper\Configuration\AutocompleteHelper::getDebounceMilliseconds()
     */
    public function getAutocompleteDebounceMilliseconds($storeId = null): int
    {
        return $this->autocompleteConfig->getDebounceMilliseconds($storeId);
    }

    /**
     * @deprecated This method has been moved to the Autocomplete config helper and will be removed in a future version
     * @see \Algolia\AlgoliaSearch\Helper\Configuration\AutocompleteHelper::getMinimumCharacterLength()
     */
    public function getAutocompleteMinimumCharacterLength(?int $storeId = null): int
    {
        return $this->autocompleteConfig->getMinimumCharacterLength($storeId);
    }

    // --- InstantSearch --- //

    /**
     * @param $storeId
     * @return bool
     * @deprecated This method has been moved to the InstantSearch config helper and will be removed in a future version
     * @see \Algolia\AlgoliaSearch\Helper\Configuration\InstantSearchHelper::isEnabled()
     * /
     */
    public function isInstantEnabled($storeId = null)
    {
        return $this->instantSearchConfig->isEnabled($storeId);
    }

    /**
     * @param $storeId
     * @return mixed
     * @deprecated This method has been moved to the InstantSearch config helper and will be removed in a future version
     * @see \Algolia\AlgoliaSearch\Helper\Configuration\InstantSearchHelper::getDomSelector()
     */
    public function getInstantSelector($storeId = null)
    {
        return $this->instantSearchConfig->getDomSelector($storeId);
    }

    /**
     * @param $storeId
     * @return int
     * @deprecated This method has been moved to the InstantSearch config helper and will be removed in a future version
     * @see \Algolia\AlgoliaSearch\Helper\Configuration\InstantSearchHelper::getNumberOfProductResults()
     */
    public function getNumberOfProductResults($storeId = null)
    {
        return $this->instantSearchConfig->getNumberOfProductResults($storeId);
    }

    /**
     * @param $storeId
     * @return bool
     * @deprecated This method has been moved to the InstantSearch config helper and will be removed in a future version
     * @see \Algolia\AlgoliaSearch\Helper\Configuration\InstantSearchHelper::shouldReplaceCategories()
     */
    public function replaceCategories($storeId = null)
    {
        return $this->instantSearchConfig->shouldReplaceCategories($storeId);
    }

    /**
     * @param $storeId
     * @return array|bool|float|int|mixed|string
     * @deprecated This method has been moved to the InstantSearch config helper and will be removed in a future version
     * @see \Algolia\AlgoliaSearch\Helper\Configuration\InstantSearchHelper::getFacets()
     */
    public function getFacets($storeId = null)
    {
        return $this->instantSearchConfig->getFacets($storeId);
    }

    /**
     * @param $storeId
     * @return int
     * @deprecated This method has been moved to the InstantSearch config helper and will be removed in a future version
     * @see \Algolia\AlgoliaSearch\Helper\Configuration\InstantSearchHelper::getMaxValuesPerFacet()
     */
    public function getMaxValuesPerFacet($storeId = null)
    {
        return $this->instantSearchConfig->getMaxValuesPerFacet($storeId);
    }

    /**
     * @param int|null $storeId
     * @return bool
     * @deprecated This method has been moved to the InstantSearch config helper and will be removed in a future version
     * @see \Algolia\AlgoliaSearch\Helper\Configuration\InstantSearchHelper::isDynamicFacetsEnabled()
     */
    public function isDynamicFacetsEnabled(?int $storeId = null): bool
    {
        return $this->instantSearchConfig->isDynamicFacetsEnabled($storeId);
    }

    /***
     * @param int|null $storeId
     * @return array<string,<array<string, mixed>>>
     * @deprecated This method has been moved to the InstantSearch config helper and will be removed in a future version
     * @see \Algolia\AlgoliaSearch\Helper\Configuration\InstantSearchHelper::getSorting()
     */
    public function getSorting(?int $storeId = null): array
    {
        return $this->instantSearchConfig->getSorting($storeId);
    }

    /**
     * @param int|null $storeId
     * @return string
     * @deprecated This method has been moved to the InstantSearch config helper and will be removed in a future version
     * @see \Algolia\AlgoliaSearch\Helper\Configuration\InstantSearchHelper::getRawSortingValue()
     */
    public function getRawSortingValue(?int $storeId = null): string
    {
        return $this->instantSearchConfig->getRawSortingValue($storeId);
    }

    /**
     * @param array $sorting
     * @param string|null $scope
     * @param int|null $scopeId
     * @return void
     * @deprecated This method has been moved to the InstantSearch config helper and will be removed in a future version
     * @see \Algolia\AlgoliaSearch\Helper\Configuration\InstantSearchHelper::setSorting()
     */
    public function setSorting(array $sorting, string $scope = ScopeConfigInterface::SCOPE_TYPE_DEFAULT, ?int $scopeId = null): void
    {
        $this->instantSearchConfig->setSorting($sorting, $scope, $scopeId);
    }

    /**
     * @param $storeId
     * @return bool
     * @deprecated This method has been moved to the InstantSearch config helper and will be removed in a future version
     * @see \Algolia\AlgoliaSearch\Helper\Configuration\InstantSearchHelper::isSearchBoxEnabled()
     */
    public function isInstantSearchBoxEnabled($storeId = null)
    {
        return $this->instantSearchConfig->isSearchBoxEnabled($storeId);
    }

    /**
     * @param $storeId
     * @return bool
     * @deprecated This method has been moved to the InstantSearch config helper and will be removed in a future version
     * @see \Algolia\AlgoliaSearch\Helper\Configuration\InstantSearchHelper::shouldShowSuggestionsOnNoResultsPage()
     */
    public function showSuggestionsOnNoResultsPage($storeId = null)
    {
        return $this->instantSearchConfig->shouldShowSuggestionsOnNoResultsPage($storeId);
    }

    /**
     * @param $storeId
     * @return bool
     * @deprecated This method has been moved to the InstantSearch config helper and will be removed in a future version
     * @see \Algolia\AlgoliaSearch\Helper\Configuration\InstantSearchHelper::isAddToCartEnabled()
     */
    public function isAddToCartEnable($storeId = null)
    {
        return $this->instantSearchConfig->isAddToCartEnabled($storeId);
    }

    /**
     * @param $storeId
     * @return bool
     * @deprecated This method has been moved to the InstantSearch config helper and will be removed in a future version
     * @see \Algolia\AlgoliaSearch\Helper\Configuration\InstantSearchHelper::isInfiniteScrollEnabled()
     */
    public function isInfiniteScrollEnabled($storeId = null)
    {
        return $this->instantSearchConfig->isInfiniteScrollEnabled($storeId);
    }

    /**
     * @param $storeId
     * @return bool
     * @deprecated This method has been moved to the InstantSearch config helper and will be removed in a future version
     * @see \Algolia\AlgoliaSearch\Helper\Configuration\InstantSearchHelper::shouldHidePagination()
     */
    public function hidePaginationInInstantSearchPage($storeId = null)
    {
        return $this->instantSearchConfig->shouldHidePagination($storeId);
    }

    // --- Indexing Queue / Cron --- //

    /**
     * @param $storeId
     * @return bool
     * @deprecated This method has been moved to the Queue config helper and will be removed in a future version
     * @see \Algolia\AlgoliaSearch\Helper\Configuration\QueueHelper::isQueueActive()
     */
    public function isQueueActive($storeId = null)
    {
        return $this->queueHelper->isQueueActive($storeId);
    }

    /**
     * @param $storeId
     * @return bool
     * @deprecated This method has been moved to the Queue config helper and will be removed in a future version
     * @see \Algolia\AlgoliaSearch\Helper\Configuration\QueueHelper::useBuiltInCron()
     */
    public function useBuiltInCron($storeId = null)
    {
        return $this->queueHelper->useBuiltInCron($storeId);
    }

    /**
     * @param $storeId
     * @return bool
     * @deprecated This method has been moved to the Queue config helper and will be removed in a future version
     * @see \Algolia\AlgoliaSearch\Helper\Configuration\QueueHelper::getNumberOfJobToRun()
     */
    public function getNumberOfJobToRun($storeId = null)
    {
        return $this->queueHelper->getNumberOfJobToRun($storeId);
    }

    /**
     * @param $storeId
     * @return bool
     * @deprecated This method has been moved to the Queue config helper and will be removed in a future version
     * @see \Algolia\AlgoliaSearch\Helper\Configuration\QueueHelper::getRetryLimit()
     */
    public function getRetryLimit($storeId = null)
    {
        return $this->queueHelper->getRetryLimit($storeId);
    }

    // --- Indexing Manager --- //

    /**
     * @param $storeId
     * @return bool
     * @deprecated This method has been renamed to be more meaningful and to avoid confusion with "backend rendering" statements
     * @see \Algolia\AlgoliaSearch\Helper\ConfigHelper::isIndexingEnabled()
     */
    public function isEnabledBackend($storeId = null)
    {
        return $this->isIndexingEnabled($storeId);
    }

    // --- Advanced --- //
    /**
     * @deprecated This configuration is no longer being used and will be removed in a future release
     */
    public function makeSeoRequest($storeId = null)
    {
        return $this->configInterface->isSetFlag(self::MAKE_SEO_REQUEST, ScopeInterface::SCOPE_STORE, $storeId);
    }
}
