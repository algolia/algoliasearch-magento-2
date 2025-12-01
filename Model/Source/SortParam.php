<?php

namespace Algolia\AlgoliaSearch\Model\Source;

use Algolia\AlgoliaSearch\Service\IndexNameFetcher;
use Magento\Framework\Data\OptionSourceInterface;

class SortParam implements OptionSourceInterface
{
    public const SORT_PARAM_ALGOLIA = 'sortBy';
    public const SORT_PARAM_MAGENTO = 'product_list_order';

    public function __construct(
        protected IndexNameFetcher $indexNameFetcher
    ){}

    public function toOptionArray()
    {
        return [
            [
                'value' => self::SORT_PARAM_ALGOLIA,
                'label' => __('sortBy (Algolia default)'),
            ],
            [
                'value' => self::SORT_PARAM_MAGENTO,
                'label' => __('product_list_order (Magento default)'),
            ],
        ];
    }
}
