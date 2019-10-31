<?php

namespace Algolia\AlgoliaSearch\Model;

use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Helper\Data;
use Algolia\AlgoliaSearch\Helper\ProxyHelper;
use Algolia\AlgoliaSearch\Model\ResourceModel\LandingPage\Collection as LandingPageCollection;
use Algolia\AlgoliaSearch\Model\ResourceModel\Query\Collection as QueryCollection;
use Algolia\AlgoliaSearch\Setup\UpgradeSchema;

class ConfigurationTracker
{
    /** @var Data */
    private $proxyHelper;

    /** @var ConfigHelper */
    private $configHelper;

    /** @var QueryCollection */
    private $queryCollection;

    /** @var LandingPageCollection */
    private $landingPageCollection;

    /** @var UpgradeSchema */
    private $upgradeSchema;

    /**
     * @param ProxyHelper $proxyHelper
     * @param ConfigHelper $configHelper
     * @param QueryCollection $queryCollection
     * @param LandingPageCollection $landingPageCollection
     */
    public function __construct(
        ProxyHelper $proxyHelper,
        ConfigHelper $configHelper,
        QueryCollection $queryCollection,
        LandingPageCollection $landingPageCollection,
        UpgradeSchema $upgradeSchema
    ) {
        $this->proxyHelper = $proxyHelper;
        $this->configHelper = $configHelper;
        $this->queryCollection = $queryCollection;
        $this->landingPageCollection = $landingPageCollection;
        $this->upgradeSchema = $upgradeSchema;
    }

    /**
     * @param int $storeId
     */
    public function trackConfiguration($storeId)
    {
        $this->proxyHelper->trackEvent($this->configHelper->getApplicationID($storeId), 'Configuration saved', [
            'source' => 'magento2.saveconfig',
            'indexingEnabled' => $this->configHelper->isEnabledBackend($storeId),
            'searchEnabled' => $this->configHelper->isEnabledFrontEnd($storeId),
            'autocompleteEnabled' => $this->configHelper->isAutoCompleteEnabled($storeId),
            'instantsearchEnabled' => $this->configHelper->isInstantEnabled($storeId),
            'sortingChanged' => $this->isSortingChanged($storeId),
            'rankingChanged' => $this->isCustomRankingChanged($storeId),
            'replaceImageByVariantUsed' => $this->configHelper->useAdaptiveImage($storeId),
            'indexingQueueEnabled' => $this->configHelper->isQueueActive($storeId),
            'synonymsManagementEnabled' => $this->configHelper->isEnabledSynonyms($storeId),
            'clickAnalyticsEnabled' => $this->configHelper->isClickConversionAnalyticsEnabled($storeId),
            'googleAnalyticsEnabled' => $this->configHelper->isAnalyticsEnabled($storeId),
            'customerGroupsEnabled' => $this->configHelper->isCustomerGroupsEnabled($storeId),
            'merchangisingQRsCreated' => $this->getCountMerchandisingQueries() > 0,
            'noOfMerchandisingQRs' => (int) $this->getCountMerchandisingQueries(),
            'landingPageCreated' => $this->getCountLandingPages() > 0,
            'noOfLandingPages' => (int) $this->getCountLandingPages(),
            'storeId' => $storeId,
        ]);
    }

    /**
     * @param null $storeId
     *
     * @return bool
     */
    private function isSortingChanged($storeId = null)
    {
        return $this->configHelper->getRawSortingValue($storeId)
            !== $this->getDefaultConfigurationFromPath(ConfigHelper::SORTING_INDICES);
    }

    /**
     * @param null $storeId
     *
     * @return bool
     */
    private function isCustomRankingChanged($storeId = null)
    {
        return $this->configHelper->getRawProductCustomRanking($storeId)
            !== $this->getDefaultConfigurationFromPath(ConfigHelper::PRODUCT_CUSTOM_RANKING);
    }

    /**
     * @return int
     */
    private function getCountMerchandisingQueries()
    {
        return $this->queryCollection->getSize();
    }

    /**
     * @return int
     */
    private function getCountLandingPages()
    {
        return $this->landingPageCollection->getSize();
    }

    /**
     * @param string $path
     *
     * @return mixed|null
     */
    private function getDefaultConfigurationFromPath($path)
    {
        $config = $this->upgradeSchema->getDefaultConfigData();
        if (isset($config[$path]) && $config[$path]) {
            return $config[$path];
        }

        return null;
    }
}
