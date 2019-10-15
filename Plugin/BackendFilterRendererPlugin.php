<?php

namespace Algolia\AlgoliaSearch\Plugin;

use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Magento\Framework\View\LayoutInterface;
use Magento\Store\Model\StoreManagerInterface;

class BackendFilterRendererPlugin
{
    /** @var LayoutInterface */
    protected $layout;

    /** @var StoreManagerInterface */
    protected $storeManager;

    /** @var ConfigHelper */
    protected $configHelper;

    /** @var string */
    protected $defaultBlock = \Algolia\AlgoliaSearch\Block\Navigation\Renderer\DefaultRenderer::class;

    /** @var string */
    protected $categoryBlock = \Algolia\AlgoliaSearch\Block\Navigation\Renderer\CategoryRenderer::class;

    /** @var string */
    protected $priceBlock = \Algolia\AlgoliaSearch\Block\Navigation\Renderer\PriceRenderer::class;

    /** @var string */
    protected $sliderBlock = \Algolia\AlgoliaSearch\Block\Navigation\Renderer\SliderRenderer::class;

    /**
     * @param LayoutInterface $layout
     * @param StoreManagerInterface $storeManager
     * @param ConfigHelper $configHelper
     */
    public function __construct(
        LayoutInterface $layout,
        StoreManagerInterface $storeManager,
        ConfigHelper $configHelper
    ) {
        $this->layout = $layout;
        $this->storeManager = $storeManager;
        $this->configHelper = $configHelper;
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     *
     * @param \Magento\LayeredNavigation\Block\Navigation\FilterRenderer $subject
     * @param \Closure $proceed
     * @param \Magento\Catalog\Model\Layer\Filter\FilterInterface $filter
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     *
     * @return mixed
     */
    public function aroundRender(
        \Magento\LayeredNavigation\Block\Navigation\FilterRenderer $subject,
        \Closure $proceed,
        \Magento\Catalog\Model\Layer\Filter\FilterInterface $filter
    ) {
        if ($this->configHelper->isBackendRenderingEnabled()) {
            if ($filter instanceof \Magento\CatalogSearch\Model\Layer\Filter\Category) {
                return $this->layout
                    ->createBlock($this->categoryBlock)
                    ->render($filter);
            }

            $attributeCode = $filter->getAttributeModel()->getAttributeCode();
            $facets = $this->configHelper->getFacets($this->storeManager->getStore()->getId());

            foreach ($facets as $facet) {
                if ($facet['attribute'] == $attributeCode) {
                    $block = $this->defaultBlock;
                    if ($facet['type'] == 'slider') {
                        $block = $this->sliderBlock;
                    }
                    if ($facet['attribute'] == 'price') {
                        $block = $this->priceBlock;
                    }

                    return $this->layout
                        ->createBlock($block)
                        ->setIsSearchable($facet['searchable'] == '1')
                        ->render($filter);
                }
            }
        }

        return $proceed($filter);
    }
}
