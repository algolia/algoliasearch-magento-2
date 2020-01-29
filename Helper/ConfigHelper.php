<?php

namespace Algolia\AlgoliaSearch\Helper;

use Algolia\AlgoliaSearch\Helper\Entity\SuggestionHelper;
use Magento;
use Magento\Directory\Model\Currency as DirCurrency;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\DataObject;
use Magento\Framework\Locale\Currency;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

class ConfigHelper
{
    const ENABLE_FRONTEND = 'algoliasearch_credentials/credentials/enable_frontend';
    const ENABLE_BACKEND = 'algoliasearch_credentials/credentials/enable_backend';
    const LOGGING_ENABLED = 'algoliasearch_credentials/credentials/debug';
    const APPLICATION_ID = 'algoliasearch_credentials/credentials/application_id';
    const API_KEY = 'algoliasearch_credentials/credentials/api_key';
    const SEARCH_ONLY_API_KEY = 'algoliasearch_credentials/credentials/search_only_api_key';
    const INDEX_PREFIX = 'algoliasearch_credentials/credentials/index_prefix';

    const IS_INSTANT_ENABLED = 'algoliasearch_instant/instant/is_instant_enabled';
    const REPLACE_CATEGORIES = 'algoliasearch_instant/instant/replace_categories';
    const INSTANT_SELECTOR = 'algoliasearch_instant/instant/instant_selector';
    const NUMBER_OF_PRODUCT_RESULTS = 'algoliasearch_instant/instant/number_product_results';
    const FACETS = 'algoliasearch_instant/instant/facets';
    const MAX_VALUES_PER_FACET = 'algoliasearch_instant/instant/max_values_per_facet';
    const SORTING_INDICES = 'algoliasearch_instant/instant/sorts';
    const SHOW_SUGGESTIONS_NO_RESULTS = 'algoliasearch_instant/instant/show_suggestions_on_no_result_page';
    const XML_ADD_TO_CART_ENABLE = 'algoliasearch_instant/instant/add_to_cart_enable';
    const INFINITE_SCROLL_ENABLE = 'algoliasearch_instant/instant/infinite_scroll_enable';
    const BACKEND_RENDERING_ENABLE = 'algoliasearch_instant/instant/backend_rendering_enable';

    const IS_POPUP_ENABLED = 'algoliasearch_autocomplete/autocomplete/is_popup_enabled';
    const NB_OF_PRODUCTS_SUGGESTIONS = 'algoliasearch_autocomplete/autocomplete/nb_of_products_suggestions';
    const NB_OF_CATEGORIES_SUGGESTIONS = 'algoliasearch_autocomplete/autocomplete/nb_of_categories_suggestions';
    const NB_OF_QUERIES_SUGGESTIONS = 'algoliasearch_autocomplete/autocomplete/nb_of_queries_suggestions';
    const AUTOCOMPLETE_SECTIONS = 'algoliasearch_autocomplete/autocomplete/sections';
    const EXCLUDED_PAGES = 'algoliasearch_autocomplete/autocomplete/excluded_pages';
    const MIN_POPULARITY = 'algoliasearch_autocomplete/autocomplete/min_popularity';
    const MIN_NUMBER_OF_RESULTS = 'algoliasearch_autocomplete/autocomplete/min_number_of_results';
    const RENDER_TEMPLATE_DIRECTIVES = 'algoliasearch_autocomplete/autocomplete/render_template_directives';
    const AUTOCOMPLETE_MENU_DEBUG = 'algoliasearch_autocomplete/autocomplete/debug';

    const PRODUCT_ATTRIBUTES = 'algoliasearch_products/products/product_additional_attributes';
    const PRODUCT_CUSTOM_RANKING = 'algoliasearch_products/products/custom_ranking_product_attributes';
    const USE_ADAPTIVE_IMAGE = 'algoliasearch_products/products/use_adaptive_image';
    const INDEX_OUT_OF_STOCK_OPTIONS = 'algoliasearch_products/products/index_out_of_stock_options';

    const CATEGORY_ATTRIBUTES = 'algoliasearch_categories/categories/category_additional_attributes';
    const INDEX_PRODUCT_COUNT = 'algoliasearch_categories/categories/index_product_count';
    const CATEGORY_CUSTOM_RANKING = 'algoliasearch_categories/categories/custom_ranking_category_attributes';
    const SHOW_CATS_NOT_INCLUDED_IN_NAV = 'algoliasearch_categories/categories/show_cats_not_included_in_navigation';
    const INDEX_EMPTY_CATEGORIES = 'algoliasearch_categories/categories/index_empty_categories';

