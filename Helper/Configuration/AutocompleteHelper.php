<?php

namespace Algolia\AlgoliaSearch\Helper\Configuration;

use Algolia\AlgoliaSearch\Service\Serializer;
use Algolia\AlgoliaSearch\Model\Source\Suggestions;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class AutocompleteHelper
{
    public const IS_ENABLED = 'algoliasearch_autocomplete/autocomplete/is_popup_enabled';
    public const DOM_SELECTOR = 'algoliasearch_autocomplete/autocomplete/autocomplete_selector';
    public const ADDITIONAL_SECTIONS = 'algoliasearch_autocomplete/autocomplete/sections';
    public const NB_OF_PRODUCTS_SUGGESTIONS = 'algoliasearch_autocomplete/autocomplete/nb_of_products_suggestions';
    public const NB_OF_CATEGORIES_SUGGESTIONS = 'algoliasearch_autocomplete/autocomplete/nb_of_categories_suggestions';

    public const SUGGESTIONS_MODE = 'algoliasearch_autocomplete/autocomplete/suggestions_mode';
    public const NB_OF_QUERIES_SUGGESTIONS = 'algoliasearch_autocomplete/autocomplete/nb_of_queries_suggestions';
    public const MIN_QUERY_POPULARITY = 'algoliasearch_autocomplete/autocomplete/min_popularity';
    public const MIN_QUERY_NUMBER_OF_RESULTS = 'algoliasearch_autocomplete/autocomplete/min_number_of_results';
    public const SUGGESTIONS_INDEX_NAME = 'algoliasearch_autocomplete/autocomplete/suggestions_index_name';
    public const NB_OF_ALGOLIA_SUGGESTIONS = 'algoliasearch_autocomplete/autocomplete/nb_of_algolia_suggestions';

    public const EXCLUDED_PAGES = 'algoliasearch_autocomplete/autocomplete/excluded_pages';
    public const RENDER_TEMPLATE_DIRECTIVES = 'algoliasearch_autocomplete/autocomplete/render_template_directives';
    public const IS_DEBUG_ENABLED = 'algoliasearch_autocomplete/autocomplete/debug';
    public const IS_KEYBOARD_NAV_ENABLED = 'algoliasearch_autocomplete/autocomplete/navigator';

    public const DEBOUNCE_MILLISEC = 'algoliasearch_autocomplete/autocomplete/debounce_millisec';
    public const MINIMUM_CHAR_LENGTH = 'algoliasearch_autocomplete/autocomplete/minimum_char_length';

    public const IS_REDIRECT_ENABLED = 'algoliasearch_autocomplete/redirects/enable';
    public const REDIRECT_MODE = 'algoliasearch_autocomplete/redirects/mode';
    public const OPEN_REDIRECT_IN_NEW_WINDOW = 'algoliasearch_autocomplete/redirects/target';

    public function __construct(
        protected ScopeConfigInterface $configInterface,
        protected Serializer           $serializer
    ) {}

    public function isEnabled(?int $storeId = null): bool
    {
        return $this->configInterface->isSetFlag(self::IS_ENABLED, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function getDomSelector(?int $storeId = null): string
    {
        return $this->configInterface->getValue(self::DOM_SELECTOR, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function getAdditionalSections(?int $storeId = null): array
    {
        $attrs = $this->serializer->unserialize(
            $this->configInterface->getValue(
                self::ADDITIONAL_SECTIONS,
                ScopeInterface::SCOPE_STORE,
                $storeId
            )
        );

        if (is_array($attrs)) {
            return array_values($attrs);
        }

        return [];
    }

    public function getNumberOfProductsSuggestions(?int $storeId = null): int
    {
        return (int) $this->configInterface->getValue(
            self::NB_OF_PRODUCTS_SUGGESTIONS,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function getNumberOfCategoriesSuggestions(?int $storeId = null): int
    {
        return (int) $this->configInterface->getValue(
            self::NB_OF_CATEGORIES_SUGGESTIONS,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function areSuggestionsEnabled(?int $storeId = null): bool
    {
        return $this->getSuggestionsMode($storeId) > 0;
    }

    public function showMagentoSuggestions(?int $storeId = null): bool
    {
        return $this->getSuggestionsMode($storeId) === Suggestions::SUGGESTIONS_MAGENTO
            && $this->getNumberOfQueriesSuggestions($storeId) > 0;
    }

    public function showAlgoliaSuggestions(?int $storeId = null): bool
    {
        return $this->getSuggestionsMode($storeId) === Suggestions::SUGGESTIONS_ALGOLIA
            && $this->getSuggestionsIndexName($storeId) !== ''
            && $this->getNumberOfAlgoliaSuggestions($storeId) > 0;
    }

    public function getSuggestionsMode(?int $storeId = null): int
    {
        return (int) $this->configInterface->getValue(
            self::SUGGESTIONS_MODE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function getNumberOfQueriesSuggestions(?int $storeId = null): int
    {
        return (int) $this->configInterface->getValue(
            self::NB_OF_QUERIES_SUGGESTIONS,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /** Throttles query suggestions that are indexed from Magento to Algolia */
    public function getMinQueryPopularity(?int $storeId = null): int
    {
        return (int) $this->configInterface->getValue(
            self::MIN_QUERY_POPULARITY,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /** Throttles query suggestions that are indexed from Magento to Algolia */
    public function getMinQueryNumberOfResults(?int $storeId = null): int
    {
        return (int) $this->configInterface->getValue(
            self::MIN_QUERY_NUMBER_OF_RESULTS,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function getSuggestionsIndexName(?int $storeId = null): string
    {
        return $this->configInterface->getValue(
            self::SUGGESTIONS_INDEX_NAME,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function getNumberOfAlgoliaSuggestions(?int $storeId = null): int
    {
        return (int) $this->configInterface->getValue(
            self::NB_OF_ALGOLIA_SUGGESTIONS,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Retrieve CMS pages to be excluded from the Autocomplete search
     * Also impacts what pages are indexed
     */
    public function getExcludedPages(?int $storeId = null): array
    {
        $attrs = $this->serializer->unserialize(
            $this->configInterface->getValue(
                self::EXCLUDED_PAGES,
                ScopeInterface::SCOPE_STORE,
                $storeId
            )
        );

        if (is_array($attrs)) {
            return $attrs;
        }

        return [];
    }

    /**
     * This setting impacts what content is indexed for CMS page search
     */
    public function shouldRenderTemplateDirectives(?int $storeId = null): bool
    {
        return $this->configInterface->isSetFlag(
            self::RENDER_TEMPLATE_DIRECTIVES,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function isDebugEnabled(?int $storeId = null): bool
    {
        return $this->configInterface->isSetFlag(self::IS_DEBUG_ENABLED, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function isKeyboardNavigationEnabled(?int $storeId = null): bool
    {
        return $this->configInterface->isSetFlag(self::IS_KEYBOARD_NAV_ENABLED, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function getDebounceMilliseconds($storeId = null): int
    {
        return (int) $this->configInterface->getValue(self::DEBOUNCE_MILLISEC, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function getMinimumCharacterLength(?int $storeId = null): int
    {
        return (int) $this->configInterface->getValue(
            self::MINIMUM_CHAR_LENGTH,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function isRedirectEnabled(?int $storeId = null): bool
    {
        return $this->configInterface->isSetFlag(self::IS_REDIRECT_ENABLED, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function getRedirectMode(?int $storeId = null): int
    {
        return (int) $this->configInterface->getValue(
            self::REDIRECT_MODE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function isRedirectInNewWindowEnabled(?int $storeId = null): bool
    {
        return $this->configInterface->isSetFlag(
            self::OPEN_REDIRECT_IN_NEW_WINDOW,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }
}
