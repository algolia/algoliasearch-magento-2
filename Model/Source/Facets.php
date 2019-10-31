<?php

namespace Algolia\AlgoliaSearch\Model\Source;

use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Helper\Entity\CategoryHelper;
use Algolia\AlgoliaSearch\Helper\Entity\ProductHelper;
use Algolia\AlgoliaSearch\Helper\ProxyHelper;
use Magento\Backend\Block\Template\Context;

class Facets extends AbstractTable
{
    protected $proxyHelper;

    public function __construct(
        Context $context,
        ProductHelper $producthelper,
        CategoryHelper $categoryHelper,
        ConfigHelper $configHelper,
        ProxyHelper $proxyHelper,
        array $data = []
    ) {
        $this->proxyHelper = $proxyHelper;

        parent::__construct($context, $producthelper, $categoryHelper, $configHelper, $data);
    }

    protected function getTableData()
    {
        $productHelper = $this->productHelper;

        $config = [
            'attribute' => [
                'label'  => 'Attribute',
                'values' => function () use ($productHelper) {
                    $options = [];

                    $attributes = $productHelper->getAllAttributes();

                    foreach ($attributes as $key => $label) {
                        $options[$key] = $key ? $key : $label;
                    }

                    return $options;
                },
            ],
            'type' => [
                'label'  => 'Facet type',
                'values' => [
                    'conjunctive' => 'Conjunctive',
                    'disjunctive' => 'Disjunctive',
                    'slider'      => 'Slider',
                    'priceRanges' => 'Price Ranges',
                ],
            ],
            'label' => [
                'label' => 'Label',
            ],
            'searchable' => [
                'label'  => 'Searchable?',
                'values' => ['1' => 'Yes', '2' => 'No'],
            ],
        ];

        if ($this->isQueryRulesEnabled()) {
            $config['create_rule'] =  [
                'label'  => 'Create Query rule?',
                'values' => ['2' => 'No', '1' => 'Yes'],
            ];
        }

        return $config;
    }

    private function isQueryRulesEnabled()
    {
        $info = $this->proxyHelper->getInfo(\Algolia\AlgoliaSearch\Helper\ProxyHelper::INFO_TYPE_QUERY_RULES);

        // In case the call to API proxy fails,
        // be "nice" and return true
        if ($info && array_key_exists('query_rules', $info)) {
            return $info['query_rules'];
        }

        return true;
    }
}
