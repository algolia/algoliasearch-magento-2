<?php

namespace Algolia\AlgoliaSearch\Helper;

use Algolia\AlgoliaSearch\Helper\Entity\ProductHelper;
use Algolia\AlgoliaSearch\Helper\Entity\SuggestionHelper;
use Magento;
use Magento\Directory\Model\Currency as DirCurrency;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\DataObject;
use Magento\Framework\Locale\Currency;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

class ConfigHelper
{
    const ENABLE_FRONTEND = 'algoliasearch_credentials/credentials/enable_frontend';
    const ENABLE_BACKEND = 'algoliasearch_credentials/credentials/enable_backend';
    const LOGGING_ENABLED = 'algoliasearch_credentials/credentials/debug';
    const IS_POPUP_ENABLED = 'algoliasearch_credentials/credentials/is_popup_enabled';
    const APPLICATION_ID = 'algoliasearch_credentials/credentials/application_id';
    const API_KEY = 'algoliasearch_credentials/credentials/api_key';
    const SEARCH_ONLY_API_KEY = 'algoliasearch_credentials/credentials/search_only_api_key';
    const INDEX_PREFIX = 'algoliasearch_credentials/credentials/index_prefix';
    const IS_INSTANT_ENABLED = 'algoliasearch_credentials/credentials/is_instant_enabled';

    const REPLACE_CATEGORIES = 'algoliasearch_instant/instant/replace_categories';
    const INSTANT_SELECTOR = 'algoliasearch_instant/instant/instant_selector';
    const FACETS = 'algoliasearch_instant/instant/facets';
    const MAX_VALUES_PER_FACET = 'algoliasearch_instant/instant/max_values_per_facet';
    const SORTING_INDICES = 'algoliasearch_instant/instant/sorts';
    const XML_ADD_TO_CART_ENABLE = 'algoliasearch_instant/instant/add_to_cart_enable';

    const NB_OF_PRODUCTS_SUGGESTIONS = 'algoliasearch_autocomplete/autocomplete/nb_of_products_suggestions';
    const NB_OF_CATEGORIES_SUGGESTIONS = 'algoliasearch_autocomplete/autocomplete/nb_of_categories_suggestions';
    const NB_OF_QUERIES_SUGGESTIONS = 'algoliasearch_autocomplete/autocomplete/nb_of_queries_suggestions';
    const AUTOCOMPLETE_SECTIONS = 'algoliasearch_autocomplete/autocomplete/sections';
    const EXCLUDED_PAGES = 'algoliasearch_autocomplete/autocomplete/excluded_pages';
    const MIN_POPULARITY = 'algoliasearch_autocomplete/autocomplete/min_popularity';
    const MIN_NUMBER_OF_RESULTS = 'algoliasearch_autocomplete/autocomplete/min_number_of_results';
    const RENDER_TEMPLATE_DIRECTIVES = 'algoliasearch_autocomplete/autocomplete/render_template_directives';
    const AUTOCOMPLETE_MENU_DEBUG = 'algoliasearch_autocomplete/autocomplete/debug';

    const NUMBER_OF_PRODUCT_RESULTS = 'algoliasearch_products/products/number_product_results';
    const PRODUCT_ATTRIBUTES = 'algoliasearch_products/products/product_additional_attributes';
    const PRODUCT_CUSTOM_RANKING = 'algoliasearch_products/products/custom_ranking_product_attributes';
    const SHOW_SUGGESTIONS_NO_RESULTS = 'algoliasearch_products/products/show_suggestions_on_no_result_page';
    const INDEX_OUT_OF_STOCK_OPTIONS = 'algoliasearch_products/products/index_out_of_stock_options';

    const CATEGORY_ATTRIBUTES = 'algoliasearch_categories/categories/category_additional_attributes';
    const INDEX_PRODUCT_COUNT = 'algoliasearch_categories/categories/index_product_count';
    const CATEGORY_CUSTOM_RANKING = 'algoliasearch_categories/categories/custom_ranking_category_attributes';
    const SHOW_CATS_NOT_INCLUDED_IN_NAVIGATION = 'algoliasearch_categories/categories/show_cats_not_included_in_navigation';

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

    const ANALYTICS_ENABLE = 'algoliasearch_analytics/analytics_group/enable';
    const ANALYTICS_DELAY = 'algoliasearch_analytics/analytics_group/delay';
    const ANALYTICS_TRIGGER_ON_UI_INTERACTION = 'algoliasearch_analytics/analytics_group/trigger_on_ui_interaction';
    const ANALYTICS_PUSH_INITIAL_SEARCH = 'algoliasearch_analytics/analytics_group/push_initial_search';