    const IS_ACTIVE = 'algoliasearch_queue/queue/active';
    const NUMBER_OF_JOB_TO_RUN = 'algoliasearch_queue/queue/number_of_job_to_run';
    const RETRY_LIMIT = 'algoliasearch_queue/queue/number_of_retries';

    const XML_PATH_IMAGE_WIDTH = 'algoliasearch_images/image/width';
    const XML_PATH_IMAGE_HEIGHT = 'algoliasearch_images/image/height';
    const XML_PATH_IMAGE_TYPE = 'algoliasearch_images/image/type';

    const ENABLE_SYNONYMS = 'algoliasearch_synonyms/synonyms_group/enable_synonyms';
    const SYNONYMS = 'algoliasearch_synonyms/synonyms_group/synonyms';
    const ONEWAY_SYNONYMS = 'algoliasearch_synonyms/synonyms_group/oneway_synonyms';
    const SYNONYMS_FILE = 'algoliasearch_synonyms/synonyms_group/synonyms_file';

    const CC_ANALYTICS_ENABLE = 'algoliasearch_cc_analytics/cc_analytics_group/enable';
    const CC_ANALYTICS_IS_SELECTOR = 'algoliasearch_cc_analytics/cc_analytics_group/is_selector';
    const CC_CONVERSION_ANALYTICS_MODE = 'algoliasearch_cc_analytics/cc_analytics_group/conversion_analytics_mode';
    const CC_ADD_TO_CART_SELECTOR = 'algoliasearch_cc_analytics/cc_analytics_group/add_to_cart_selector';

    const GA_ENABLE = 'algoliasearch_analytics/analytics_group/enable';
    const GA_DELAY = 'algoliasearch_analytics/analytics_group/delay';
    const GA_TRIGGER_ON_UI_INTERACTION = 'algoliasearch_analytics/analytics_group/trigger_on_ui_interaction';
    const GA_PUSH_INITIAL_SEARCH = 'algoliasearch_analytics/analytics_group/push_initial_search';

    const NUMBER_OF_ELEMENT_BY_PAGE = 'algoliasearch_advanced/advanced/number_of_element_by_page';
    const REMOVE_IF_NO_RESULT = 'algoliasearch_advanced/advanced/remove_words_if_no_result';
    const PARTIAL_UPDATES = 'algoliasearch_advanced/advanced/partial_update';
    const CUSTOMER_GROUPS_ENABLE = 'algoliasearch_advanced/advanced/customer_groups_enable';
    const REMOVE_PUB_DIR_IN_URL = 'algoliasearch_advanced/advanced/remove_pub_dir_in_url';
    const MAKE_SEO_REQUEST = 'algoliasearch_advanced/advanced/make_seo_request';
    const REMOVE_BRANDING = 'algoliasearch_advanced/advanced/remove_branding';
    const AUTOCOMPLETE_SELECTOR = 'algoliasearch_advanced/advanced/autocomplete_selector';
    const IDX_PRODUCT_ON_CAT_PRODUCTS_UPD = 'algoliasearch_advanced/advanced/index_product_on_category_products_update';
    const PREVENT_BACKEND_RENDERING = 'algoliasearch_advanced/advanced/prevent_backend_rendering';
    const PREVENT_BACKEND_RENDERING_DISPLAY_MODE =
        'algoliasearch_advanced/advanced/prevent_backend_rendering_display_mode';
    const BACKEND_RENDERING_ALLOWED_USER_AGENTS =
        'algoliasearch_advanced/advanced/backend_rendering_allowed_user_agents';
    const NON_CASTABLE_ATTRIBUTES = 'algoliasearch_advanced/advanced/non_castable_attributes';
    const MAX_RECORD_SIZE_LIMIT = 'algoliasearch_advanced/advanced/max_record_size_limit';
    const ARCHIVE_LOG_CLEAR_LIMIT = 'algoliasearch_advanced/advanced/archive_clear_limit';

    const SHOW_OUT_OF_STOCK = 'cataloginventory/options/show_out_of_stock';

    const USE_SECURE_IN_FRONTEND = 'web/secure/use_in_frontend';
    const CATALOG_SEARCH_ENGINE = 'catalog/search/engine';

