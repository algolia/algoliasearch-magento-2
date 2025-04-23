<?php

namespace Algolia\AlgoliaSearch\Helper\Configuration;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class AutocompleteHelper
{
    public const IS_AUTOCOMPLETE_REDIRECT_ENABLED = 'algoliasearch_autocomplete/redirects/enable';
    public const AUTOCOMPLETE_REDIRECT_MODE = 'algoliasearch_autocomplete/redirects/mode';
    public const AUTOCOMPLETE_OPEN_REDIRECT_IN_NEW_WINDOW = 'algoliasearch_autocomplete/redirects/target';

    public function __construct(
        protected ScopeConfigInterface $configInterface,
    ) {}
    public function isAutocompleteRedirectEnabled(?int $storeId = null): bool
    {
        return $this->configInterface->isSetFlag(self::IS_AUTOCOMPLETE_REDIRECT_ENABLED, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function getAutocompleteRedirectMode(?int $storeId = null): int
    {
        return (int) $this->configInterface->getValue(
            self::AUTOCOMPLETE_REDIRECT_MODE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function isAutocompleteRedirectInNewWindowEnabled($storeId = null): bool
    {
        return $this->configInterface->isSetFlag(self::AUTOCOMPLETE_OPEN_REDIRECT_IN_NEW_WINDOW, ScopeInterface::SCOPE_STORE, $storeId);
    }

}
