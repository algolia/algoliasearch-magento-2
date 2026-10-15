<?php

declare(strict_types=1);

namespace Algolia\AlgoliaSearch\Test\Unit\Service\Product;

use Algolia\AlgoliaSearch\Api\Data\PricingContextInterface;
use Algolia\AlgoliaSearch\Model\ResourceModel\Rule;
use Algolia\AlgoliaSearch\Model\ResourceModel\RuleFactory;
use Algolia\AlgoliaSearch\Service\Product\CatalogRulesManager;
use Algolia\AlgoliaSearch\Test\TestCase;
use Magento\Catalog\Model\Product;
use Magento\Customer\Api\GroupManagementInterface;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Store\Model\Store;

class CatalogRulesManagerTest extends TestCase
{
    protected function createObjectToTest(
        ?RuleFactory $resourceRuleFactory = null,
        ?GroupManagementInterface $groupManagement = null,
        ?TimezoneInterface $localeDate = null
    ): CatalogRulesManager {
        return new CatalogRulesManager(
            $resourceRuleFactory ?? $this->createStub(RuleFactory::class),
            $groupManagement ?? $this->createStub(GroupManagementInterface::class),
            $localeDate ?? $this->createStub(TimezoneInterface::class)
        );
    }

    public function testGetProductRulesFetchesAndReturnsRules(): void
    {
        $productId = 100;
        $websiteId = 1;
        $timestamp = 1697241600;
        $dbRules = [
            ['product_id' => 100, 'customer_group_id' => 0, 'rule_price' => '99.99'],
            ['product_id' => 100, 'customer_group_id' => 1, 'rule_price' => '89.99'],
        ];
        $expectedRules = [
            0 => 99.99,
            1 => 89.99,
        ];

        $store = $this->createStub(Store::class);
        $store->method('getWebsiteId')->willReturn($websiteId);

        $product = $this->createStub(Product::class);
        $product->method('getId')->willReturn($productId);

        $pricingContext = $this->createStub(PricingContextInterface::class);
        $pricingContext->method('getStore')->willReturn($store);
        $pricingContext->method('getProduct')->willReturn($product);
        $pricingContext->method('getSubProducts')->willReturn([]);

        $localeDate = $this->createStub(TimezoneInterface::class);
        $localeDate->method('scopeTimeStamp')->willReturn($timestamp);

        $rule = $this->createStub(Rule::class);
        $rule->method('getAllRulePricesFromProduct')->willReturn($dbRules);

        $resourceRuleFactory = $this->createStub(RuleFactory::class);
        $resourceRuleFactory->method('create')->willReturn($rule);

        $manager = $this->createObjectToTest(
            resourceRuleFactory: $resourceRuleFactory,
            localeDate: $localeDate
        );

        $result = $manager->getProductRules($pricingContext);

        $this->assertSame($expectedRules, $result);
    }

    public function testGetProductRulesCachesResultsAndDoesNotFetchTwice(): void
    {
        $productId = 100;
        $websiteId = 1;
        $timestamp = 1697241600;
        $dbRules = [
            ['product_id' => 100, 'customer_group_id' => 0, 'rule_price' => '99.99'],
        ];

        $store = $this->createStub(Store::class);
        $store->method('getWebsiteId')->willReturn($websiteId);

        $product = $this->createStub(Product::class);
        $product->method('getId')->willReturn($productId);

        $pricingContext = $this->createStub(PricingContextInterface::class);
        $pricingContext->method('getStore')->willReturn($store);
        $pricingContext->method('getProduct')->willReturn($product);
        $pricingContext->method('getSubProducts')->willReturn([]);

        $localeDate = $this->createStub(TimezoneInterface::class);
        $localeDate->method('scopeTimeStamp')->willReturn($timestamp);

        $rule = $this->createMock(Rule::class);
        $rule->expects($this->once())
            ->method('getAllRulePricesFromProduct')
            ->willReturn($dbRules);

        $resourceRuleFactory = $this->createMock(RuleFactory::class);
        $resourceRuleFactory->expects($this->once())
            ->method('create')
            ->willReturn($rule);

        $manager = $this->createObjectToTest(
            resourceRuleFactory: $resourceRuleFactory,
            localeDate: $localeDate
        );

        $result1 = $manager->getProductRules($pricingContext);
        $result2 = $manager->getProductRules($pricingContext);

        $this->assertSame([0 => 99.99], $result1);
        $this->assertSame([0 => 99.99], $result2);
    }

