<?php

namespace Algolia\AlgoliaSearch\Helper;

class SupportHelper
{
    /** @var ConfigHelper */
    private $configHelper;

    /**
     * @param ConfigHelper $configHelper
     */
    public function __construct(ConfigHelper $configHelper)
    {
        $this->configHelper = $configHelper;
    }

    /** @return string */
    public function getApplicationId()
    {
        return $this->configHelper->getApplicationID();
    }

    /** @return string */
    public function getExtensionVersion()
    {
        return $this->configHelper->getExtensionVersion();
    }
}
