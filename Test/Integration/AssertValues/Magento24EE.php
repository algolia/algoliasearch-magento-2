<?php

namespace Algolia\AlgoliaSearch\Test\Integration\AssertValues;

abstract class Magento24EE
{
    public $productsOnStockCount = 180;
    public $productsOutOfStockCount = 183;
    public $productsCountWithoutGiftcards = 181;
    public $lastJobDataSize = 13;
    public $expectedCategory = 17;
    public $attributesForFaceting = 5;
    public $automaticalSetOfCategoryAttributesForFaceting = 4;
    public $expectedPages = 9;
    public $expectedExcludePages = 7;
}
