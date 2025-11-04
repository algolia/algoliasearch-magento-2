<?php

namespace Algolia\AlgoliaSearch\Api\Product;

/**
 * Defines canonical product record field names for Algolia indexing.
 * These constants represent stable keys that external integrations may rely on.
 *
 * @api
 */
interface ProductRecordFieldsInterface
{
    /**
     * @var string Product visibie is search results
     */
    public const VISIBILITY_SEARCH = 'visibility_search';

    /**
     * @var string Product visibility in catalog
     */
    public const VISIBILITY_CATALOG = 'visibility_catalog';
}
