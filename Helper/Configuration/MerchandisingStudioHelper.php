<?php

namespace Algolia\AlgoliaSearch\Helper\Configuration;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class MerchandisingStudioHelper
{
    public const string ENABLE_VISUAL_MERCHANDISING =
        'algoliasearch_merchandising_studio/visual_merchandising/enable_visual_merchandising';

    public const string CATEGORY_PAGE_ID_ATTRIBUTE_NAME =
        'algoliasearch_merchandising_studio/visual_merchandising/category_page_id_attribute_name';

    public const string ENABLE_SMART_GROUPS =
        'algoliasearch_merchandising_studio/smart_groups/enable_visual_merchandising';

    public function __construct(
        protected ScopeConfigInterface $configInterface
    ){}

    public function isVisualMerchEnabled($storeId = null): bool
    {
        return $this->configInterface->isSetFlag(self::ENABLE_VISUAL_MERCHANDISING, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function getCategoryPageIdAttributeName($storeId = null): string
    {
        return (string) $this->configInterface->getValue(self::CATEGORY_PAGE_ID_ATTRIBUTE_NAME, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function isSmartGroupsEnabled($storeId = null): bool
    {
        return $this->configInterface->isSetFlag(self::ENABLE_SMART_GROUPS, ScopeInterface::SCOPE_STORE, $storeId);
    }
}
