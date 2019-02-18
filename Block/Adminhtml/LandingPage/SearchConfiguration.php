<?php

namespace Algolia\AlgoliaSearch\Block\Adminhtml\LandingPage;

use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Helper\Data;
use Algolia\AlgoliaSearch\Helper\ProxyHelper;
use Algolia\AlgoliaSearch\Model\LandingPage;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\Registry;

class SearchConfiguration extends \Magento\Backend\Block\Template
{
    /** @var string */
    protected $_template = 'landingpage/search-configuration.phtml';

    /** @var Registry */
    protected $registry;

    /** @var ConfigHelper */
    private $configHelper;

    /** @var Data */
    private $coreHelper;

    /** @var ProxyHelper */
    protected $proxyHelper;

    /** @var int */
    protected $planLevel;

    /**
     * @param Context $context
     * @param Registry $registry
     * @param ConfigHelper $configHelper
     * @param Data $coreHelper
     * @param ProxyHelper $proxyHelper
     * @param array $data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        ConfigHelper $configHelper,
        Data $coreHelper,
        ProxyHelper $proxyHelper,
        array $data = []
    ) {
        $this->registry = $registry;
        $this->configHelper = $configHelper;
        $this->coreHelper = $coreHelper;
        $this->proxyHelper = $proxyHelper;

        parent::__construct($context, $data);
    }

    /** @return LandingPage | null */
    public function getLandingPage()
    {
        return $this->registry->registry('algoliasearch_landing_page');
    }

    /** @return ConfigHelper */
    public function getConfigHelper()
    {
        return $this->configHelper;
    }

    /** @return Data */
    public function getCoreHelper()
    {
        return $this->coreHelper;
    }

    /** @return int */
    public function getPlanLevel()
    {
        if ($this->planLevel == null) {
            $planLevel = 1;
            $planLevelInfo = $this->proxyHelper->getInfo(ProxyHelper::INFO_TYPE_PLAN_LEVEL);

            if (isset($planLevelInfo['plan_level'])) {
                $planLevel = (int) $planLevelInfo['plan_level'];
            }
            $this->planLevel = $planLevel;
        }

        return $this->planLevel;
    }
}