    public function testGetProductRulesHandlesSubProducts(): void
    {
        $mainProductId = 100;
        $subProduct1Id = 101;
        $subProduct2Id = 102;
        $websiteId = 1;
        $timestamp = 1697241600;

        $store = $this->createStub(Store::class);
        $store->method('getWebsiteId')->willReturn($websiteId);

        $mainProduct = $this->createStub(Product::class);
        $mainProduct->method('getId')->willReturn($mainProductId);

        $subProduct1 = $this->createStub(Product::class);
        $subProduct1->method('getId')->willReturn($subProduct1Id);

        $subProduct2 = $this->createStub(Product::class);
        $subProduct2->method('getId')->willReturn($subProduct2Id);

        $pricingContext = $this->createStub(PricingContextInterface::class);
        $pricingContext->method('getStore')->willReturn($store);
        $pricingContext->method('getProduct')->willReturn($mainProduct);
        $pricingContext->method('getSubProducts')->willReturn([$subProduct1, $subProduct2]);

        $localeDate = $this->createStub(TimezoneInterface::class);
        $localeDate->method('scopeTimeStamp')->willReturn($timestamp);

        $rule = $this->createMock(Rule::class);
        $rule->expects($this->once())
            ->method('getAllRulePricesFromProduct')
            ->with(
                $this->isInstanceOf(\DateTime::class),
                $websiteId,
                [$mainProductId, $subProduct1Id, $subProduct2Id]
            )
            ->willReturn([]);

        $resourceRuleFactory = $this->createMock(RuleFactory::class);
        $resourceRuleFactory->expects($this->once())
            ->method('create')
            ->willReturn($rule);

        $manager = $this->createObjectToTest(
            resourceRuleFactory: $resourceRuleFactory,
            localeDate: $localeDate
        );

        $result = $manager->getProductRules($pricingContext);

        $this->assertSame([], $result);
    }

    public function testGetProductRulesReturnsSubProductRulesWhenSubProductProvided(): void
    {
        $mainProductId = 100;
        $subProductId = 101;
        $websiteId = 1;
        $timestamp = 1697241600;
        $dbRules = [
            ['product_id' => 100, 'customer_group_id' => 0, 'rule_price' => '99.99'],
            ['product_id' => 101, 'customer_group_id' => 0, 'rule_price' => '49.99'],
        ];

        $store = $this->createStub(Store::class);
        $store->method('getWebsiteId')->willReturn($websiteId);

        $mainProduct = $this->createStub(Product::class);
        $mainProduct->method('getId')->willReturn($mainProductId);

        $subProduct = $this->createStub(Product::class);
        $subProduct->method('getId')->willReturn($subProductId);

        $pricingContext = $this->createStub(PricingContextInterface::class);
        $pricingContext->method('getStore')->willReturn($store);
        $pricingContext->method('getProduct')->willReturn($mainProduct);
        $pricingContext->method('getSubProducts')->willReturn([$subProduct]);

        $localeDate = $this->createStub(TimezoneInterface::class);
        $localeDate->method('scopeTimeStamp')->willReturn($timestamp);

        $rule = $this->createStub(Rule::class);
        $rule->method('getAllRulePricesFromProduct')->willReturn($dbRules);

        $resourceRuleFactory = $this->createStub(RuleFactory::class);
        $resourceRuleFactory->method('create')->willReturn($rule);

        $manager = $this->createObjectToTest(
            resourceRuleFactory: $resourceRuleFactory,
            localeDate: $localeDate
        );

        $result = $manager->getProductRules($pricingContext, $subProduct);

        $this->assertSame([0 => 49.99], $result);
    }

    public function testGetProductRulesOnlyFetchesMissingRules(): void
    {
        $product1Id = 100;
        $product2Id = 101;
        $websiteId = 1;
        $timestamp = 1697241600;
        $dbRules1 = [
            ['product_id' => 100, 'customer_group_id' => 0, 'rule_price' => '99.99'],
        ];
        $dbRules2 = [
            ['product_id' => 101, 'customer_group_id' => 0, 'rule_price' => '49.99'],
        ];

        $store = $this->createStub(Store::class);
        $store->method('getWebsiteId')->willReturn($websiteId);

        $product1 = $this->createStub(Product::class);
        $product1->method('getId')->willReturn($product1Id);

        $product2 = $this->createStub(Product::class);
        $product2->method('getId')->willReturn($product2Id);

        $pricingContext1 = $this->createStub(PricingContextInterface::class);
        $pricingContext1->method('getStore')->willReturn($store);
        $pricingContext1->method('getProduct')->willReturn($product1);
        $pricingContext1->method('getSubProducts')->willReturn([]);

        $pricingContext2 = $this->createStub(PricingContextInterface::class);
        $pricingContext2->method('getStore')->willReturn($store);
        $pricingContext2->method('getProduct')->willReturn($product1);
        $pricingContext2->method('getSubProducts')->willReturn([$product2]);

        $localeDate = $this->createStub(TimezoneInterface::class);
        $localeDate->method('scopeTimeStamp')->willReturn($timestamp);

        $rule = $this->createMock(Rule::class);
        $rule->expects($this->exactly(2))
            ->method('getAllRulePricesFromProduct')
            ->willReturnOnConsecutiveCalls($dbRules1, $dbRules2);

        $resourceRuleFactory = $this->createMock(RuleFactory::class);
        $resourceRuleFactory->expects($this->exactly(2))
            ->method('create')
            ->willReturn($rule);

        $manager = $this->createObjectToTest(
            resourceRuleFactory: $resourceRuleFactory,
            localeDate: $localeDate
        );

        $manager->getProductRules($pricingContext1);
        $result = $manager->getProductRules($pricingContext2);

        $this->assertSame([0 => 99.99], $result);
    }

