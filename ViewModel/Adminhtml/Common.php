<?php

namespace Algolia\AlgoliaSearch\ViewModel\Adminhtml;

use Algolia\AlgoliaSearch\Helper\ConfigHelper;

class Common implements \Magento\Framework\View\Element\Block\ArgumentInterface
{
    /** @var ConfigHelper */
    private $configHelper;

    public function __construct(
        ConfigHelper $configHelper
    ) {
        $this->configHelper = $configHelper;
    }

    /** @return string */
    public function getApplicationId()
    {
        return $this->configHelper->getApplicationID();
    }
}
