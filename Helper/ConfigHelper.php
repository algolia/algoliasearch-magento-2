<?php

namespace Algolia\AlgoliaSearch\Helper;

use Magento;
use Magento\Customer\Model\ResourceModel\Group\Collection as GroupCollection;
use Magento\Directory\Model\Currency as DirCurrency;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\DataObject;
use Magento\Framework\Locale\Currency;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

class ConfigHelper
{
    public const ENABLE_FRONTEND = 'algoliasearch_credentials/credentials/enable_frontend';
    public const ENABLE_BACKEND = 'algoliasearch_credentials/credentials/enable_backend';
    public const ENABLE_QUERY_SUGGESTIONS_INDEX = 'algoliasearch_credentials/credentials/enable_query_suggestions_index';
    public const ENABLE_PAGES_INDEX = 'algoliasearch_credentials/credentials/enable_pages_index';
    public const LOGGING_ENABLED = 'algoliasearch_credentials/credentials/debug';
    public const APPLICATION_ID = 'algoliasearch_credentials/credentials/application_id';
    public const API_KEY = 'algoliasearch_credentials/credentials/api_key';
    public const SEARCH_ONLY_API_KEY = 'algoliasearch_credentials/credentials/search_only_api_key';
    public const INDEX_PREFIX = 'algoliasearch_credentials/credentials/index_prefix';

    public const IS_INSTANT_ENABLED = 'algoliasearch_instant/instant/is_instant_enabled';
    public const REPLACE_CATEGORIES = 'algoliasearch_instant/instant/replace_categories';
    public const INSTANT_SELECTOR = 'algoliasearch_instant/instant/instant_selector';
    public const NUMBER_OF_PRODUCT_RESULTS = 'algoliasearch_instant/instant/number_product_results';
    public const FACETS = 'algoliasearch_instant/instant/facets';
    public const MAX_VALUES_PER_FACET = 'algoliasearch_instant/instant/max_values_per_facet';
    public const SORTING_INDICES = 'algoliasearch_instant/instant/sorts';
    public const SHOW_SUGGESTIONS_NO_RESULTS = 'algoliasearch_instant/instant/show_suggestions_on_no_result_page';
    public const XML_ADD_TO_CART_ENABLE = 'algoliasearch_instant/instant/add_to_cart_enable';
    public const INFINITE_SCROLL_ENABLE = 'algoliasearch_instant/instant/infinite_scroll_enable';
    public const SEARCHBOX_ENABLE = 'algoliasearch_instant/instant/instantsearch_searchbox';
    public const HIDE_PAGINATION = 'algoliasearch_instant/instant/hide_pagination';

    public const IS_POPUP_ENABLED = 'algoliasearch_autocomplete/autocomplete/is_popup_enabled';
    public const NB_OF_PRODUCTS_SUGGESTIONS = 'algoliasearch_autocomplete/autocomplete/nb_of_products_suggestions';
    public const NB_OF_CATEGORIES_SUGGESTIONS = 'algoliasearch_autocomplete/autocomplete/nb_of_categories_suggestions';
    public const NB_OF_QUERIES_SUGGESTIONS = 'algoliasearch_autocomplete/autocomplete/nb_of_queries_suggestions';
    public const AUTOCOMPLETE_SECTIONS = 'algoliasearch_autocomplete/autocomplete/sections';
    public const EXCLUDED_PAGES = 'algoliasearch_autocomplete/autocomplete/excluded_pages';
    public const MIN_POPULARITY = 'algoliasearch_autocomplete/autocomplete/min_popularity';
    public const MIN_NUMBER_OF_RESULTS = 'algoliasearch_autocomplete/autocomplete/min_number_of_results';
    public const RENDER_TEMPLATE_DIRECTIVES = 'algoliasearch_autocomplete/autocomplete/render_template_directives';
    public const AUTOCOMPLETE_MENU_DEBUG = 'algoliasearch_autocomplete/autocomplete/debug';
    public const AUTOCOMPLETE_DEBOUNCE_MILLISEC = 'algoliasearch_autocomplete/autocomplete/debounce_millisec';
    public const AUTOCOMPLETE_MINIMUM_CHAR_LENGTH = 'algoliasearch_autocomplete/autocomplete/minimum_char_length';

    public const PRODUCT_ATTRIBUTES = 'algoliasearch_products/products/product_additional_attributes';
    public const PRODUCT_CUSTOM_RANKING = 'algoliasearch_products/products/custom_ranking_product_attributes';
    public const USE_ADAPTIVE_IMAGE = 'algoliasearch_products/products/use_adaptive_image';
    public const ENABLE_VISUAL_MERCHANDISING = 'algoliasearch_products/products/enable_visual_merchandising';
    public const CATEGORY_PAGE_ID_ATTRIBUTE_NAME = 'algoliasearch_products/products/category_page_id_attribute_name';