    const NUMBER_OF_ELEMENT_BY_PAGE = 'algoliasearch_advanced/advanced/number_of_element_by_page';
    const REMOVE_IF_NO_RESULT = 'algoliasearch_advanced/advanced/remove_words_if_no_result';
    const PARTIAL_UPDATES = 'algoliasearch_advanced/advanced/partial_update';
    const CUSTOMER_GROUPS_ENABLE = 'algoliasearch_advanced/advanced/customer_groups_enable';
    const REMOVE_PUB_DIR_IN_URL = 'algoliasearch_advanced/advanced/remove_pub_dir_in_url';
    const MAKE_SEO_REQUEST = 'algoliasearch_advanced/advanced/make_seo_request';
    const REMOVE_BRANDING = 'algoliasearch_advanced/advanced/remove_branding';
    const AUTOCOMPLETE_SELECTOR = 'algoliasearch_advanced/advanced/autocomplete_selector';
    const INDEX_PRODUCT_ON_CATEGORY_PRODUCTS_UPDATE = 'algoliasearch_advanced/advanced/index_product_on_category_products_update';

    const SHOW_OUT_OF_STOCK = 'cataloginventory/options/show_out_of_stock';

    const USE_SECURE_IN_FRONTEND = 'web/secure/use_in_frontend';

    const EXTRA_SETTINGS_PRODUCTS = 'algoliasearch_extra_settings/extra_settings/products_extra_settings';
    const EXTRA_SETTINGS_CATEGORIES = 'algoliasearch_extra_settings/extra_settings/categories_extra_settings';
    const EXTRA_SETTINGS_PAGES = 'algoliasearch_extra_settings/extra_settings/pages_extra_settings';
    const EXTRA_SETTINGS_SUGGESTIONS = 'algoliasearch_extra_settings/extra_settings/suggestions_extra_settings';
    const EXTRA_SETTINGS_ADDITIONAL_SECTIONS = 'algoliasearch_extra_settings/extra_settings/additional_sections_extra_settings';

    protected $_productTypeMap = [];

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

    public function __construct(Magento\Framework\App\Config\ScopeConfigInterface $configInterface,
                                Magento\Framework\ObjectManagerInterface $objectManager,
                                StoreManagerInterface $storeManager,
                                Currency $currency,
                                DirCurrency $dirCurrency,
                                DirectoryList $directoryList,
                                Magento\Framework\Module\ResourceInterface $moduleResource,
                                Magento\Framework\App\ProductMetadataInterface $productMetadata,
                                Magento\Framework\Event\ManagerInterface $eventManager,
                                Magento\Directory\Model\Currency $currencyManager)
    {
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
    }

