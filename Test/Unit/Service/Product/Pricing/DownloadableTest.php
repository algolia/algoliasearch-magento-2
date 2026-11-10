<?php

declare(strict_types=1);

namespace Algolia\AlgoliaSearch\Test\Unit\Service\Product\Pricing;

use Algolia\AlgoliaSearch\Api\Data\PriceDataInterface;
use Algolia\AlgoliaSearch\Api\Data\PricingContextInterface;
use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Helper\PricingHelper;
use Algolia\AlgoliaSearch\Logger\DiagnosticsLogger;
use Algolia\AlgoliaSearch\Service\Product\CatalogRulesManager;
use Algolia\AlgoliaSearch\Service\Product\PriceDataFormatter;
use Algolia\AlgoliaSearch\Service\Product\Pricing\CustomerGroups\CustomerGroupsManager;
use Algolia\AlgoliaSearch\Service\Product\Pricing\Downloadable;
use Algolia\AlgoliaSearch\Service\Product\PricingStrategyResolver;
use Algolia\AlgoliaSearch\Test\TestCase;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Customer\Model\Group;
use Magento\Customer\Model\ResourceModel\Group\Collection as CustomerGroupCollection;
use Magento\Framework\Pricing\Price\PriceInterface;
use Magento\Framework\Pricing\PriceInfo\Base as PriceInfo;
use Magento\Store\Model\Store;

/**
 * @covers \Algolia\AlgoliaSearch\Service\Product\Pricing\Downloadable
 */
class DownloadableTest extends TestCase
{
    /**
     * Create the object under test with optional dependency overrides
     */
    protected function createObjectToTest(
        ?ProductRepositoryInterface $productRepository = null,
        ?ConfigHelper $configHelper = null,
        ?PricingHelper $pricingHelper = null,
        ?DiagnosticsLogger $logger = null,
        ?PriceDataFormatter $priceDataFormatter = null,
        ?CatalogRulesManager $catalogRulesManager = null,
        ?CustomerGroupsManager $customerGroupsManager = null,
        ?PricingStrategyResolver $pricingStrategyResolver = null
    ): Downloadable {
        return new Downloadable(
            $productRepository ?? $this->createStub(ProductRepositoryInterface::class),
            $configHelper ?? $this->createStub(ConfigHelper::class),
            $pricingHelper ?? $this->createStub(PricingHelper::class),
            $logger ?? $this->createStub(DiagnosticsLogger::class),
            $priceDataFormatter ?? $this->createStub(PriceDataFormatter::class),
            $catalogRulesManager ?? $this->createStub(CatalogRulesManager::class),
            $customerGroupsManager ?? $this->createStub(CustomerGroupsManager::class),
            $pricingStrategyResolver ?? $this->createStub(PricingStrategyResolver::class)
        );
    }

    /**
     * Test that addCustomerGroupsPrices returns unchanged when customer groups disabled
     */
    public function testAddCustomerGroupsPricesReturnsUnchangedWhenCustomerGroupsDisabled(): void
    {
        $priceData = $this->createStub(PriceDataInterface::class);

        $pricingContext = $this->createStub(PricingContextInterface::class);
        $pricingContext->method('areCustomerGroupsEnabled')->willReturn(false);

        $downloadable = $this->createObjectToTest();

        $result = $this->invokeMethod($downloadable, 'addCustomerGroupsPrices', [$priceData, $pricingContext]);

        $this->assertSame($priceData, $result);
    }

