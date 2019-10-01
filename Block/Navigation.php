<?php

namespace Algolia\AlgoliaSearch\Block;

use Algolia\AlgoliaSearch\Helper\ConfigHelper;

class Navigation extends \Magento\LayeredNavigation\Block\Navigation
{
    /** @var ConfigHelper */
    private $configHelper;

    /**
     * Navigation constructor.
     *
     * @param \Magento\Framework\View\Element\Template\Context $context
     * @param \Magento\Catalog\Model\Layer\Resolver $layerResolver
     * @param \Magento\Catalog\Model\Layer\FilterList $filterList
     * @param \Magento\Catalog\Model\Layer\AvailabilityFlagInterface $visibilityFlag
     * @param ConfigHelper $configHelper
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \Magento\Catalog\Model\Layer\Resolver $layerResolver,
        \Magento\Catalog\Model\Layer\FilterList $filterList,
        \Magento\Catalog\Model\Layer\AvailabilityFlagInterface $visibilityFlag,
        ConfigHelper $configHelper,
        array $data
    ) {
        parent::__construct($context, $layerResolver, $filterList, $visibilityFlag, $data);
        $this->configHelper = $configHelper;

        if ($this->configHelper->isBackendRenderingEnabled()) {
            $this->getLayout()->unsetElement('catalog.compare.sidebar');
            $this->getLayout()->unsetElement('wishlist_sidebar');
        }
    }
}
