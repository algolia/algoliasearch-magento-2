<?php

namespace Algolia\AlgoliaSearch\Test\Unit\Service\Product;

use Algolia\AlgoliaSearch\Service\Product\FacetBuilder;

class FacetBuilderTestable extends FacetBuilder
{
    public function getRawFacets(int $storeId): array
    {
        return parent::getRawFacets($storeId);
    }

    public function getPricingAttributes(int $storeId): array
    {
        return parent::getPricingAttributes($storeId);
    }

    public function decorateAttributeForFaceting(array $facet): string
    {
        return parent::decorateAttributeForFaceting($facet);
    }

}