    public const CATEGORY_ATTRIBUTES = 'algoliasearch_categories/categories/category_additional_attributes';
    public const CATEGORY_CUSTOM_RANKING = 'algoliasearch_categories/categories/custom_ranking_category_attributes';
    public const SHOW_CATS_NOT_INCLUDED_IN_NAV = 'algoliasearch_categories/categories/show_cats_not_included_in_navigation';
    public const INDEX_EMPTY_CATEGORIES = 'algoliasearch_categories/categories/index_empty_categories';
    public const CATEGORY_SEPARATOR = 'algoliasearch_categories/categories/category_separator';

    public const IS_ACTIVE = 'algoliasearch_queue/queue/active';
    public const NUMBER_OF_JOB_TO_RUN = 'algoliasearch_queue/queue/number_of_job_to_run';
    public const RETRY_LIMIT = 'algoliasearch_queue/queue/number_of_retries';

    public const XML_PATH_IMAGE_WIDTH = 'algoliasearch_images/image/width';
    public const XML_PATH_IMAGE_HEIGHT = 'algoliasearch_images/image/height';
    public const XML_PATH_IMAGE_TYPE = 'algoliasearch_images/image/type';

    public const CC_ANALYTICS_ENABLE = 'algoliasearch_cc_analytics/cc_analytics_group/enable';
    public const CC_ANALYTICS_IS_SELECTOR = 'algoliasearch_cc_analytics/cc_analytics_group/is_selector';
    public const CC_CONVERSION_ANALYTICS_MODE = 'algoliasearch_cc_analytics/cc_analytics_group/conversion_analytics_mode';
    public const CC_ADD_TO_CART_SELECTOR = 'algoliasearch_cc_analytics/cc_analytics_group/add_to_cart_selector';

    public const GA_ENABLE = 'algoliasearch_analytics/analytics_group/enable';
    public const GA_DELAY = 'algoliasearch_analytics/analytics_group/delay';
    public const GA_TRIGGER_ON_UI_INTERACTION = 'algoliasearch_analytics/analytics_group/trigger_on_ui_interaction';
    public const GA_PUSH_INITIAL_SEARCH = 'algoliasearch_analytics/analytics_group/push_initial_search';

    public const REMOVE_IF_NO_RESULT = 'algoliasearch_advanced/advanced/remove_words_if_no_result';
    public const PARTIAL_UPDATES = 'algoliasearch_advanced/advanced/partial_update';
    public const CUSTOMER_GROUPS_ENABLE = 'algoliasearch_advanced/advanced/customer_groups_enable';
    public const REMOVE_PUB_DIR_IN_URL = 'algoliasearch_advanced/advanced/remove_pub_dir_in_url';
    public const MAKE_SEO_REQUEST = 'algoliasearch_advanced/advanced/make_seo_request';
    public const REMOVE_BRANDING = 'algoliasearch_advanced/advanced/remove_branding';
    public const AUTOCOMPLETE_SELECTOR = 'algoliasearch_autocomplete/autocomplete/autocomplete_selector';
    public const IDX_PRODUCT_ON_CAT_PRODUCTS_UPD = 'algoliasearch_advanced/advanced/index_product_on_category_products_update';
    public const PREVENT_BACKEND_RENDERING = 'algoliasearch_advanced/advanced/prevent_backend_rendering';
    public const PREVENT_BACKEND_RENDERING_DISPLAY_MODE =
        'algoliasearch_advanced/advanced/prevent_backend_rendering_display_mode';
    public const BACKEND_RENDERING_ALLOWED_USER_AGENTS =
        'algoliasearch_advanced/advanced/backend_rendering_allowed_user_agents';
    public const NON_CASTABLE_ATTRIBUTES = 'algoliasearch_advanced/advanced/non_castable_attributes';
    public const MAX_RECORD_SIZE_LIMIT = 'algoliasearch_advanced/advanced/max_record_size_limit';

    public const SHOW_OUT_OF_STOCK = 'cataloginventory/options/show_out_of_stock';

    public const USE_SECURE_IN_FRONTEND = 'web/secure/use_in_frontend';

