<?php

namespace Algolia\AlgoliaSearch\ViewModel\Adminhtml;

use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Helper\Configuration\AssetHelper;
use Algolia\AlgoliaSearch\Helper\Configuration\NoticeHelper;

class Common implements \Magento\Framework\View\Element\Block\ArgumentInterface
{
    /** @var ConfigHelper */
    private $configHelper; // TO DO : REMOVE IT AND ADD IT TO ASSET HELPER

    /** @var AssetHelper */
    private $assetHelper;

    /** @var NoticeHelper */
    private $noticeHelper;

    public function __construct(
        ConfigHelper $configHelper,
        AssetHelper $assetHelper,
        NoticeHelper $noticeHelper
    ) {
        $this->configHelper = $configHelper;
        $this->assetHelper = $assetHelper;
        $this->noticeHelper = $noticeHelper;
    }

    /** @return bool */
    public function isQueryRulesEnabled()
    {
        return $this->noticeHelper->isQueryRulesEnabled();
    }

    /** @return bool */
    public function isClickAnalyticsEnabled()
    {
        return $this->noticeHelper->isClickAnalyticsEnabled();
    }

    /** @return bool */
    public function isClickAnalyticsTurnedOnInAdmin()
    {
        return $this->configHelper->isClickConversionAnalyticsEnabled();
    }

    public function isEsWarningNeeded()
    {
        return ! $this->noticeHelper->isMysqlUsed();
    }

    public function getLinksAndVideoTemplate($section, $configNotSet = false)
    {
        // Check if all the mandatory credentials have been set
        if (!$this->configHelper->getApplicationID()
            || !$this->configHelper->getAPIKey()
            || !$this->configHelper->getSearchOnlyAPIKey()) {
            $configNotSet = true;
        }

        return $this->assetHelper->getLinksAndVideoTemplate($section, $configNotSet);
    }

    public function getExtensionNotices()
    {
        return $this->noticeHelper->getExtensionNotices();
    }
}