    const EXTRA_SETTINGS_PRODUCTS = 'algoliasearch_extra_settings/extra_settings/products_extra_settings';
    const EXTRA_SETTINGS_CATEGORIES = 'algoliasearch_extra_settings/extra_settings/categories_extra_settings';
    const EXTRA_SETTINGS_PAGES = 'algoliasearch_extra_settings/extra_settings/pages_extra_settings';
    const EXTRA_SETTINGS_SUGGESTIONS = 'algoliasearch_extra_settings/extra_settings/suggestions_extra_settings';
    const EXTRA_SETTINGS_ADDITIONAL_SECTIONS =
        'algoliasearch_extra_settings/extra_settings/additional_sections_extra_settings';

    const DEFAULT_MAX_RECORD_SIZE = 10000;

    private $configInterface;
    private $objectManager;
    private $currency;
    private $storeManager;
    private $dirCurrency;
    private $directoryList;
    private $moduleResource;
    private $productMetadata;
    private $eventManager;
    private $currencyManager;
    private $serializer;
    private $maxRecordSize;

    public function __construct(
        Magento\Framework\App\Config\ScopeConfigInterface $configInterface,
        Magento\Framework\ObjectManagerInterface $objectManager,
        StoreManagerInterface $storeManager,
        Currency $currency,
        DirCurrency $dirCurrency,
        DirectoryList $directoryList,
        Magento\Framework\Module\ResourceInterface $moduleResource,
        Magento\Framework\App\ProductMetadataInterface $productMetadata,
        Magento\Framework\Event\ManagerInterface $eventManager,
        Magento\Directory\Model\Currency $currencyManager,
        SerializerInterface $serializer
    ) {
        $this->objectManager = $objectManager;
        $this->configInterface = $configInterface;
        $this->currency = $currency;
        $this->storeManager = $storeManager;
        $this->dirCurrency = $dirCurrency;
        $this->directoryList = $directoryList;
        $this->moduleResource = $moduleResource;
        $this->productMetadata = $productMetadata;
        $this->eventManager = $eventManager;
        $this->currencyManager = $currencyManager;
        $this->serializer = $serializer;
    }