    /**
     * Test that addCustomerGroupsPrices sets prices for each customer group
     */
    public function testAddCustomerGroupsPricesSetsPricesForEachGroup(): void
    {
        $group1 = $this->createStub(Group::class);
        $group1->method('getData')->willReturn(1);

        $group2 = $this->createStub(Group::class);
        $group2->method('getData')->willReturn(2);

        $groupCollection = $this->createStub(CustomerGroupCollection::class);
        $groupCollection->method('getIterator')->willReturn(new \ArrayIterator([$group1, $group2]));

        $customerGroupsManager = $this->createStub(CustomerGroupsManager::class);
        $customerGroupsManager->method('getCustomerGroups')->willReturn($groupCollection);

        $store = $this->createStub(Store::class);
        $store->method('getWebsiteId')->willReturn(1);

        $finalPrice = $this->createStub(PriceInterface::class);
        $finalPrice->method('getValue')->willReturn(90.00);

        $priceInfo = $this->createStub(PriceInfo::class);
        $priceInfo->method('getPrice')->willReturn($finalPrice);

        $reloadedProduct = $this->createStub(Product::class);
        $reloadedProduct->method('getStore')->willReturn($store);
        $reloadedProduct->method('getPriceInfo')->willReturn($priceInfo);

        $productRepository = $this->createStub(ProductRepositoryInterface::class);
        $productRepository->method('getById')->willReturn($reloadedProduct);

        $product = $this->createStub(Product::class);
        $product->method('getId')->willReturn(123);
        $product->method('getStoreId')->willReturn(1);

        $priceData = $this->createMock(PriceDataInterface::class);

        $pricingHelper = $this->createStub(PricingHelper::class);
        $pricingHelper->method('handleConversion')->willReturnArgument(0);
        $pricingHelper->method('getTaxPrice')->willReturnArgument(1);

        $pricingContext = $this->createStub(PricingContextInterface::class);
        $pricingContext->method('areCustomerGroupsEnabled')->willReturn(true);
        $pricingContext->method('getProduct')->willReturn($product);

        // Expect setPrice to be called for each group
        $priceData->expects($this->exactly(2))
            ->method('setPrice');

        $downloadable = $this->createObjectToTest(
            productRepository: $productRepository,
            pricingHelper: $pricingHelper,
            customerGroupsManager: $customerGroupsManager
        );

        $result = $this->invokeMethod($downloadable, 'addCustomerGroupsPrices', [$priceData, $pricingContext]);

        $this->assertSame($priceData, $result);
    }

    /**
     * Test that addCustomerGroupsPrices uses default price when discounted price is false
     */
    public function testAddCustomerGroupsPricesUsesDefaultPriceWhenDiscountedIsFalse(): void
    {
        $group1 = $this->createStub(Group::class);
        $group1->method('getData')->willReturn(1);

        $groupCollection = $this->createStub(CustomerGroupCollection::class);
        $groupCollection->method('getIterator')->willReturn(new \ArrayIterator([$group1]));

        $customerGroupsManager = $this->createStub(CustomerGroupsManager::class);
        $customerGroupsManager->method('getCustomerGroups')->willReturn($groupCollection);

        $store = $this->createStub(Store::class);
        $store->method('getWebsiteId')->willReturn(1);

        $finalPrice = $this->createStub(PriceInterface::class);
        $finalPrice->method('getValue')->willReturn(0.0);

        $priceInfo = $this->createStub(PriceInfo::class);
        $priceInfo->method('getPrice')->willReturn($finalPrice);

        $reloadedProduct = $this->createStub(Product::class);
        $reloadedProduct->method('getStore')->willReturn($store);
        $reloadedProduct->method('getPriceInfo')->willReturn($priceInfo);

        $productRepository = $this->createStub(ProductRepositoryInterface::class);
        $productRepository->method('getById')->willReturn($reloadedProduct);

        $product = $this->createStub(Product::class);
        $product->method('getId')->willReturn(123);
        $product->method('getStoreId')->willReturn(1);

        $priceData = $this->createMock(PriceDataInterface::class);
        $priceData->method('getPrice')->willReturn(100.00);

        $pricingHelper = $this->createStub(PricingHelper::class);
        $pricingHelper->method('handleConversion')->willReturn(0.0);
        $pricingHelper->method('getTaxPrice')->willReturn(0.0);

        $pricingContext = $this->createStub(PricingContextInterface::class);
        $pricingContext->method('areCustomerGroupsEnabled')->willReturn(true);
        $pricingContext->method('getProduct')->willReturn($product);

        $priceData->expects($this->once())
            ->method('setPrice')
            ->with(0.0, 1);

        $downloadable = $this->createObjectToTest(
            productRepository: $productRepository,
            pricingHelper: $pricingHelper,
            customerGroupsManager: $customerGroupsManager
        );

        $this->invokeMethod($downloadable, 'addCustomerGroupsPrices', [$priceData, $pricingContext]);
    }
}