    public const EXTRA_SETTINGS_PRODUCTS = 'algoliasearch_extra_settings/extra_settings/products_extra_settings';
    public const EXTRA_SETTINGS_CATEGORIES = 'algoliasearch_extra_settings/extra_settings/categories_extra_settings';
    public const EXTRA_SETTINGS_PAGES = 'algoliasearch_extra_settings/extra_settings/pages_extra_settings';
    public const EXTRA_SETTINGS_SUGGESTIONS = 'algoliasearch_extra_settings/extra_settings/suggestions_extra_settings';
    public const EXTRA_SETTINGS_ADDITIONAL_SECTIONS =
        'algoliasearch_extra_settings/extra_settings/additional_sections_extra_settings';
    public const MAGENTO_DEFAULT_CACHE_TIME = 'system/full_page_cache/ttl';
    protected const IS_RECOMMEND_FREQUENTLY_BOUGHT_TOGETHER_ENABLED = 'algoliasearch_recommend/recommend/frequently_bought_together/is_frequently_bought_together_enabled';
    protected const IS_RECOMMEND_RELATED_PRODUCTS_ENABLED = 'algoliasearch_recommend/recommend/related_product/is_related_products_enabled';
    protected const IS_RECOMMEND_FREQUENTLY_BOUGHT_TOGETHER_ENABLED_ON_CART_PAGE = 'algoliasearch_recommend/recommend/frequently_bought_together/is_frequently_bought_together_enabled_in_cart_page';
    protected const IS_RECOMMEND_RELATED_PRODUCTS_ENABLED_ON_CART_PAGE = 'algoliasearch_recommend/recommend/related_product/is_related_products_enabled_in_cart_page';
    protected const NUM_OF_RECOMMEND_FREQUENTLY_BOUGHT_TOGETHER_PRODUCTS = 'algoliasearch_recommend/recommend/frequently_bought_together/num_of_frequently_bought_together_products';
    protected const NUM_OF_RECOMMEND_RELATED_PRODUCTS = 'algoliasearch_recommend/recommend/related_product/num_of_related_products';
    protected const IS_REMOVE_RELATED_PRODUCTS_BLOCK = 'algoliasearch_recommend/recommend/related_product/is_remove_core_related_products_block';
    protected const IS_REMOVE_UPSELL_PRODUCTS_BLOCK = 'algoliasearch_recommend/recommend/frequently_bought_together/is_remove_core_upsell_products_block';
    protected const IS_RECOMMEND_TRENDING_ITEMS_ENABLED = 'algoliasearch_recommend/recommend/trends_item/is_trending_items_enabled';
    protected const NUM_OF_TRENDING_ITEMS = 'algoliasearch_recommend/recommend/trends_item/num_of_trending_items';
    protected const TREND_ITEMS_FACET_NAME = 'algoliasearch_recommend/recommend/trends_item/facet_name';
    protected const TREND_ITEMS_FACET_VALUE = 'algoliasearch_recommend/recommend/trends_item/facet_value';
    protected const IS_TREND_ITEMS_ENABLED_IN_PDP = 'algoliasearch_recommend/recommend/trends_item/is_trending_items_enabled_on_pdp';
    protected const IS_TREND_ITEMS_ENABLED_IN_SHOPPING_CART = 'algoliasearch_recommend/recommend/trends_item/is_trending_items_enabled_on_cart_page';
    protected const IS_ADDTOCART_ENABLED_IN_FREQUENTLY_BOUGHT_TOGETHER = 'algoliasearch_recommend/recommend/frequently_bought_together/is_addtocart_enabled';
    protected const IS_ADDTOCART_ENABLED_IN_RELATED_PRODUCTS = 'algoliasearch_recommend/recommend/related_product/is_addtocart_enabled';
    protected const IS_ADDTOCART_ENABLED_IN_TRENDS_ITEM = 'algoliasearch_recommend/recommend/trends_item/is_addtocart_enabled';
    protected const USE_VIRTUAL_REPLICA_ENABLED = 'algoliasearch_instant/instant/use_virtual_replica';
    protected const AUTOCOMPLETE_KEYBORAD_NAVIAGATION = 'algoliasearch_autocomplete/autocomplete/navigator';
    protected const FREQUENTLY_BOUGHT_TOGETHER_TITLE = 'algoliasearch_recommend/recommend/frequently_bought_together/title';
    protected const RELATED_PRODUCTS_TITLE = 'algoliasearch_recommend/recommend/related_product/title';
    protected const TRENDING_ITEMS_TITLE = 'algoliasearch_recommend/recommend/trends_item/title';

    // Indexing Queue Advanced settings
    public const ENHANCED_QUEUE_ARCHIVE = 'algoliasearch_advanced/queue/enhanced_archive';
    public const NUMBER_OF_ELEMENT_BY_PAGE = 'algoliasearch_advanced/queue/number_of_element_by_page';
    public const ARCHIVE_LOG_CLEAR_LIMIT = 'algoliasearch_advanced/queue/archive_clear_limit';

    /**
     * @var Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $configInterface;

    /**
     * @var Currency
     */
    protected $currency;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var DirCurrency
     */
    protected $dirCurrency;

    /**
     * @var DirectoryList
     */
    protected $directoryList;

    /**
     * @var Magento\Framework\Module\ResourceInterface
     */
    protected $moduleResource;

    /**
     * @var Magento\Framework\App\ProductMetadataInterface
     */
    protected $productMetadata;

    /**
     * @var Magento\Framework\Event\ManagerInterface
     */
    protected $eventManager;

    /**
     * @var SerializerInterface
     */
    protected $serializer;

    /**
     * @var GroupCollection
     */
    protected $groupCollection;