    public function indexOutOfStockOptions($storeId = null)
    {
        return $this->configInterface->isSetFlag(
            self::INDEX_OUT_OF_STOCK_OPTIONS,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function showCatsNotIncludedInNavigation($storeId = null)
    {
        return $this->configInterface->isSetFlag(
            self::SHOW_CATS_NOT_INCLUDED_IN_NAV,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function shouldIndexEmptyCategories($storeId = null)
    {
        return $this->configInterface->isSetFlag(self::INDEX_EMPTY_CATEGORIES, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function getMagentoVersion()
    {
        return $this->productMetadata->getVersion();
    }

    public function getMagentoEdition()
    {
        return $this->productMetadata->getEdition();
    }

    public function getExtensionVersion()
    {
        return $this->moduleResource->getDbVersion('Algolia_AlgoliaSearch');
    }

    public function isDefaultSelector($storeId = null)
    {
        return '.algolia-search-input' === $this->getAutocompleteSelector($storeId);
    }

    public function getAutocompleteSelector($storeId = null)
    {
        return $this->configInterface->getValue(self::AUTOCOMPLETE_SELECTOR, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function indexProductOnCategoryProductsUpdate($storeId = null)
    {
        return $this->configInterface->getValue(
            self::IDX_PRODUCT_ON_CAT_PRODUCTS_UPD,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function getNumberOfQueriesSuggestions($storeId = null)
    {
        return (int) $this->configInterface->getValue(
            self::NB_OF_QUERIES_SUGGESTIONS,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function getNumberOfProductsSuggestions($storeId = null)
    {
        return (int) $this->configInterface->getValue(
            self::NB_OF_PRODUCTS_SUGGESTIONS,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function getNumberOfCategoriesSuggestions($storeId = null)
    {
        return (int) $this->configInterface->getValue(
            self::NB_OF_CATEGORIES_SUGGESTIONS,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function showSuggestionsOnNoResultsPage($storeId = null)
    {
        return $this->configInterface->isSetFlag(
            self::SHOW_SUGGESTIONS_NO_RESULTS,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function isEnabledFrontEnd($storeId = null)
    {
        return $this->configInterface->isSetFlag(self::ENABLE_FRONTEND, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function isEnabledBackend($storeId = null)
    {
        return $this->configInterface->isSetFlag(self::ENABLE_BACKEND, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function makeSeoRequest($storeId = null)
    {
        return $this->configInterface->isSetFlag(self::MAKE_SEO_REQUEST, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function isLoggingEnabled($storeId = null)
    {
        return $this->configInterface->isSetFlag(self::LOGGING_ENABLED, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function getShowOutOfStock($storeId = null)
    {
        return $this->configInterface->isSetFlag(self::SHOW_OUT_OF_STOCK, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function useSecureUrlsInFrontend($storeId = null)
    {
        return $this->configInterface->isSetFlag(
            self::USE_SECURE_IN_FRONTEND,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

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

        return (int) $imageWidth;
    }

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

        return (int) $imageHeight;
    }

    public function getImageType($storeId = null)
    {
        return $this->configInterface->getValue(self::XML_PATH_IMAGE_TYPE, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function isCustomerGroupsEnabled($storeId = null)
    {
        return $this->configInterface->isSetFlag(self::CUSTOMER_GROUPS_ENABLE, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function shouldRemovePubDirectory($storeId = null)
    {
        return $this->configInterface->isSetFlag(self::REMOVE_PUB_DIR_IN_URL, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function isPartialUpdateEnabled($storeId = null)
    {
        return $this->configInterface->isSetFlag(self::PARTIAL_UPDATES, ScopeInterface::SCOPE_STORE, $storeId);
    }

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

    public function getMinPopularity($storeId = null)
    {
        return (int) $this->configInterface->getValue(self::MIN_POPULARITY, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function getMinNumberOfResults($storeId = null)
    {
        return (int) $this->configInterface->getValue(self::MIN_NUMBER_OF_RESULTS, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function isAddToCartEnable($storeId = null)
    {
        return $this->configInterface->isSetFlag(
            self::XML_ADD_TO_CART_ENABLE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function isInfiniteScrollEnabled($storeId = null)
    {
        return $this->isInstantEnabled($storeId)
            && $this->configInterface->isSetFlag(self::INFINITE_SCROLL_ENABLE, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function isBackendRenderingEnabled($storeId = null)
    {
        return $this->configInterface->isSetFlag(self::BACKEND_RENDERING_ENABLE, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function isRemoveBranding($storeId = null)
    {
        return $this->configInterface->isSetFlag(self::REMOVE_BRANDING, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function getMaxValuesPerFacet($storeId = null)
    {
        return (int) $this->configInterface->getValue(self::MAX_VALUES_PER_FACET, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function getNumberOfElementByPage($storeId = null)
    {
        return (int) $this->configInterface->getValue(self::NUMBER_OF_ELEMENT_BY_PAGE, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function getNumberOfJobToRun($storeId = null)
    {
        return (int) $this->configInterface->getValue(self::NUMBER_OF_JOB_TO_RUN, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function getRetryLimit($storeId = null)
    {
        return (int) $this->configInterface->getValue(self::RETRY_LIMIT, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function isQueueActive($storeId = null)
    {
        return $this->configInterface->isSetFlag(self::IS_ACTIVE, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function getRemoveWordsIfNoResult($storeId = null)
    {
        return $this->configInterface->getValue(self::REMOVE_IF_NO_RESULT, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function getNumberOfProductResults($storeId = null)
    {
        return (int) $this->configInterface->getValue(
            self::NUMBER_OF_PRODUCT_RESULTS,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function replaceCategories($storeId = null)
    {
        return $this->configInterface->isSetFlag(self::REPLACE_CATEGORIES, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function isAutoCompleteEnabled($storeId = null)
    {
        return $this->configInterface->isSetFlag(self::IS_POPUP_ENABLED, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function isInstantEnabled($storeId = null)
    {
        return $this->configInterface->isSetFlag(self::IS_INSTANT_ENABLED, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function useAdaptiveImage($storeId = null)
    {
        return $this->configInterface->isSetFlag(self::USE_ADAPTIVE_IMAGE, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function getInstantSelector($storeId = null)
    {
        return $this->configInterface->getValue(self::INSTANT_SELECTOR, ScopeInterface::SCOPE_STORE, $storeId);
    }

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

    public function getRenderTemplateDirectives($storeId = null)
    {
        return $this->configInterface->getValue(
            self::RENDER_TEMPLATE_DIRECTIVES,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function isAutocompleteDebugEnabled($storeId = null)
    {
        return $this->configInterface->isSetFlag(self::AUTOCOMPLETE_MENU_DEBUG, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function getRawSortingValue($storeId = null)
    {
        return $this->configInterface->getValue(
            self::SORTING_INDICES,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function getSorting($storeId = null)
    {
        return $this->unserialize($this->getRawSortingValue($storeId));
    }

    public function getSortingIndices($originalIndexName, $storeId = null, $currentCustomerGroupId = null)
    {
        $attrs = $this->getSorting($storeId);

        $currency = $this->getCurrencyCode($storeId);
        $attributesToAdd = [];

        foreach ($attrs as $key => $attr) {
            $indexName = false;
            $sortAttribute = false;

            if ($this->isCustomerGroupsEnabled($storeId) && $attr['attribute'] === 'price') {
                /** @var Magento\Customer\Model\ResourceModel\Group\Collection $groupCollection */
                $groupCollection = $this->objectManager->get('Magento\Customer\Model\ResourceModel\Group\Collection');

                if (!is_null($currentCustomerGroupId)) {
                    $groupCollection->addFilter('customer_group_id', $currentCustomerGroupId);
                }

                foreach ($groupCollection as $group) {
                    $customerGroupId = (int) $group->getData('customer_group_id');
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

    public function getApplicationID($storeId = null)
    {
        return $this->configInterface->getValue(self::APPLICATION_ID, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function getAPIKey($storeId = null)
    {
        return $this->configInterface->getValue(self::API_KEY, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function getSearchOnlyAPIKey($storeId = null)
    {
        return $this->configInterface->getValue(self::SEARCH_ONLY_API_KEY, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function credentialsAreConfigured($storeId = null)
    {
        return $this->getApplicationID($storeId) &&
            $this->getAPIKey($storeId) &&
            $this->getSearchOnlyAPIKey($storeId);
    }

    public function getIndexPrefix($storeId = null)
    {
        return $this->configInterface->getValue(self::INDEX_PREFIX, ScopeInterface::SCOPE_STORE, $storeId);
    }

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
     * @param int|null $storeId
     *
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
     * @param int|null $storeId
     *
     * @return array|mixed
     */
    public function getProductCustomRanking($storeId = null)
    {
        $attrs = $this->unserialize($this->getRawProductCustomRanking($storeId));

        if (is_array($attrs)) {
            return $attrs;
        }

        return [];
    }

    public function getExtraSettings($section, $storeId = null)
    {
        $constant = 'EXTRA_SETTINGS_' . mb_strtoupper($section);

        $value = $this->configInterface->getValue(constant('self::' . $constant), ScopeInterface::SCOPE_STORE, $storeId);

        return trim($value);
    }

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

    public function getBackendRenderingDisplayMode($storeId = null)
    {
        return $this->configInterface->getValue(
            self::PREVENT_BACKEND_RENDERING_DISPLAY_MODE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function getStoreId()
    {
        return $this->storeManager->getStore()->getId();
    }

    public function getStoreLocale($storeId)
    {
        return $this->configInterface->getValue(
            \Magento\Directory\Helper\Data::XML_PATH_DEFAULT_LOCALE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function getCurrency($storeId = null)
    {
        /** @var Magento\Store\Model\Store $store */
        $store = $this->storeManager->getStore($storeId);
        $currencySymbol = $this->currency->getCurrency($store->getCurrentCurrencyCode())->getSymbol();

        return $currencySymbol;
    }

    public function getCurrencyCode($storeId = null)
    {
        /** @var Magento\Store\Model\Store $store */
        $store = $this->storeManager->getStore($storeId);
        $code = $store->getCurrentCurrencyCode();

        return $code;
    }

    public function getPopularQueries($storeId = null)
    {
        if (!$this->isInstantEnabled($storeId) || !$this->showSuggestionsOnNoResultsPage($storeId)) {
            return [];
        }

        if ($storeId === null) {
            $storeId = $this->storeManager->getStore()->getId();
        }

        /** @var SuggestionHelper $suggestionHelper */
        $suggestionHelper = $this->objectManager->create('Algolia\AlgoliaSearch\Helper\Entity\SuggestionHelper');
        $popularQueries = $suggestionHelper->getPopularQueries($storeId);

        return $popularQueries;
    }

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
        ]);

        $currencies = $this->dirCurrency->getConfigAllowCurrencies();

        foreach ($currencies as $currency) {
            $attributes[] = 'price.' . $currency . '.default';
            $attributes[] = 'price.' . $currency . '.default_tier';
            $attributes[] = 'price.' . $currency . '.default_formated';
            $attributes[] = 'price.' . $currency . '.default_original_formated';
            $attributes[] = 'price.' . $currency . '.default_tier_formated';
            $attributes[] = 'price.' . $currency . '.group_' . $groupId;
            $attributes[] = 'price.' . $currency . '.group_' . $groupId . '_tier';
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

    public function isEnabledSynonyms($storeId = null)
    {
        return $this->configInterface->isSetFlag(self::ENABLE_SYNONYMS, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function getSynonyms($storeId = null)
    {
        $synonyms = $this->unserialize($this->configInterface->getValue(
            self::SYNONYMS,
            ScopeInterface::SCOPE_STORE,
            $storeId
        ));

        if (is_array($synonyms)) {
            return $synonyms;
        }

        return [];
    }

    public function getOnewaySynonyms($storeId = null)
    {
        $onewaySynonyms = $this->unserialize($this->configInterface->getValue(
            self::ONEWAY_SYNONYMS,
            ScopeInterface::SCOPE_STORE,
            $storeId
        ));

        if (is_array($onewaySynonyms)) {
            return $onewaySynonyms;
        }

        return [];
    }

    public function getSynonymsFile($storeId = null)
    {
        $filename = $this->configInterface->getValue(self::SYNONYMS_FILE, ScopeInterface::SCOPE_STORE, $storeId);

        if (!$filename) {
            return null;
        }

        $baseDirectory = $this->directoryList->getPath(DirectoryList::MEDIA);

        return $baseDirectory . '/algoliasearch_admin_config_uploads/' . $filename;
    }

    public function isClickConversionAnalyticsEnabled($storeId = null)
    {
        return $this->configInterface->isSetFlag(self::CC_ANALYTICS_ENABLE, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function getClickConversionAnalyticsISSelector($storeId = null)
    {
        return $this->configInterface->getValue(self::CC_ANALYTICS_IS_SELECTOR, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function getConversionAnalyticsMode($storeId = null)
    {
        return $this->configInterface->getValue(
            self::CC_CONVERSION_ANALYTICS_MODE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function getConversionAnalyticsAddToCartSelector($storeId = null)
    {
        return $this->configInterface->getValue(self::CC_ADD_TO_CART_SELECTOR, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function isAnalyticsEnabled($storeId = null)
    {
        return $this->configInterface->isSetFlag(self::GA_ENABLE, ScopeInterface::SCOPE_STORE, $storeId);
    }

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

    private function addIndexableAttributes(
        $attributes,
        $addedAttributes,
        $searchable = '1',
        $retrievable = '1',
        $indexNoValue = '1'
    ) {
        foreach ((array) $addedAttributes as $addedAttribute) {
            foreach ((array) $attributes as $attribute) {
                if ($addedAttribute['attribute'] === $attribute['attribute']) {
                    continue 2;
                }
            }

            $attributes[] = [
                'attribute'         => $addedAttribute['attribute'],
                'searchable'        => $searchable,
                'retrievable'       => $retrievable,
                'index_no_value'    => $indexNoValue,
            ];
        }

        return $attributes;
    }

    private function unserialize($value)
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

    public function getDefaultMaxRecordSize()
    {
        return self::DEFAULT_MAX_RECORD_SIZE;
    }

    public function getMaxRecordSizeLimit($storeId = null)
    {
        if ($this->maxRecordSize) {
            return $this->maxRecordSize;
        }

        $configValue = $this->configInterface->getValue(self::MAX_RECORD_SIZE_LIMIT, ScopeInterface::SCOPE_STORE, $storeId);
        if ($configValue) {
            $this->maxRecordSize = $configValue;

            return $this->maxRecordSize;
        }
        /** @var \Algolia\AlgoliaSearch\Helper\ProxyHelper $proxyHelper */
        $proxyHelper = $this->objectManager->create('Algolia\AlgoliaSearch\Helper\ProxyHelper');
        $clientData = $proxyHelper->getClientConfigurationData();
        if ($clientData && isset($clientData['max_record_size'])) {
            /** @var \Magento\Framework\App\Config\Storage\Writer $configWriter */
            $configWriter = $this->objectManager->create('Magento\Framework\App\Config\Storage\Writer');
            $configWriter->save(self::MAX_RECORD_SIZE_LIMIT, $clientData['max_record_size']);
            $this->maxRecordSize = $clientData['max_record_size'];
        } else {
            $this->maxRecordSize = self::getDefaultMaxRecordSize();
        }

        return $this->maxRecordSize;
    }

    public function getArchiveLogClearLimit($storeId = null)
    {
        return (int) $this->configInterface->getValue(
            self::ARCHIVE_LOG_CLEAR_LIMIT,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function getCatalogSearchEngine($storeId = null)
    {
        return $this->configInterface->getValue(self::CATALOG_SEARCH_ENGINE, ScopeInterface::SCOPE_STORE, $storeId);
    }
}
