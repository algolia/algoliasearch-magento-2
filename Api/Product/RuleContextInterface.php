<?php

namespace Algolia\AlgoliaSearch\Api\Product;

/**
 * Defines rule context identifiers for product-related Algolia Query Rules.
 *
 * @api
 */
interface RuleContextInterface
{
    public const FACET_FILTERS_CONTEXT = 'magento_filters';

    public const MERCH_RULE_CATEGORY_PREFIX = 'magento-category-';

    public const MERCH_RULE_QUERY_PREFIX = 'magento-query-';

    public const LANDING_PAGE_PREFIX = 'magento-landingpage-';

}