    /**
     * @param Magento\Framework\App\Config\ScopeConfigInterface $configInterface
     * @param StoreManagerInterface $storeManager
     * @param Currency $currency
     * @param DirCurrency $dirCurrency
     * @param DirectoryList $directoryList
     * @param Magento\Framework\Module\ResourceInterface $moduleResource
     * @param Magento\Framework\App\ProductMetadataInterface $productMetadata
     * @param Magento\Framework\Event\ManagerInterface $eventManager
     * @param SerializerInterface $serializer
     * @param GroupCollection $groupCollection
     */
    public function __construct(
        Magento\Framework\App\Config\ScopeConfigInterface $configInterface,
        StoreManagerInterface                             $storeManager,
        Currency                                          $currency,
        DirCurrency                                       $dirCurrency,
        DirectoryList                                     $directoryList,
        Magento\Framework\Module\ResourceInterface        $moduleResource,
        Magento\Framework\App\ProductMetadataInterface    $productMetadata,
        Magento\Framework\Event\ManagerInterface          $eventManager,
        SerializerInterface                               $serializer,
        GroupCollection                                   $groupCollection
    ) {
        $this->configInterface = $configInterface;
        $this->currency = $currency;
        $this->storeManager = $storeManager;
        $this->dirCurrency = $dirCurrency;
        $this->directoryList = $directoryList;
        $this->moduleResource = $moduleResource;
        $this->productMetadata = $productMetadata;
        $this->eventManager = $eventManager;
        $this->serializer = $serializer;
        $this->groupCollection = $groupCollection;
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
    public function shouldIndexEmptyCategories($storeId = null)
    {
        return $this->configInterface->isSetFlag(self::INDEX_EMPTY_CATEGORIES, ScopeInterface::SCOPE_STORE, $storeId);
    }

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
     * @param $storeId
     * @return bool
     */
    public function isDefaultSelector($storeId = null)
    {
        return '.algolia-search-input' === $this->getAutocompleteSelector($storeId);
    }

    /**
     * @param $storeId
     * @return mixed
     */
    public function getAutocompleteSelector($storeId = null)
    {
        return $this->configInterface->getValue(self::AUTOCOMPLETE_SELECTOR, ScopeInterface::SCOPE_STORE, $storeId);
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
     * @return int
     */
    public function getNumberOfQueriesSuggestions($storeId = null)
    {
        return (int)$this->configInterface->getValue(
            self::NB_OF_QUERIES_SUGGESTIONS,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * @param $storeId
     * @return int
     */
    public function getNumberOfProductsSuggestions($storeId = null)
    {
        return (int)$this->configInterface->getValue(
            self::NB_OF_PRODUCTS_SUGGESTIONS,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * @param $storeId
     * @return int
     */
    public function getNumberOfCategoriesSuggestions($storeId = null)
    {
        return (int)$this->configInterface->getValue(
            self::NB_OF_CATEGORIES_SUGGESTIONS,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * @param $storeId
     * @return bool
     */
    public function isEnabledFrontEnd($storeId = null)
    {
        return $this->configInterface->isSetFlag(self::ENABLE_FRONTEND, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * @param $storeId
     * @return bool
     */
    public function isEnabledBackend($storeId = null)
    {
        return $this->configInterface->isSetFlag(self::ENABLE_BACKEND, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * @param $storeId
     * @return bool
     */
    public function makeSeoRequest($storeId = null)
    {
        return $this->configInterface->isSetFlag(self::MAKE_SEO_REQUEST, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * @param $storeId
     * @return bool
     */
    public function isLoggingEnabled($storeId = null)
    {
        return $this->configInterface->isSetFlag(self::LOGGING_ENABLED, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * @param $storeId
     * @return bool
     */
    public function getShowOutOfStock($storeId = null)
    {
        return $this->configInterface->isSetFlag(self::SHOW_OUT_OF_STOCK, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * @param $storeId
     * @return bool
     */
    public function useSecureUrlsInFrontend($storeId = null)
    {
        return $this->configInterface->isSetFlag(
            self::USE_SECURE_IN_FRONTEND,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

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

    /**
     * @param $storeId
     * @return bool
     */
    public function shouldRemovePubDirectory($storeId = null)
    {
        return $this->configInterface->isSetFlag(self::REMOVE_PUB_DIR_IN_URL, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * @param $storeId
     * @return bool
     */
    public function isPartialUpdateEnabled($storeId = null)
    {
        return $this->configInterface->isSetFlag(self::PARTIAL_UPDATES, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * @param $storeId
     * @return array
     */
    public function getAutocompleteSections($storeId = null)
    {
        $attrs = $this->unserialize($this->configInterface->getValue(
            self::AUTOCOMPLETE_SECTIONS,
            ScopeInterface::SCOPE_STORE,
            $storeId
        ));

        if (is_array($attrs)) {
            return array_values($attrs);
        }

        return [];
    }

    /**
     * @param $value
     * @return array|bool|float|int|mixed|string|null
     */
    protected function unserialize($value)
    {
        if (false === $value || null === $value || '' === $value) {
            return false;
        }
        $unserialized = json_decode($value, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $unserialized;
        }
        return $this->serializer->unserialize($value);
    }

    /**
     * @param $storeId
     * @return int
     */
    public function getMinPopularity($storeId = null)
    {
        return (int)$this->configInterface->getValue(self::MIN_POPULARITY, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * @param $storeId
     * @return int
     */
    public function getMinNumberOfResults($storeId = null)
    {
        return (int)$this->configInterface->getValue(self::MIN_NUMBER_OF_RESULTS, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * @param $storeId
     * @return bool
     */
    public function isAddToCartEnable($storeId = null)
    {
        return $this->configInterface->isSetFlag(
            self::XML_ADD_TO_CART_ENABLE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * @param $storeId
     * @return bool
     */
    public function isInfiniteScrollEnabled($storeId = null)
    {
        return $this->isInstantEnabled($storeId)
            && $this->configInterface->isSetFlag(self::INFINITE_SCROLL_ENABLE, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * @param $storeId
     * @return bool
     */
    public function hidePaginationInInstantSearchPage($storeId = null)
    {
        return $this->isInstantEnabled($storeId)
            && $this->configInterface->isSetFlag(self::HIDE_PAGINATION, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * @param $storeId
     * @return bool
     */
    public function isInstantSearchBoxEnabled($storeId = null)
    {
        return $this->isInstantEnabled($storeId)
            && $this->configInterface->isSetFlag(self::SEARCHBOX_ENABLE, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * @param $storeId
     * @return bool
     */
    public function isInstantEnabled($storeId = null)
    {
        return $this->configInterface->isSetFlag(self::IS_INSTANT_ENABLED, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * @param $storeId
     * @return bool
     */
    public function isRemoveBranding($storeId = null)
    {
        return $this->configInterface->isSetFlag(self::REMOVE_BRANDING, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * @param $storeId
     * @return int
     */
    public function getMaxValuesPerFacet($storeId = null)
    {
        return (int)$this->configInterface->getValue(self::MAX_VALUES_PER_FACET, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * @param $storeId
     * @return int
     */
    public function getNumberOfElementByPage($storeId = null)
    {
        return (int)$this->configInterface->getValue(self::NUMBER_OF_ELEMENT_BY_PAGE, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * @param $storeId
     * @return mixed
     */
    public function getNumberOfJobToRun($storeId = null)
    {
        $nbJobs = (int)$this->configInterface->getValue(self::NUMBER_OF_JOB_TO_RUN, ScopeInterface::SCOPE_STORE, $storeId);

        return max($nbJobs, 1);
    }

    /**
     * @param $storeId
     * @return int
     */
    public function getRetryLimit($storeId = null)
    {
        return (int)$this->configInterface->getValue(self::RETRY_LIMIT, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * @param $storeId
     * @return bool
     */
    public function isQueueActive($storeId = null)
    {
        return $this->configInterface->isSetFlag(self::IS_ACTIVE, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * @param $storeId
     * @return bool
     */
    public function isEnhancedQueueArchiveEnabled($storeId = null)
    {
        return $this->configInterface->isSetFlag(self::ENHANCED_QUEUE_ARCHIVE, ScopeInterface::SCOPE_STORE, $storeId);
    }

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
     * @return int
     */
    public function getNumberOfProductResults($storeId = null)
    {
        return (int)$this->configInterface->getValue(
            self::NUMBER_OF_PRODUCT_RESULTS,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * @param $storeId
     * @return bool
     */
    public function replaceCategories($storeId = null)
    {
        return $this->configInterface->isSetFlag(self::REPLACE_CATEGORIES, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * @param $storeId
     * @return bool
     */
    public function isAutoCompleteEnabled($storeId = null)
    {
        return $this->configInterface->isSetFlag(self::IS_POPUP_ENABLED, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * @param $storeId
     * @return bool
     */
    public function isRecommendFrequentlyBroughtTogetherEnabled($storeId = null)
    {
        return $this->configInterface->isSetFlag(self::IS_RECOMMEND_FREQUENTLY_BOUGHT_TOGETHER_ENABLED, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * @param $storeId
     * @return bool
     */
    public function isRecommendRelatedProductsEnabled($storeId = null)
    {
        return $this->configInterface->isSetFlag(self::IS_RECOMMEND_RELATED_PRODUCTS_ENABLED, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * @param $storeId
     * @return bool
     */
    public function isRecommendFrequentlyBroughtTogetherEnabledOnCartPage($storeId = null)
    {
        return $this->configInterface->isSetFlag(self::IS_RECOMMEND_FREQUENTLY_BOUGHT_TOGETHER_ENABLED_ON_CART_PAGE, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * @param $storeId
     * @return bool
     */
    public function isRecommendRelatedProductsEnabledOnCartPage($storeId = null)
    {
        return $this->configInterface->isSetFlag(self::IS_RECOMMEND_RELATED_PRODUCTS_ENABLED_ON_CART_PAGE, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * @param $storeId
     * @return bool
     */
    public function isRemoveCoreRelatedProductsBlock($storeId = null)
    {
        return $this->configInterface->isSetFlag(self::IS_REMOVE_RELATED_PRODUCTS_BLOCK, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * @param $storeId
     * @return bool
     */
    public function isRemoveUpsellProductsBlock($storeId = null)
    {
        return $this->configInterface->isSetFlag(self::IS_REMOVE_UPSELL_PRODUCTS_BLOCK, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * @param $storeId
     * @return int
     */
    public function getNumberOfRelatedProducts($storeId = null)
    {
        return (int)$this->configInterface->getValue(
            self::NUM_OF_RECOMMEND_RELATED_PRODUCTS,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * @param $storeId
     * @return int
     */
    public function getNumberOfFrequentlyBoughtTogetherProducts($storeId = null)
    {
        return (int)$this->configInterface->getValue(
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
    public function getNumberOfTrendingItems($storeId = null)
    {
        return (int)$this->configInterface->getValue(
            self::NUM_OF_TRENDING_ITEMS,
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
    public function isAddToCartEnabledInFrequentlyBoughtTogether($storeId = null)
    {
        return $this->configInterface->isSetFlag(self::IS_ADDTOCART_ENABLED_IN_FREQUENTLY_BOUGHT_TOGETHER, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * @param $storeId
     * @return bool
     */
    public function isAddToCartEnabledInRelatedProducts($storeId = null)
    {
        return $this->configInterface->isSetFlag(self::IS_ADDTOCART_ENABLED_IN_RELATED_PRODUCTS, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * @param $storeId
     * @return bool
     */
    public function isAddToCartEnabledInTrendsItem($storeId = null)
    {
        return $this->configInterface->isSetFlag(self::IS_ADDTOCART_ENABLED_IN_TRENDS_ITEM, ScopeInterface::SCOPE_STORE, $storeId);
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

    /**
     * @param $storeId
     * @return bool
     */
    public function useAdaptiveImage($storeId = null)
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
     * @param $storeId
     * @return mixed
     */
    public function getInstantSelector($storeId = null)
    {
        return $this->configInterface->getValue(self::INSTANT_SELECTOR, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * @param $storeId
     * @return array
     */
    public function getExcludedPages($storeId = null)
    {
        $attrs = $this->unserialize($this->configInterface->getValue(
            self::EXCLUDED_PAGES,
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
     * @return mixed
     */
    public function getRenderTemplateDirectives($storeId = null)
    {
        return $this->configInterface->getValue(
            self::RENDER_TEMPLATE_DIRECTIVES,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * @param $storeId
     * @return bool
     */
    public function isAutocompleteDebugEnabled($storeId = null)
    {
        return $this->configInterface->isSetFlag(self::AUTOCOMPLETE_MENU_DEBUG, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function getAutocompleteDebounceMilliseconds($storeId = null): int
    {
        return (int) $this->configInterface->getValue(
            self::AUTOCOMPLETE_DEBOUNCE_MILLISEC,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function getAutocompleteMinimumCharacterLength($storeId = null): int
    {
        return (int) $this->configInterface->getValue(
            self::AUTOCOMPLETE_MINIMUM_CHAR_LENGTH,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * @param $originalIndexName
     * @param $storeId
     * @param $currentCustomerGroupId
     * @return array
     * @throws Magento\Framework\Exception\NoSuchEntityException
     */
    public function getSortingIndices($originalIndexName, $storeId = null, $currentCustomerGroupId = null)
    {
        $attrs = $this->getSorting($storeId);
        $currency = $this->getCurrencyCode($storeId);
        $attributesToAdd = [];
        foreach ($attrs as $key => $attr) {
            $indexName = false;
            $sortAttribute = false;
            if ($this->isCustomerGroupsEnabled($storeId) && $attr['attribute'] === 'price') {
                $groupCollection = $this->groupCollection;
                if (!is_null($currentCustomerGroupId)) {
                    $groupCollection->addFilter('customer_group_id', $currentCustomerGroupId);
                }
                foreach ($groupCollection as $group) {
                    $customerGroupId = (int)$group->getData('customer_group_id');
                    $groupIndexNameSuffix = 'group_' . $customerGroupId;
                    $groupIndexName =
                        $originalIndexName . '_' . $attr['attribute'] . '_' . $groupIndexNameSuffix . '_' . $attr['sort'];
                    $groupSortAttribute = $attr['attribute'] . '.' . $currency . '.' . $groupIndexNameSuffix;
                    $newAttr = [];
                    $newAttr['name'] = $groupIndexName;
                    $newAttr['attribute'] = $attr['attribute'];
                    $newAttr['sort'] = $attr['sort'];
                    $newAttr['sortLabel'] = $attr['sortLabel'];
                    if (!array_key_exists('label', $newAttr) && array_key_exists('sortLabel', $newAttr)) {
                        $newAttr['label'] = $newAttr['sortLabel'];
                    }
                    $newAttr['ranking'] = [
                        $newAttr['sort'] . '(' . $groupSortAttribute . ')',
                        'typo',
                        'geo',
                        'words',
                        'filters',
                        'proximity',
                        'attribute',
                        'exact',
                        'custom',
                    ];
                    $attributesToAdd[$newAttr['sort']][] = $newAttr;
                }
            } elseif ($attr['attribute'] === 'price') {
                $indexName = $originalIndexName . '_' . $attr['attribute'] . '_' . 'default' . '_' . $attr['sort'];
                $sortAttribute = $attr['attribute'] . '.' . $currency . '.' . 'default';
            } else {
                $indexName = $originalIndexName . '_' . $attr['attribute'] . '_' . $attr['sort'];
                $sortAttribute = $attr['attribute'];
            }
            if ($indexName && $sortAttribute) {
                $attrs[$key]['name'] = $indexName;
                if (!array_key_exists('label', $attrs[$key]) && array_key_exists('sortLabel', $attrs[$key])) {
                    $attrs[$key]['label'] = $attrs[$key]['sortLabel'];
                }
                $attrs[$key]['ranking'] = [
                    $attr['sort'] . '(' . $sortAttribute . ')',
                    'typo',
                    'geo',
                    'words',
                    'filters',
                    'proximity',
                    'attribute',
                    'exact',
                    'custom',
                ];
            }
        }
        $attrsToReturn = [];
        if (count($attributesToAdd) > 0) {
            foreach ($attrs as $key => $attr) {
                if ($attr['attribute'] == 'price' && isset($attributesToAdd[$attr['sort']])) {
                    $attrsToReturn = array_merge($attrsToReturn, $attributesToAdd[$attr['sort']]);
                } else {
                    $attrsToReturn[] = $attr;
                }
            }
        }
        if (count($attrsToReturn) > 0) {
            return $attrsToReturn;
        }
        if (is_array($attrs)) {
            return $attrs;
        }
        return [];
    }

    /***
     * @param $storeId
     * @return array|bool|float|int|mixed|string|null
     */
    public function getSorting($storeId = null)
    {
        return $this->unserialize($this->getRawSortingValue($storeId));
    }

    /**
     * @param $storeId
     * @return mixed
     */
    public function getRawSortingValue($storeId = null)
    {
        return $this->configInterface->getValue(
            self::SORTING_INDICES,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * @param $storeId
     * @return string
     * @throws Magento\Framework\Exception\NoSuchEntityException
     */
    public function getCurrencyCode($storeId = null)
    {
        /** @var Magento\Store\Model\Store $store */
        $store = $this->storeManager->getStore($storeId);
        return $store->getCurrentCurrencyCode();
    }

    /**
     * @param $storeId
     * @return bool
     */
    public function isCustomerGroupsEnabled($storeId = null)
    {
        return $this->configInterface->isSetFlag(self::CUSTOMER_GROUPS_ENABLE, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * @param $storeId
     * @return bool
     */
    public function credentialsAreConfigured($storeId = null)
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
     * @param $storeId
     * @return mixed
     */
    public function getIndexPrefix($storeId = null)
    {
        return $this->configInterface->getValue(self::INDEX_PREFIX, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * @param $storeId
     * @return array|bool|float|int|mixed|string
     */
    public function getFacets($storeId = null)
    {
        $attrs = $this->unserialize($this->configInterface->getValue(
            self::FACETS,
            ScopeInterface::SCOPE_STORE,
            $storeId
        ));
        if ($attrs) {
            foreach ($attrs as &$attr) {
                if ($attr['type'] === 'other') {
                    $attr['type'] = $attr['other_type'];
                }
            }
            if (is_array($attrs)) {
                return array_values($attrs);
            }
        }
        return [];
    }

    /**
     * @param $storeId
     * @return array
     */
    public function getCategoryCustomRanking($storeId = null)
    {
        $attrs = $this->unserialize($this->configInterface->getValue(
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
     * @return array
     */
    public function getProductCustomRanking($storeId = null)
    {
        $attrs = $this->unserialize($this->getRawProductCustomRanking($storeId));
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
     * @param $section
     * @param $storeId
     * @return string
     */
    public function getExtraSettings($section, $storeId = null)
    {
        $constant = 'EXTRA_SETTINGS_' . mb_strtoupper($section);
        $value = $this->configInterface->getValue(constant('self::' . $constant), ScopeInterface::SCOPE_STORE, $storeId);
        return trim((string)$value);
    }

    /**
     * @param $storeId
     * @return bool
     */
    public function preventBackendRendering($storeId = null)
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
        $userAgent = mb_strtolower($_SERVER['HTTP_USER_AGENT'], 'utf-8');
        $allowedUserAgents = $this->configInterface->getValue(
            self::BACKEND_RENDERING_ALLOWED_USER_AGENTS,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
        $allowedUserAgents = trim($allowedUserAgents);
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
     * @return int
     * @throws Magento\Framework\Exception\NoSuchEntityException
     */
    public function getStoreId()
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
     * @throws Magento\Framework\Exception\NoSuchEntityException
     */
    public function getCurrency($storeId = null)
    {
        /** @var Magento\Store\Model\Store $store */
        $store = $this->storeManager->getStore($storeId);
        return $this->currency->getCurrency($store->getCurrentCurrencyCode())->getSymbol();
    }

    /**
     * @param $storeId
     * @return bool
     */
    public function showSuggestionsOnNoResultsPage($storeId = null)
    {
        return $this->configInterface->isSetFlag(
            self::SHOW_SUGGESTIONS_NO_RESULTS,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
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
            'objectID',
            'name',
            'url',
            'visibility_search',
            'visibility_catalog',
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
     * @param $storeId
     * @return array
     */
    public function getProductAdditionalAttributes($storeId = null)
    {
        $attributes = $this->unserialize($this->configInterface->getValue(
            self::PRODUCT_ATTRIBUTES,
            ScopeInterface::SCOPE_STORE,
            $storeId
        ));

        $facets = $this->unserialize($this->configInterface->getValue(
            self::FACETS,
            ScopeInterface::SCOPE_STORE,
            $storeId
        ));
        $attributes = $this->addIndexableAttributes($attributes, $facets, '0');

        $sorts = $this->unserialize($this->configInterface->getValue(
            self::SORTING_INDICES,
            ScopeInterface::SCOPE_STORE,
            $storeId
        ));
        $attributes = $this->addIndexableAttributes($attributes, $sorts, '0');

        $customRankings = $this->unserialize($this->configInterface->getValue(
            self::PRODUCT_CUSTOM_RANKING,
            ScopeInterface::SCOPE_STORE,
            $storeId
        ));
        $customRankings = $customRankings ?: [];
        $customRankings = array_filter($customRankings, function ($customRanking) {
            return $customRanking['attribute'] !== 'custom_attribute';
        });
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
    public function getCategoryAdditionalAttributes($storeId = null)
    {
        $attributes = $this->unserialize($this->configInterface->getValue(
            self::CATEGORY_ATTRIBUTES,
            ScopeInterface::SCOPE_STORE,
            $storeId
        ));
        $customRankings = $this->unserialize($this->configInterface->getValue(
            self::CATEGORY_CUSTOM_RANKING,
            ScopeInterface::SCOPE_STORE,
            $storeId
        ));
        $customRankings = $customRankings ?: [];
        $customRankings = array_filter($customRankings, function ($customRanking) {
            return $customRanking['attribute'] !== 'custom_attribute';
        });
        $attributes = $this->addIndexableAttributes($attributes, $customRankings, '0', '0');
        if (is_array($attributes)) {
            return $attributes;
        }
        return [];
    }

    /**
     * @param $storeId
     * @return string
     */
    public function getCategorySeparator($storeId = null): string
    {
        return (string) $this->configInterface->getValue(self::CATEGORY_SEPARATOR, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
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

    /**
     * @param $storeId
     * @return bool
     */
    public function isClickConversionAnalyticsEnabled($storeId = null)
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
    public function isAnalyticsEnabled($storeId = null)
    {
        return $this->configInterface->isSetFlag(self::GA_ENABLE, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * @param $storeId
     * @return array
     */
    public function getNonCastableAttributes($storeId = null)
    {
        $nonCastableAttributes = [];
        $config = $this->unserialize($this->configInterface->getValue(
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
    public function getMaxRecordSizeLimit($storeId = null)
    {
        return (int)$this->configInterface->getValue(
            self::MAX_RECORD_SIZE_LIMIT,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * @param $storeId
     * @return bool
     */
    public function isQuerySuggestionsIndexEnabled($storeId = null)
    {
        return $this->configInterface->isSetFlag(self::ENABLE_QUERY_SUGGESTIONS_INDEX, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * @param $storeId
     * @return bool
     */
    public function isPagesIndexEnabled($storeId = null)
    {
        return $this->configInterface->isSetFlag(self::ENABLE_PAGES_INDEX, ScopeInterface::SCOPE_STORE, $storeId);
    }

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

    /**
     * @param $storeId
     * @return mixed
     */
    public function useVirtualReplica($storeId = null) {
        return $this->configInterface->isSetFlag(self::USE_VIRTUAL_REPLICA_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * @param $storeId
     * @return mixed
     */
    public function isAutocompleteNavigatorEnabled($storeId = null)
    {
        return $this->configInterface->isSetFlag(self::AUTOCOMPLETE_KEYBORAD_NAVIAGATION,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }
}
