<?php

namespace Algolia\AlgoliaSearch\Block\Navigation\Renderer;

use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Magento\Catalog\Model\Layer\Filter\FilterInterface;
use Magento\Framework\View\Element\Template;
use Magento\LayeredNavigation\Block\Navigation\FilterRendererInterface;

class DefaultRenderer extends Template implements FilterRendererInterface
{
    /** @var bool */
    private $isSearchable = true;

    const JS_COMPONENT = 'Algolia_AlgoliaSearch/navigation/attribute-filter';

    /**
     * Path to template file.
     *
     * @var string
     */
    protected $_template = 'Algolia_AlgoliaSearch::layer/filter/js-default.phtml';

    /** @var ConfigHelper */
    private $configHelper;

    /**
     * Constructor.
     *
     * @param Template\Context $context
     * @param CatalogHelper ConfigHelper
     * @param array $data
     */
    public function __construct(Template\Context $context, ConfigHelper $configHelper, array $data = [])
    {
        parent::__construct($context, $data);
        $this->configHelper = $configHelper;
    }

    /**
     * Returns true if checkox have to be enabled.
     *
     * @return bool
     */
    public function isMultipleSelectEnabled()
    {
        return true;
    }

    public function setIsSearchable($value)
    {
        $this->isSearchable = $value;

        return $this;
    }

    public function getIsSearchable()
    {
        return $this->isSearchable;
    }

    /**
     * {@inheritdoc}
     */
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
     * {@inheritdoc}
     */
    public function getJsLayout()
    {
        $filterItems = $this->getFilter()->getItems();
        $maxValuesPerFacet = (int) $this->configHelper->getMaxValuesPerFacet();

        $jsLayoutConfig = [
            'component' => self::JS_COMPONENT,
            'maxSize'  => $maxValuesPerFacet,
            'displayProductCount' => true,
            'hasMoreItems' => (bool) $filterItems > $maxValuesPerFacet,
            'ajaxLoadUrl' => $this->getAjaxLoadUrl(),
            'displaySearch' => $this->getIsSearchable(),
        ];

        foreach ($filterItems as $item) {
            $jsLayoutConfig['items'][] = [
                'label' => $item->getLabel(),
                'count' => $item->getCount(),
                'url' => $item->getUrl(),
                'is_selected' => $item->getData('is_selected'),
            ];
        }

        return json_encode($jsLayoutConfig);
    }

    /**
     * {@inheritdoc}
     */
    protected function canRenderFilter()
    {
        return true;
    }

    /**
     * @return FilterInterface
     */
    public function getFilter()
    {
        return $this->filter;
    }

    /**
     * Get the AJAX load URL (used by the show more and the search features).
     *
     * @return string
     */
    private function getAjaxLoadUrl()
    {
        $qsParams = ['filterName' => $this->getFilter()->getRequestVar()];

        $currentCategory = $this->getFilter()->getLayer()->getCurrentCategory();

        if ($currentCategory && $currentCategory->getId() && $currentCategory->getLevel() > 1) {
            $qsParams['cat'] = $currentCategory->getId();
        }

        $urlParams = ['_current' => true, '_query' => $qsParams];

        return $this->_urlBuilder->getUrl('catalog/navigation_filter/ajax', $urlParams);
    }
}
