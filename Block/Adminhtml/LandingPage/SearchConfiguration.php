<?php

namespace Algolia\AlgoliaSearch\Block\Adminhtml\LandingPage;

use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Helper\Data;
use Algolia\AlgoliaSearch\Model\LandingPage;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\Session\SessionManagerInterface;

class SearchConfiguration extends \Magento\Backend\Block\Template
{
    /** @var string */
    protected $_template = 'landingpage/search-configuration.phtml';

    /** @var SessionManagerInterface */
    protected $backendSession;

    /** @var ConfigHelper */
    private $configHelper;

    /** @var Data */
    private $coreHelper;

    /**
     * @param Context $context
     * @param SessionManagerInterface $backendSession
     * @param ConfigHelper $configHelper
     * @param Data $coreHelper
     * @param array $data
     */
    public function __construct(
        Context $context,
        SessionManagerInterface $backendSession,
        ConfigHelper $configHelper,
        Data $coreHelper,
        array $data = []
    ) {
        $this->backendSession = $backendSession;
        $this->configHelper = $configHelper;
        $this->coreHelper = $coreHelper;

        parent::__construct($context, $data);
    }

    /** @return LandingPage | null */
    public function getLandingPage()
    {
        return $this->backendSession->getData('algoliasearch_landing_page');
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
}
