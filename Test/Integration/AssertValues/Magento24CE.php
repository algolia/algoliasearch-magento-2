<?php

namespace Algolia\AlgoliaSearch\Test\Integration\AssertValues;

abstract class Magento24CE
{
    public $productsOnStockCount = 180;
    public $productsOutOfStockCount = 181;
    public $productsCountWithoutGiftcards = 181;
    public $lastJobDataSize = 13;
    public $expectedCategory = 16;
    public $attributesForFaceting = 5;
    public $automaticalSetOfCategoryAttributesForFaceting = 4;
    public $expectedPages = 6;
    public $expectedExcludePages = 4;
}
