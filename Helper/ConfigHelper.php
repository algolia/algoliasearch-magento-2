<?php

namespace Algolia\AlgoliaSearch\Helper;

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

    const NUMBER_OF_PRODUCT_RESULTS = 'algoliasearch_products/products/number_product_results';
    const PRODUCT_ATTRIBUTES = 'algoliasearch_products/products/product_additional_attributes';
    const PRODUCT_CUSTOM_RANKING = 'algoliasearch_products/products/custom_ranking_product_attributes';
    const RESULTS_LIMIT = 'algoliasearch_products/products/results_limit';
    const SHOW_SUGGESTIONS_NO_RESULTS = 'algoliasearch_products/products/show_suggestions_on_no_result_page';
    const INDEX_OUT_OF_STOCK_OPTIONS = 'algoliasearch_products/products/index_out_of_stock_options';

    const CATEGORY_ATTRIBUTES = 'algoliasearch_categories/categories/category_additional_attributes';
    const INDEX_PRODUCT_COUNT = 'algoliasearch_categories/categories/index_product_count';
    const CATEGORY_CUSTOM_RANKING = 'algoliasearch_categories/categories/custom_ranking_category_attributes';
    const SHOW_CATS_NOT_INCLUDED_IN_NAVIGATION = 'algoliasearch_categories/categories/show_cats_not_included_in_navigation';

    const IS_ACTIVE = 'algoliasearch_queue/queue/active';
    const NUMBER_OF_JOB_TO_RUN = 'algoliasearch_queue/queue/number_of_job_to_run';

    const XML_PATH_IMAGE_WIDTH = 'algoliasearch_images/image/width';
    const XML_PATH_IMAGE_HEIGHT = 'algoliasearch_images/image/height';
    const XML_PATH_IMAGE_TYPE = 'algoliasearch_images/image/type';

    const SYNONYMS = 'algoliasearch_synonyms/synonyms_group/synonyms';
    const ONEWAY_SYNONYMS = 'algoliasearch_synonyms/synonyms_group/oneway_synonyms';
    const SYNONYMS_FILE = 'algoliasearch_synonyms/synonyms_group/synonyms_file';

    const NUMBER_OF_ELEMENT_BY_PAGE = 'algoliasearch_advanced/advanced/number_of_element_by_page';
    const REMOVE_IF_NO_RESULT = 'algoliasearch_advanced/advanced/remove_words_if_no_result';
    const PARTIAL_UPDATES = 'algoliasearch_advanced/advanced/partial_update';
    const CUSTOMER_GROUPS_ENABLE = 'algoliasearch_advanced/advanced/customer_groups_enable';
    const MAKE_SEO_REQUEST = 'algoliasearch_advanced/advanced/make_seo_request';
    const REMOVE_BRANDING = 'algoliasearch_advanced/advanced/remove_branding';
    const AUTOCOMPLETE_SELECTOR = 'algoliasearch_advanced/advanced/autocomplete_selector';
    const INDEX_PRODUCT_ON_CATEGORY_PRODUCTS_UPDATE = 'algoliasearch_advanced/advanced/index_product_on_category_products_update';

    const SHOW_OUT_OF_STOCK = 'cataloginventory/options/show_out_of_stock';

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

    public function __construct(Magento\Framework\App\Config\ScopeConfigInterface $configInterface,
                                Magento\Framework\ObjectManagerInterface $objectManager,
                                StoreManagerInterface $storeManager,
                                Currency $currency,
                                DirCurrency $dirCurrency,
                                DirectoryList $directoryList,
                                Magento\Framework\Module\ResourceInterface $moduleResource,
                                Magento\Framework\App\ProductMetadataInterface $productMetadata,
                                Magento\Framework\Event\ManagerInterface $eventManager)
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
        return $this->configInterface->getValue(self::ENABLE_BACKEND, ScopeInterface::SCOPE_STORE, $storeId);
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

    public function noProcess($storeId = null)
    {
        return $this->configInterface->getValue(self::NO_PROCESS, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function getImageWidth($storeId = null)
    {
        $imageWidth = $this->configInterface->getValue(self::XML_PATH_IMAGE_WIDTH, ScopeInterface::SCOPE_STORE, $storeId);
        if (empty($imageWidth)) {
            return;
        }

        return $imageWidth;
    }

    public function getImageHeight($storeId = null)
    {
        $imageHeight = $this->configInterface->getValue(self::XML_PATH_IMAGE_HEIGHT, ScopeInterface::SCOPE_STORE, $storeId);
        if (empty($imageHeight)) {
            return;
        }

        return $imageHeight;
    }

    public function getImageType($storeId = null)
    {
        return $this->configInterface->getValue(self::XML_PATH_IMAGE_TYPE, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function isCustomerGroupsEnabled($storeId = null)
    {
        return $this->configInterface->getValue(self::CUSTOMER_GROUPS_ENABLE, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function isPartialUpdateEnabled($storeId = null)
    {
        return $this->configInterface->getValue(self::PARTIAL_UPDATES, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function getAutocompleteSections($storeId = null)
    {
        $attrs = unserialize($this->configInterface->getValue(self::AUTOCOMPLETE_SECTIONS, ScopeInterface::SCOPE_STORE, $storeId));

        if (is_array($attrs)) {
            return array_values($attrs);
        }

        return [];
    }

    public function getNumberOfQuerySuggestions($storeId = null)
    {
        return $this->configInterface->getValue(self::NUMBER_QUERY_SUGGESTIONS, ScopeInterface::SCOPE_STORE, $storeId);
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

    public function getResultsLimit($storeId = null)
    {
        return $this->configInterface->getValue(self::RESULTS_LIMIT, ScopeInterface::SCOPE_STORE, $storeId);
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
        $attrs = unserialize($this->configInterface->getValue(self::EXCLUDED_PAGES, ScopeInterface::SCOPE_STORE, $storeId));

        if (is_array($attrs)) {
            return $attrs;
        }

        return [];
    }

    public function getRenderTemplateDirectives($storeId = null)
    {
        return $this->configInterface->getValue(self::RENDER_TEMPLATE_DIRECTIVES, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function getSortingIndices($storeId = null)
    {
        $productHelper = $this->objectManager->create('Algolia\AlgoliaSearch\Helper\Entity\ProductHelper');

        $attrs = unserialize($this->configInterface->getValue(self::SORTING_INDICES, ScopeInterface::SCOPE_STORE, $storeId));

        if ($storeId === null) {
            /** @var \Magento\Customer\Model\Session $customerSession */
            $customerSession = $this->objectManager->create('Magento\Customer\Model\Session');
            $group_id = $customerSession->getCustomerGroupId();

            foreach ($attrs as &$attr) {
                if ($this->isCustomerGroupsEnabled($storeId)) {
                    if (strpos($attr['attribute'], 'price') !== false) {
                        $suffix_index_name = 'group_' . $group_id;

                        $attr['name'] = $productHelper->getIndexName($storeId) . '_' . $attr['attribute'] . '_' . $suffix_index_name . '_' . $attr['sort'];
                    } else {
                        $attr['name'] = $productHelper->getIndexName($storeId) . '_' . $attr['attribute'] . '_' . $attr['sort'];
                    }
                } else {
                    if (strpos($attr['attribute'], 'price') !== false) {
                        $attr['name'] = $productHelper->getIndexName($storeId) . '_' . $attr['attribute'] . '_' . 'default' . '_' . $attr['sort'];
                    } else {
                        $attr['name'] = $productHelper->getIndexName($storeId) . '_' . $attr['attribute'] . '_' . $attr['sort'];
                    }
                }
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
        $attrs = unserialize($this->configInterface->getValue(self::CATEGORY_ATTRIBUTES, ScopeInterface::SCOPE_STORE, $storeId));

        if (is_array($attrs)) {
            return $attrs;
        }

        return [];
    }

    public function getProductAdditionalAttributes($storeId = null)
    {
        $attrs = unserialize($this->configInterface->getValue(self::PRODUCT_ATTRIBUTES, ScopeInterface::SCOPE_STORE, $storeId));

        if (is_array($attrs)) {
            return $attrs;
        }

        return [];
    }

    public function getFacets($storeId = null)
    {
        $attrs = unserialize($this->configInterface->getValue(self::FACETS, ScopeInterface::SCOPE_STORE, $storeId));

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
        $attrs = unserialize($this->configInterface->getValue(self::CATEGORY_CUSTOM_RANKING, ScopeInterface::SCOPE_STORE, $storeId));

        if (is_array($attrs)) {
            return $attrs;
        }

        return [];
    }

    public function getProductCustomRanking($storeId = null)
    {
        $attrs = unserialize($this->configInterface->getValue(self::PRODUCT_CUSTOM_RANKING, ScopeInterface::SCOPE_STORE, $storeId));

        if (is_array($attrs)) {
            return $attrs;
        }

        return [];
    }

    public function getCurrency($storeId = null)
    {
        $currencySymbol = $this->currency->getCurrency($this->storeManager->getStore()->getCurrentCurrencyCode())->getSymbol();

        return $currencySymbol;
    }

    public function getPopularQueries($storeId = null)
    {
        if ($storeId === null) {
            $storeId = $this->storeManager->getStore()->getId();
        }

        $suggestion_helper = $this->objectManager->create('Algolia\AlgoliaSearch\Helper\Entity\SuggestionHelper');
        $popularQueries = $suggestion_helper->getPopularQueries($storeId);

        return $popularQueries;
    }

    public function getAttributesToRetrieve($group_id)
    {
        if (false === $this->isCustomerGroupsEnabled()) {
            return [];
        }

        $attributes = [];
        foreach ($this->getProductAdditionalAttributes() as $attribute) {
            if ($attribute['attribute'] !== 'price') {
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

        return ['attributesToRetrieve' => $attributes];
    }

    public function getSynonyms($storeId = null)
    {
        $synonyms = unserialize($this->configInterface->getValue(self::SYNONYMS, ScopeInterface::SCOPE_STORE, $storeId));

        if (is_array($synonyms)) {
            return $synonyms;
        }

        return [];
    }

    public function getOnewaySynonyms($storeId = null)
    {
        $onewaySynonyms = unserialize($this->configInterface->getValue(self::ONEWAY_SYNONYMS, ScopeInterface::SCOPE_STORE, $storeId));

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
}
