<?php

declare(strict_types=1);

namespace Algolia\AlgoliaSearch\Test\Unit\Service\Product;

use Algolia\AlgoliaSearch\Api\Data\PricingContextInterface;
use Algolia\AlgoliaSearch\Model\ResourceModel\Rule;
use Algolia\AlgoliaSearch\Model\ResourceModel\RuleFactory;
use Algolia\AlgoliaSearch\Service\Product\CatalogRulesManager;
use Algolia\AlgoliaSearch\Service\Product\RulePricesStorage;
use Algolia\AlgoliaSearch\Test\TestCase;
use Magento\Catalog\Model\Product;
use Magento\Customer\Api\GroupManagementInterface;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Store\Model\Store;

class CatalogRulesManagerTest extends TestCase
{
    protected function createObjectToTest(
        ?RulePricesStorage $rulePricesStorage = null,
        ?RuleFactory $resourceRuleFactory = null,
        ?GroupManagementInterface $groupManagement = null,
        ?TimezoneInterface $localeDate = null
    ): CatalogRulesManager {
        return new CatalogRulesManager(
            $rulePricesStorage ?? $this->createStub(RulePricesStorage::class),
            $resourceRuleFactory ?? $this->createStub(RuleFactory::class),
            $groupManagement ?? $this->createStub(GroupManagementInterface::class),
            $localeDate ?? $this->createStub(TimezoneInterface::class)
        );
    }
    public function testGetProductRulesReturnsRulesFromStorageWhenAlreadyCached(): void
    {
        $productId = 100;
        $websiteId = 1;
        $timestamp = 1697241600;
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

        $rulePricesStorage = $this->createMock(RulePricesStorage::class);
        $rulePricesStorage->expects($this->once())
            ->method('hasRulePrice')
            ->with('100|1')
            ->willReturn(true);
        $rulePricesStorage->expects($this->once())
            ->method('getRulePrice')
            ->with('100|1')
            ->willReturn($expectedRules);

        $manager = $this->createObjectToTest(
            rulePricesStorage: $rulePricesStorage,
            localeDate: $localeDate
        );

        $result = $manager->getProductRules($pricingContext);

        $this->assertSame($expectedRules, $result);
    }