    public function indexOutOfStockOptions($storeId = null)
    {
        return $this->configInterface->getValue(self::INDEX_OUT_OF_STOCK_OPTIONS, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function showCatsNotIncludedInNavigation($storeId = null)
    {
        return $this->configInterface->getValue(self::SHOW_CATS_NOT_INCLUDED_IN_NAVIGATION, ScopeInterface::SCOPE_STORE, $storeId);
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
        return $this->configInterface->getValue(self::INDEX_PRODUCT_ON_CATEGORY_PRODUCTS_UPDATE, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function getNumberOfQueriesSuggestions($storeId = null)
    {
        return $this->configInterface->getValue(self::NB_OF_QUERIES_SUGGESTIONS, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function getNumberOfProductsSuggestions($storeId = null)
    {
        return $this->configInterface->getValue(self::NB_OF_PRODUCTS_SUGGESTIONS, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function getNumberOfCategoriesSuggestions($storeId = null)
    {
        return $this->configInterface->getValue(self::NB_OF_CATEGORIES_SUGGESTIONS, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function showSuggestionsOnNoResultsPage($storeId = null)
    {
        return $this->configInterface->getValue(self::SHOW_SUGGESTIONS_NO_RESULTS, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function isEnabledFrontEnd($storeId = null)
    {
        // Frontend = Backend + Frontend
        return (bool) $this->configInterface->getValue(self::ENABLE_BACKEND, ScopeInterface::SCOPE_STORE, $storeId) && (bool) $this->configInterface->getValue(self::ENABLE_FRONTEND, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function isEnabledBackend($storeId = null)
    {
        return $this->configInterface->isSetFlag(self::ENABLE_BACKEND, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function makeSeoRequest($storeId = null)
    {
        return $this->configInterface->getValue(self::MAKE_SEO_REQUEST, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function isLoggingEnabled($storeId = null)
    {
        return $this->configInterface->getValue(self::LOGGING_ENABLED, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function getShowOutOfStock($storeId = null)
    {
        return (bool) $this->configInterface->getValue(self::SHOW_OUT_OF_STOCK, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function useSecureUrlsInFrontend($storeId = null)
    {
        return (bool) $this->configInterface->getValue(self::USE_SECURE_IN_FRONTEND, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function getImageWidth($storeId = null)
    {
        $imageWidth = $this->configInterface->getValue(self::XML_PATH_IMAGE_WIDTH, ScopeInterface::SCOPE_STORE, $storeId);
        if (empty($imageWidth)) {
            return 265;
        }

        return $imageWidth;
    }

    public function getImageHeight($storeId = null)
    {
        $imageHeight = $this->configInterface->getValue(self::XML_PATH_IMAGE_HEIGHT, ScopeInterface::SCOPE_STORE, $storeId);
        if (empty($imageHeight)) {
            return 265;
        }

        return $imageHeight;
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
        return $this->configInterface->getValue(self::PARTIAL_UPDATES, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function getAutocompleteSections($storeId = null)
    {
        $attrs = $this->unserialize($this->configInterface->getValue(self::AUTOCOMPLETE_SECTIONS, ScopeInterface::SCOPE_STORE, $storeId));

        if (is_array($attrs)) {
            return array_values($attrs);
        }

        return [];
    }

    public function getMinPopularity($storeId = null)
    {
        return $this->configInterface->getValue(self::MIN_POPULARITY, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function getMinNumberOfResults($storeId = null)
    {
        return $this->configInterface->getValue(self::MIN_NUMBER_OF_RESULTS, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function isAddToCartEnable($storeId = null)
    {
        return (bool) $this->configInterface->getValue(self::XML_ADD_TO_CART_ENABLE, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function isRemoveBranding($storeId = null)
    {
        return $this->configInterface->getValue(self::REMOVE_BRANDING, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function getMaxValuesPerFacet($storeId = null)
    {
        return $this->configInterface->getValue(self::MAX_VALUES_PER_FACET, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function getNumberOfElementByPage($storeId = null)
    {
        return $this->configInterface->getValue(self::NUMBER_OF_ELEMENT_BY_PAGE, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function getNumberOfJobToRun($storeId = null)
    {
        return $this->configInterface->getValue(self::NUMBER_OF_JOB_TO_RUN, ScopeInterface::SCOPE_STORE, $storeId);
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
        return (int) $this->configInterface->getValue(self::NUMBER_OF_PRODUCT_RESULTS, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function isPopupEnabled($storeId = null)
    {
        return $this->configInterface->getValue(self::IS_POPUP_ENABLED, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function replaceCategories($storeId = null)
    {
        return $this->configInterface->getValue(self::REPLACE_CATEGORIES, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function isAutoCompleteEnabled($storeId = null)
    {
        return $this->configInterface->getValue(self::IS_POPUP_ENABLED, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function isInstantEnabled($storeId = null)
    {
        return $this->configInterface->getValue(self::IS_INSTANT_ENABLED, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function getInstantSelector($storeId = null)
    {
        return $this->configInterface->getValue(self::INSTANT_SELECTOR, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function getExcludedPages($storeId = null)
    {
        $attrs = $this->unserialize($this->configInterface->getValue(self::EXCLUDED_PAGES, ScopeInterface::SCOPE_STORE, $storeId));

        if (is_array($attrs)) {
            return $attrs;
        }

        return [];
    }

    public function getRenderTemplateDirectives($storeId = null)
    {
        return $this->configInterface->getValue(self::RENDER_TEMPLATE_DIRECTIVES, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function isAutocompleteDebugEnabled($storeId = null)
    {
        return $this->configInterface->isSetFlag(self::AUTOCOMPLETE_MENU_DEBUG, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function getSortingIndices($storeId = null)
    {
        /** @var ProductHelper $productHelper */
        $productHelper = $this->objectManager->create('Algolia\AlgoliaSearch\Helper\Entity\ProductHelper');

        $attrs = $this->unserialize($this->configInterface->getValue(self::SORTING_INDICES, ScopeInterface::SCOPE_STORE, $storeId));

        $currencies = $this->currencyManager->getConfigAllowCurrencies();

        foreach ($attrs as &$attr) {
            $indexName = false;
            $sortAttribute = false;

            if ($this->isCustomerGroupsEnabled($storeId) && $attr['attribute'] === 'price') {
                /** @var Magento\Customer\Model\ResourceModel\Group\Collection $groupCollection */
                $groupCollection = $this->objectManager->get('Magento\Customer\Model\ResourceModel\Group\Collection');

                foreach ($groupCollection as $group) {
                    $customerGroupId = (int) $group->getData('customer_group_id');

                    $indexNameSuffix = 'group_'.$customerGroupId;

                    $indexName = $productHelper->getIndexName($storeId).'_'.$attr['attribute'].'_'.$indexNameSuffix.'_'.$attr['sort'];
                    $sortAttribute = $attr['attribute'] . '.' . $currencies[0] . '.' . $indexNameSuffix;
                }
            } elseif ($attr['attribute'] === 'price') {
                $indexName = $productHelper->getIndexName($storeId) . '_' . $attr['attribute'] . '_' . 'default' . '_' . $attr['sort'];
                $sortAttribute = $attr['attribute'] . '.' . $currencies[0] . '.' . 'default';
            } else {
                $indexName = $productHelper->getIndexName($storeId) . '_' . $attr['attribute'] . '_' . $attr['sort'];
                $sortAttribute = $attr['attribute'];
            }

            if ($indexName && $sortAttribute) {
                $attr['name'] = $indexName;

                if (!array_key_exists('label', $attr) && array_key_exists('sortLabel', $attr)) {
                    $attr['label'] = $attr['sortLabel'];
                }

                $attr['ranking'] = [
                    $attr['sort'].'('.$sortAttribute.')',
                    'typo',
                    'geo',
                    'words',
                    'proximity',
                    'attribute',
                    'exact',
                    'custom',
                ];
            }
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

    public function getIndexPrefix($storeId = null)
    {
        return $this->configInterface->getValue(self::INDEX_PREFIX, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function getCategoryAdditionalAttributes($storeId = null)
    {
        $attrs = $this->unserialize($this->configInterface->getValue(self::CATEGORY_ATTRIBUTES, ScopeInterface::SCOPE_STORE, $storeId));

        if (is_array($attrs)) {
            return $attrs;
        }

        return [];
    }

    public function getProductAdditionalAttributes($storeId = null)
    {
        $attributes = $this->unserialize($this->configInterface->getValue(self::PRODUCT_ATTRIBUTES, ScopeInterface::SCOPE_STORE, $storeId));

        $facets = $this->unserialize($this->configInterface->getValue(self::FACETS, ScopeInterface::SCOPE_STORE,$storeId));
        $attributes = $this->addIndexableAttributes($attributes, $facets, '0');

        $sorts = $this->unserialize($this->configInterface->getValue(self::SORTING_INDICES, ScopeInterface::SCOPE_STORE,$storeId));
        $attributes = $this->addIndexableAttributes($attributes, $sorts, '0');

        $customRankings = $this->unserialize($this->configInterface->getValue(self::PRODUCT_CUSTOM_RANKING, ScopeInterface::SCOPE_STORE,$storeId));
        $customRankings = array_filter($customRankings, function ($customRanking) {
            return $customRanking['attribute'] != 'custom_attribute';
        });
        $attributes = $this->addIndexableAttributes($attributes, $customRankings, '0', '0');

        if (is_array($attributes)) {
            return $attributes;
        }

        return [];
    }

    public function getFacets($storeId = null)
    {
        $attrs = $this->unserialize($this->configInterface->getValue(self::FACETS, ScopeInterface::SCOPE_STORE, $storeId));

        foreach ($attrs as &$attr) {
            if ($attr['type'] == 'other') {
                $attr['type'] = $attr['other_type'];
            }
        }

        if (is_array($attrs)) {
            return array_values($attrs);
        }

        return [];
    }

    public function getCategoryCustomRanking($storeId = null)
    {
        $attrs = $this->unserialize($this->configInterface->getValue(self::CATEGORY_CUSTOM_RANKING, ScopeInterface::SCOPE_STORE, $storeId));

        if (is_array($attrs)) {
            return $attrs;
        }

        return [];
    }

    public function getProductCustomRanking($storeId = null)
    {
        $attrs = $this->unserialize($this->configInterface->getValue(self::PRODUCT_CUSTOM_RANKING, ScopeInterface::SCOPE_STORE, $storeId));

        if (is_array($attrs)) {
            return $attrs;
        }

        return [];
    }

    public function getExtraSettings($section, $storeId = null)
    {
        $constant = 'EXTRA_SETTINGS_'.mb_strtoupper($section);

        $value = $this->configInterface->getValue(constant('self::'.$constant), ScopeInterface::SCOPE_STORE, $storeId);

        return trim($value);
    }

    public function getCurrency($storeId = null)
    {
        /** @var Magento\Store\Model\Store $store */
        $store = $this->storeManager->getStore($storeId);
        $currencySymbol = $this->currency->getCurrency($store->getCurrentCurrencyCode())->getSymbol();

        return $currencySymbol;
    }

    public function getPopularQueries($storeId = null)
    {
        if (!$this->isInstantEnabled($storeId) || !$this->showSuggestionsOnNoResultsPage($storeId)) {
            return array();
        }

        if ($storeId === null) {
            $storeId = $this->storeManager->getStore()->getId();
        }

        /** @var SuggestionHelper $suggestionHelper */
        $suggestionHelper = $this->objectManager->create('Algolia\AlgoliaSearch\Helper\Entity\SuggestionHelper');
        $popularQueries = $suggestionHelper->getPopularQueries($storeId);

        return $popularQueries;
    }

    public function getAttributesToRetrieve($group_id)
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
            'in_stock',
            'type_id',
            'value',
        ]);

        $currencies = $this->dirCurrency->getConfigAllowCurrencies();

        foreach ($currencies as $currency) {
            $attributes[] = 'price.' . $currency . '.default';
            $attributes[] = 'price.' . $currency . '.default_formated';
            $attributes[] = 'price.' . $currency . '.default_original_formated';
            $attributes[] = 'price.' . $currency . '.group_' . $group_id;
            $attributes[] = 'price.' . $currency . '.group_' . $group_id . '_formated';
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

    public function isEnabledSynonyms($storeId = null)
    {
        return $this->configInterface->isSetFlag(self::ENABLE_SYNONYMS, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function getSynonyms($storeId = null)
    {
        $synonyms = $this->unserialize($this->configInterface->getValue(self::SYNONYMS, ScopeInterface::SCOPE_STORE, $storeId));

        if (is_array($synonyms)) {
            return $synonyms;
        }

        return [];
    }

    public function getOnewaySynonyms($storeId = null)
    {
        $onewaySynonyms = $this->unserialize($this->configInterface->getValue(self::ONEWAY_SYNONYMS, ScopeInterface::SCOPE_STORE, $storeId));

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

    public function isAnalyticsEnabled($storeId = null)
    {
        return $this->configInterface->isSetFlag(self::ANALYTICS_ENABLE, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function getAnalyticsConfig($storeId = null)
    {
        return [
            'enabled' => $this->isAnalyticsEnabled(),
            'delay' => $this->configInterface->getValue(self::ANALYTICS_DELAY, ScopeInterface::SCOPE_STORE, $storeId),
            'triggerOnUiInteraction' => $this->configInterface->getValue(self::ANALYTICS_TRIGGER_ON_UI_INTERACTION, ScopeInterface::SCOPE_STORE, $storeId),
            'pushInitialSearch' => $this->configInterface->getValue(self::ANALYTICS_PUSH_INITIAL_SEARCH, ScopeInterface::SCOPE_STORE, $storeId),
        ];
    }
  
    private function addIndexableAttributes($attributes, $addedAttributes, $searchable = '1', $retrievable = '1', $indexNoValue = '1')
    {
        foreach ((array) $addedAttributes as $addedAttribute) {
            foreach ((array) $attributes as $attribute) {
                if ($addedAttribute['attribute'] == $attribute['attribute']) {
                    continue 2;
                }
            }

            $attributes[] = array(
                'attribute'         => $addedAttribute['attribute'],
                'searchable'        => $searchable,
                'retrievable'       => $retrievable,
                'index_no_value'    => $indexNoValue,
            );
        }

        return $attributes;
    }

    private function unserialize($value)
    {
        $unserialized = json_decode($value, true);

        if (json_last_error() === JSON_ERROR_NONE) {
            return $unserialized;
        }

        return unserialize($value);
    }
}
