<?php

namespace Algolia\AlgoliaSearch\Helper\Configuration;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class InstantSearchHelper
{
    public const IS_INSTANT_REDIRECT_ENABLED = 'algoliasearch_instant/instant_redirects/enable';
    public const INSTANT_REDIRECT_OPTIONS = 'algoliasearch_instant/instant_redirects/options';

    public function __construct(
        protected ScopeConfigInterface $configInterface,
    ) {}

    public function isInstantRedirectEnabled(?int $storeId = null): bool
    {
        return $this->configInterface->isSetFlag(self::IS_INSTANT_REDIRECT_ENABLED, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function getInstantRedirectOptions(?int $storeId = null): array
    {
        $value = $this->configInterface->getValue(
            self::INSTANT_REDIRECT_OPTIONS,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
        return empty($value) ? [] : explode(',', $value);
    }
}
