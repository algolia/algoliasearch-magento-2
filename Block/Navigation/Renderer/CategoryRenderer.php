<?php

namespace Algolia\AlgoliaSearch\Block\Navigation\Renderer;

use Magento\Catalog\Model\Layer\Filter\FilterInterface;
use Magento\Framework\View\Element\Template;
use Magento\LayeredNavigation\Block\Navigation\FilterRendererInterface;

/**
 * @deprecated since 3.18, to be removed in 3.19. (See MAGE-1579.)
 *             Part of the legacy backend-facet-rendering subsystem.
 *             The algoliasearch_instant/instant/backend_rendering_enable config flag
 *             was removed from system.xml, making this class unreachable.
 *             Backend rendering is now provided by the optional Algolia_SearchAdapter module.
 * @see https://github.com/algolia/algoliasearch-adapter-magento-2
 */
class CategoryRenderer extends Template implements FilterRendererInterface
{
    /** @var string */
    protected $_template = 'Algolia_AlgoliaSearch::layer/filter/category.phtml';

    /** @var FilterInterface */
    protected $filter;

    public function isMultipleSelectEnabled()
    {
        return false;
    }

    public function render(FilterInterface $filter)
    {
        $html = '';
        $this->filter = $filter;

        if ($this->canRenderFilter()) {
            $this->assign('filterItems', $filter->getItems());
            $html = $this->_toHtml();
            $this->assign('filterItems', []);
        }

        return $html;
    }

    /**
     * @inheritdoc
     */
    protected function canRenderFilter()
    {
        return true;
    }
}