    public function testGetProductRulesConvertsRulePricesToFloats(): void
    {
        $productId = 300;
        $websiteId = 1;
        $timestamp = 1697241600;
        $dbRules = [
            ['product_id' => 300, 'customer_group_id' => 0, 'rule_price' => '99.99'],
            ['product_id' => 300, 'customer_group_id' => 1, 'rule_price' => '89'],
            ['product_id' => 300, 'customer_group_id' => 2, 'rule_price' => '79.999'],
        ];

        $store = $this->createStub(Store::class);
        $store->method('getWebsiteId')->willReturn($websiteId);

        $product = $this->createStub(Product::class);
        $product->method('getId')->willReturn($productId);

        $pricingContext = $this->createStub(PricingContextInterface::class);
        $pricingContext->method('getStore')->willReturn($store);
        $pricingContext->method('getProduct')->willReturn($product);
        $pricingContext->method('getSubProducts')->willReturn([]);

        $localeDate = $this->createStub(TimezoneInterface::class);
        $localeDate->method('scopeTimeStamp')->willReturn($timestamp);

        $rule = $this->createStub(Rule::class);
        $rule->method('getAllRulePricesFromProduct')->willReturn($dbRules);

        $resourceRuleFactory = $this->createStub(RuleFactory::class);
        $resourceRuleFactory->method('create')->willReturn($rule);

        $manager = $this->createObjectToTest(
            resourceRuleFactory: $resourceRuleFactory,
            localeDate: $localeDate
        );

        $result = $manager->getProductRules($pricingContext);

        $this->assertSame(99.99, $result[0]);
        $this->assertSame(89.0, $result[1]);
        $this->assertSame(79.999, $result[2]);
    }

    public function testGetProductRulesReturnsEmptyArrayForProductsWithoutRules(): void
    {
        $product1Id = 100;
        $product2Id = 200;
        $websiteId = 1;
        $timestamp = 1697241600;
        $dbRules = [
            ['product_id' => 100, 'customer_group_id' => 0, 'rule_price' => '99.99'],
        ];

        $store = $this->createStub(Store::class);
        $store->method('getWebsiteId')->willReturn($websiteId);

        $product1 = $this->createStub(Product::class);
        $product1->method('getId')->willReturn($product1Id);

        $product2 = $this->createStub(Product::class);
        $product2->method('getId')->willReturn($product2Id);

        $pricingContext1 = $this->createStub(PricingContextInterface::class);
        $pricingContext1->method('getStore')->willReturn($store);
        $pricingContext1->method('getProduct')->willReturn($product1);
        $pricingContext1->method('getSubProducts')->willReturn([$product2]);

        $pricingContext2 = $this->createStub(PricingContextInterface::class);
        $pricingContext2->method('getStore')->willReturn($store);
        $pricingContext2->method('getProduct')->willReturn($product2);
        $pricingContext2->method('getSubProducts')->willReturn([]);

        $localeDate = $this->createStub(TimezoneInterface::class);
        $localeDate->method('scopeTimeStamp')->willReturn($timestamp);

        $rule = $this->createStub(Rule::class);
        $rule->method('getAllRulePricesFromProduct')->willReturn($dbRules);

        $resourceRuleFactory = $this->createStub(RuleFactory::class);
        $resourceRuleFactory->method('create')->willReturn($rule);

        $manager = $this->createObjectToTest(
            resourceRuleFactory: $resourceRuleFactory,
            localeDate: $localeDate
        );

        $manager->getProductRules($pricingContext1);
        $result = $manager->getProductRules($pricingContext2);

        $this->assertSame([], $result);
    }

    public function testGetProductRulesReturnsEmptyArrayWhenDatabaseReturnsNoRules(): void
    {
        $productId = 999;
        $websiteId = 1;
        $timestamp = 1697241600;

        $store = $this->createStub(Store::class);
        $store->method('getWebsiteId')->willReturn($websiteId);

        $product = $this->createStub(Product::class);
        $product->method('getId')->willReturn($productId);

        $pricingContext = $this->createStub(PricingContextInterface::class);
        $pricingContext->method('getStore')->willReturn($store);
        $pricingContext->method('getProduct')->willReturn($product);
        $pricingContext->method('getSubProducts')->willReturn([]);

        $localeDate = $this->createStub(TimezoneInterface::class);
        $localeDate->method('scopeTimeStamp')->willReturn($timestamp);

        $rule = $this->createStub(Rule::class);
        $rule->method('getAllRulePricesFromProduct')->willReturn([]);

        $resourceRuleFactory = $this->createStub(RuleFactory::class);
        $resourceRuleFactory->method('create')->willReturn($rule);

        $manager = $this->createObjectToTest(
            resourceRuleFactory: $resourceRuleFactory,
            localeDate: $localeDate
        );

        $result = $manager->getProductRules($pricingContext);

        $this->assertSame([], $result);
    }
}