    public function testGetProductRulesFetchesRulesWhenNotCached(): void
    {
        $productId = 200;
        $websiteId = 2;
        $timestamp = 1697241600;
        $dbRules = [
            ['product_id' => 200, 'customer_group_id' => 0, 'rule_price' => '79.99'],
            ['product_id' => 200, 'customer_group_id' => 1, 'rule_price' => '69.99'],
        ];
        $expectedRules = [
            0 => 79.99,
            1 => 69.99,
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

        $rulePricesStorage = $this->createMock(RulePricesStorage::class);
        $rulePricesStorage->expects($this->once())
            ->method('hasRulePrice')
            ->with('200|2')
            ->willReturn(false);
        $rulePricesStorage->expects($this->once())
            ->method('setRulePrice')
            ->with('200|2', $this->anything());
        $rulePricesStorage->expects($this->once())
            ->method('getRulePrice')
            ->with('200|2')
            ->willReturn($expectedRules);

        $manager = $this->createObjectToTest(
            rulePricesStorage: $rulePricesStorage,
            resourceRuleFactory: $resourceRuleFactory,
            localeDate: $localeDate
        );

        $result = $manager->getProductRules($pricingContext);

        $this->assertSame($expectedRules, $result);
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

        $rulePricesStorage = $this->createMock(RulePricesStorage::class);
        $rulePricesStorage->expects($this->atLeastOnce())
            ->method('hasRulePrice')
            ->willReturn(false);
        $rulePricesStorage->expects($this->once())
            ->method('getRulePrice')
            ->willReturn([]);

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
            rulePricesStorage: $rulePricesStorage,
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
        $subProductRules = [0 => 49.99];

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

        $rulePricesStorage = $this->createMock(RulePricesStorage::class);
        $rulePricesStorage->expects($this->atLeastOnce())
            ->method('hasRulePrice')
            ->willReturn(true);
        $rulePricesStorage->expects($this->once())
            ->method('getRulePrice')
            ->with('101|1')
            ->willReturn($subProductRules);

        $manager = $this->createObjectToTest(
            rulePricesStorage: $rulePricesStorage,
            localeDate: $localeDate
        );

        $result = $manager->getProductRules($pricingContext, $subProduct);

        $this->assertSame($subProductRules, $result);
    }

    public function testGetProductRulesOnlyFetchesMissingRules(): void
    {
        $mainProductId = 100;
        $cachedSubProductId = 101;
        $uncachedSubProductId = 102;
        $websiteId = 1;
        $timestamp = 1697241600;

        $store = $this->createStub(Store::class);
        $store->method('getWebsiteId')->willReturn($websiteId);

        $mainProduct = $this->createStub(Product::class);
        $mainProduct->method('getId')->willReturn($mainProductId);

        $cachedSubProduct = $this->createStub(Product::class);
        $cachedSubProduct->method('getId')->willReturn($cachedSubProductId);

        $uncachedSubProduct = $this->createStub(Product::class);
        $uncachedSubProduct->method('getId')->willReturn($uncachedSubProductId);

        $pricingContext = $this->createStub(PricingContextInterface::class);
        $pricingContext->method('getStore')->willReturn($store);
        $pricingContext->method('getProduct')->willReturn($mainProduct);
        $pricingContext->method('getSubProducts')->willReturn([$cachedSubProduct, $uncachedSubProduct]);

        $localeDate = $this->createStub(TimezoneInterface::class);
        $localeDate->method('scopeTimeStamp')->willReturn($timestamp);

        $rulePricesStorage = $this->createMock(RulePricesStorage::class);
        $rulePricesStorage->expects($this->atLeastOnce())
            ->method('hasRulePrice')
            ->willReturnCallback(function ($key) {
                return $key === '101|1';
            });
        $rulePricesStorage->expects($this->once())
            ->method('getRulePrice')
            ->willReturn([]);

        $rule = $this->createMock(Rule::class);
        $rule->expects($this->once())
            ->method('getAllRulePricesFromProduct')
            ->with(
                $this->anything(),
                $websiteId,
                [$mainProductId, $uncachedSubProductId]
            )
            ->willReturn([]);

        $resourceRuleFactory = $this->createMock(RuleFactory::class);
        $resourceRuleFactory->expects($this->once())
            ->method('create')
            ->willReturn($rule);

        $manager = $this->createObjectToTest(
            rulePricesStorage: $rulePricesStorage,
            resourceRuleFactory: $resourceRuleFactory,
            localeDate: $localeDate
        );

        $manager->getProductRules($pricingContext);
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

        $capturedPrices = null;
        $rulePricesStorage = $this->createMock(RulePricesStorage::class);
        $rulePricesStorage->method('hasRulePrice')->willReturn(false);
        $rulePricesStorage->expects($this->once())
            ->method('setRulePrice')
            ->willReturnCallback(function ($key, $prices) use (&$capturedPrices) {
                $capturedPrices = $prices;
            });
        $rulePricesStorage->method('getRulePrice')->willReturn([]);

        $manager = $this->createObjectToTest(
            rulePricesStorage: $rulePricesStorage,
            resourceRuleFactory: $resourceRuleFactory,
            localeDate: $localeDate
        );

        $manager->getProductRules($pricingContext);

        $this->assertIsArray($capturedPrices);
        $this->assertArrayHasKey('300|1', $capturedPrices);
        $this->assertSame(99.99, $capturedPrices['300|1'][0]);
        $this->assertSame(89.0, $capturedPrices['300|1'][1]);
        $this->assertSame(79.999, $capturedPrices['300|1'][2]);
    }
}
