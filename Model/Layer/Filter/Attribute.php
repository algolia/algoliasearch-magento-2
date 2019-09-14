<?php

namespace Algolia\AlgoliaSearch\Model\Layer\Filter;

use Algolia\AlgoliaSearch\Helper\ConfigHelper;

class Attribute extends \Magento\CatalogSearch\Model\Layer\Filter\Attribute
{
    /** @var array */
    protected $currentFilterValue = [];

    /** @var \Magento\Framework\Escaper */
    private $escaper;

    /** @var \Magento\Framework\Filter\StripTags */
    private $tagFilter;

    /** @var ConfigHelper */
    private $configHelper;

    /**
     * Constructor.
     *
     * @param \Magento\Catalog\Model\Layer\Filter\ItemFactory $filterItemFactory
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Magento\Catalog\Model\Layer $layer
     * @param \Magento\Catalog\Model\Layer\Filter\Item\DataBuilder $itemDataBuilder
     * @param \Magento\Framework\Filter\StripTags $tagFilter
     * @param \Magento\Framework\Escaper $escaper
     * @param ConfigHelper $configHelper
     * @param array $data
     */
    public function __construct(
        \Magento\Catalog\Model\Layer\Filter\ItemFactory $filterItemFactory,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Catalog\Model\Layer $layer,
        \Magento\Catalog\Model\Layer\Filter\Item\DataBuilder $itemDataBuilder,
        \Magento\Framework\Filter\StripTags $tagFilter,
        \Magento\Framework\Escaper $escaper,
        ConfigHelper $configHelper,
        array $data = []
    ) {
        parent::__construct(
            $filterItemFactory,
            $storeManager,
            $layer,
            $itemDataBuilder,
            $tagFilter,
            $data
        );

        $this->tagFilter = $tagFilter;
        $this->escaper = $escaper;
        $this->configHelper = $configHelper;
    }

    /**
     * {@inheritdoc}
     */
    public function apply(\Magento\Framework\App\RequestInterface $request)
    {
        $storeId = $this->configHelper->getStoreId();
        if (!$this->configHelper->isBackendRenderingEnabled($storeId)) {
            return parent::apply($request);
        }

        $attribute = $this->getAttributeModel();
        $attributeValue = $request->getParam($this->_requestVar);
        if (!is_null($attributeValue)) {
            $attributeValue = explode('~', $request->getParam($this->_requestVar));
        }

        if (empty($attributeValue)) {
            return $this;
        }

        if (!is_array($attributeValue)) {
            $attributeValue = [$attributeValue];
        }

        $this->currentFilterValue = array_values($attributeValue);

        /** @var \Magento\CatalogSearch\Model\ResourceModel\Fulltext\Collection $productCollection */
        $productCollection = $this->getLayer()->getProductCollection();
        $productCollection->addFieldToFilter($attribute->getAttributeCode(), ['in' => $this->currentFilterValue]);
        $layerState = $this->getLayer()->getState();

        foreach ($this->currentFilterValue as $currentFilter) {
            $filter = $this->_createItem(
                $this->escaper->escapeHtml($this->getOptionText($currentFilter)),
                $this->currentFilterValue
            );
            $layerState->addFilter($filter);
        }

        return $this;
    }

    protected function _initItems()
    {
        parent::_initItems();

        foreach ($this->_items as $item) {
            $applyValue = $item->getValue();
            if (($valuePos = array_search($applyValue, $this->currentFilterValue)) !== false) {
                $item->setIsSelected(true);
            }
        }

        return $this;
    }

    protected function _getItemsData()
    {
        return $this->sortItemsData(parent::_getItemsData());
    }

    private function sortItemsData(array $items)
    {
        usort($items, function ($item1, $item2) {
            if (!isset($item1['count']) or !isset($item2['count'])) {
                return 0;
            }

            return $item1['count'] > $item2['count'] ? -1 : 1;
        });

        return $items;
    }
}
