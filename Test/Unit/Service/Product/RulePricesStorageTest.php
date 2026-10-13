<?php

declare(strict_types=1);

namespace Algolia\AlgoliaSearch\Test\Unit\Service\Product;

use Algolia\AlgoliaSearch\Service\Product\RulePricesStorage;
use Algolia\AlgoliaSearch\Test\TestCase;

class RulePricesStorageTest extends TestCase
{
    protected function createObjectToTest(): RulePricesStorage
    {
        return new RulePricesStorage();
    }

    public function testSetAndGetRulePriceStoresAndRetrievesData(): void
    {
        $storage = $this->createObjectToTest();
        $id = 'product_123';
        $prices = [
            ['customer_group_id' => 0, 'rule_price' => 99.99],
            ['customer_group_id' => 1, 'rule_price' => 89.99],
        ];

        $storage->setRulePrice($id, $prices);

        $this->assertSame($prices, $storage->getRulePrice($id));
    }

    public function testGetRulePriceReturnsFalseWhenIdNotSet(): void
    {
        $storage = $this->createObjectToTest();

        $result = $storage->getRulePrice('non_existent_id');

        $this->assertFalse($result);
    }

    public function testHasRulePriceReturnsTrueWhenIdExists(): void
    {
        $storage = $this->createObjectToTest();
        $id = 'product_456';
        $prices = [['customer_group_id' => 0, 'rule_price' => 49.99]];

        $storage->setRulePrice($id, $prices);

        $this->assertTrue($storage->hasRulePrice($id));
    }

    public function testHasRulePriceReturnsFalseWhenIdDoesNotExist(): void
    {
        $storage = $this->createObjectToTest();

        $this->assertFalse($storage->hasRulePrice('non_existent_id'));
    }

    public function testSetRulePriceOverwritesExistingData(): void
    {
        $storage = $this->createObjectToTest();
        $id = 'product_789';
        $initialPrices = [['customer_group_id' => 0, 'rule_price' => 100.00]];
        $updatedPrices = [['customer_group_id' => 1, 'rule_price' => 80.00]];

        $storage->setRulePrice($id, $initialPrices);
        $storage->setRulePrice($id, $updatedPrices);

        $this->assertSame($updatedPrices, $storage->getRulePrice($id));
    }

    public function testSetRulePriceAcceptsEmptyArray(): void
    {
        $storage = $this->createObjectToTest();
        $id = 'product_empty';

        $storage->setRulePrice($id, []);

        $this->assertSame([], $storage->getRulePrice($id));
        $this->assertTrue($storage->hasRulePrice($id));
    }

    public function testMultipleIdsCanBeStoredIndependently(): void
    {
        $storage = $this->createObjectToTest();
        $id1 = 'product_001';
        $id2 = 'product_002';
        $prices1 = [['customer_group_id' => 0, 'rule_price' => 10.00]];
        $prices2 = [['customer_group_id' => 1, 'rule_price' => 20.00]];

        $storage->setRulePrice($id1, $prices1);
        $storage->setRulePrice($id2, $prices2);

        $this->assertSame($prices1, $storage->getRulePrice($id1));
        $this->assertSame($prices2, $storage->getRulePrice($id2));
        $this->assertTrue($storage->hasRulePrice($id1));
        $this->assertTrue($storage->hasRulePrice($id2));
    }

    public function testGetRulePriceReturnsFalseAfterStorageIsCleared(): void
    {
        $storage = $this->createObjectToTest();
        $id = 'product_clear';
        $prices = [['customer_group_id' => 0, 'rule_price' => 50.00]];

        $storage->setRulePrice($id, $prices);
        $storage = $this->createObjectToTest();

        $this->assertFalse($storage->getRulePrice($id));
    }

    public function testHasRulePriceReturnsFalseForEmptyString(): void
    {
        $storage = $this->createObjectToTest();

        $this->assertFalse($storage->hasRulePrice(''));
    }

    public function testSetAndGetRulePriceHandlesComplexArrayStructure(): void
    {
        $storage = $this->createObjectToTest();
        $id = 'product_complex';
        $prices = [
            [
                'customer_group_id' => 0,
                'rule_price' => 99.99,
                'website_id' => 1,
                'rule_date' => '2026-10-13',
            ],
            [
                'customer_group_id' => 2,
                'rule_price' => 79.99,
                'website_id' => 1,
                'rule_date' => '2026-10-13',
            ],
        ];

        $storage->setRulePrice($id, $prices);

        $this->assertSame($prices, $storage->getRulePrice($id));
    }
}
