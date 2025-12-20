<?php

namespace Algolia\AlgoliaSearch\Api\Product;

/**
 * Defines rule context identifiers for product-related Algolia Query Rules.
 *
 * @api
 */
interface RuleContextInterface
{
    /**
     * Context for product facet filter query rules
     */
    public const FACET_FILTERS_CONTEXT = 'magento_filters';
}

