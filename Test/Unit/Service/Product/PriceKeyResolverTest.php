<?php

namespace Algolia\AlgoliaSearch\Test\Unit\Service\Product;

use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Service\Product\PriceKeyResolver;
use Algolia\AlgoliaSearch\Test\TestCase;
use Magento\Customer\Model\Context as CustomerContext;
use Magento\Framework\App\Http\Context as HttpContext;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;

class PriceKeyResolverTest extends TestCase
{
    protected function createObjectToTest(
        ?ConfigHelper $configHelper = null,
        ?StoreManagerInterface $storeManager = null,
        ?HttpContext $httpContext = null,
    ): PriceKeyResolver {
        return new PriceKeyResolver(
            $configHelper ?? $this->createStub(ConfigHelper::class),
            $storeManager ?? $this->createStub(StoreManagerInterface::class),
            $httpContext ?? $this->createStub(HttpContext::class),
        );
    }

    #[DataProvider('priceKeyDataProvider')]
    public function testGetPriceKeyWithVariousConfigurations(
        int $storeId,
        int $customerGroupId,
        bool $isCustomerGroupsEnabled,
        string $currencyCode,
        string $expectedPriceKey
    ): void {
        $storeMock = $this->createMock(Store::class);
        $storeMock->expects($this->once())->method('getCurrentCurrencyCode')->willReturn($currencyCode);

        $configHelper = $this->createMock(ConfigHelper::class);
        $configHelper->expects($this->once())
            ->method('isCustomerGroupsEnabled')
            ->with($storeId)
            ->willReturn($isCustomerGroupsEnabled);

        // getGroupId() only calls the http context when customer groups are enabled.
        $httpContext = $this->createMock(HttpContext::class);
        $httpContext->expects($isCustomerGroupsEnabled ? $this->once() : $this->never())
            ->method('getValue')
            ->with(CustomerContext::CONTEXT_GROUP)
            ->willReturn($customerGroupId);

        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->expects($this->once())
            ->method('getStore')
            ->with($storeId)
            ->willReturn($storeMock);

        $priceKeyResolver = $this->createObjectToTest($configHelper, $storeManager, $httpContext);

        $result = $priceKeyResolver->getPriceKey($storeId);

        $this->assertEquals($expectedPriceKey, $result);
    }

    public static function priceKeyDataProvider(): array
    {
        return [
            [
                'storeId' => 1,
                'customerGroupId' => 0,
                'isCustomerGroupsEnabled' => false,
                'currencyCode' => 'USD',
                'expectedPriceKey' => '.USD.default',
            ],
            [
                'storeId' => 1,
                'customerGroupId' => 1,
                'isCustomerGroupsEnabled' => true,
                'currencyCode' => 'USD',
                'expectedPriceKey' => '.USD.group_1',
            ],
            [
                'storeId' => 1,
                'customerGroupId' => 2,
                'isCustomerGroupsEnabled' => true,
                'currencyCode' => 'USD',
                'expectedPriceKey' => '.USD.group_2',
            ],
            [
                'storeId' => 2,
                'customerGroupId' => 0,
                'isCustomerGroupsEnabled' => false,
                'currencyCode' => 'EUR',
                'expectedPriceKey' => '.EUR.default',
            ],
            [
                'storeId' => 2,
                'customerGroupId' => 3,
                'isCustomerGroupsEnabled' => true,
                'currencyCode' => 'EUR',
                'expectedPriceKey' => '.EUR.group_3',
            ],
            [
                'storeId' => 3,
                'customerGroupId' => 1,
                'isCustomerGroupsEnabled' => false,
                'currencyCode' => 'GBP',
                'expectedPriceKey' => '.GBP.default',
            ],
            [
                'storeId' => 3,
                'customerGroupId' => 4,
                'isCustomerGroupsEnabled' => true,
                'currencyCode' => 'GBP',
                'expectedPriceKey' => '.GBP.group_4',
            ],
            [
                'storeId' => 5,
                'customerGroupId' => 0,
                'isCustomerGroupsEnabled' => true,
                'currencyCode' => 'USD',
                'expectedPriceKey' => '.USD.group_0',
            ],
            [
                'storeId' => 10,
                'customerGroupId' => 10,
                'isCustomerGroupsEnabled' => true,
                'currencyCode' => 'JPY',
                'expectedPriceKey' => '.JPY.group_10',
            ],
            [
                'storeId' => 7,
                'customerGroupId' => 5,
                'isCustomerGroupsEnabled' => false,
                'currencyCode' => 'CAD',
                'expectedPriceKey' => '.CAD.default',
            ],
        ];
    }

    public function testGetPriceKeyCachesResultForSameStoreAndGroup(): void
    {
        $storeId = 1;
        $customerGroupId = 2;
        $currencyCode = 'USD';

        $storeMock = $this->createMock(Store::class);
        // Store and currency are only fetched once due to caching
        $storeMock->expects($this->once())->method('getCurrentCurrencyCode')->willReturn($currencyCode);

        // getGroupId() is called twice (once per getPriceKey call) to determine the cache key
        $configHelper = $this->createMock(ConfigHelper::class);
        $configHelper->expects($this->exactly(2))
            ->method('isCustomerGroupsEnabled')
            ->with($storeId)
            ->willReturn(true);

        $httpContext = $this->createMock(HttpContext::class);
        $httpContext->expects($this->exactly(2))
            ->method('getValue')
            ->with(CustomerContext::CONTEXT_GROUP)
            ->willReturn($customerGroupId);

        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->expects($this->once())
            ->method('getStore')
            ->with($storeId)
            ->willReturn($storeMock);

        $priceKeyResolver = $this->createObjectToTest($configHelper, $storeManager, $httpContext);

        $result1 = $priceKeyResolver->getPriceKey($storeId);
        $result2 = $priceKeyResolver->getPriceKey($storeId);

        $this->assertEquals('.USD.group_2', $result1);
        $this->assertEquals('.USD.group_2', $result2);
        $this->assertSame($result1, $result2);
    }

    public function testGetPriceKeyDoesNotCacheAcrossDifferentStores(): void
    {
        $storeId1 = 1;
        $storeId2 = 2;
        $customerGroupId = 1;

        $storeMock1 = $this->createMock(Store::class);
        $storeMock1->expects($this->once())->method('getCurrentCurrencyCode')->willReturn('USD');

        $storeMock2 = $this->createMock(Store::class);
        $storeMock2->expects($this->once())->method('getCurrentCurrencyCode')->willReturn('EUR');

        $configHelper = $this->createMock(ConfigHelper::class);
        $configHelper->expects($this->exactly(2))->method('isCustomerGroupsEnabled')->willReturn(true);

        $httpContext = $this->createMock(HttpContext::class);
        $httpContext->expects($this->exactly(2))
            ->method('getValue')
            ->with(CustomerContext::CONTEXT_GROUP)
            ->willReturn($customerGroupId);

        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->expects($this->exactly(2))
            ->method('getStore')
            ->willReturnMap([
                [$storeId1, $storeMock1],
                [$storeId2, $storeMock2],
            ]);

        $priceKeyResolver = $this->createObjectToTest($configHelper, $storeManager, $httpContext);

        $result1 = $priceKeyResolver->getPriceKey($storeId1);
        $result2 = $priceKeyResolver->getPriceKey($storeId2);

        $this->assertEquals('.USD.group_1', $result1);
        $this->assertEquals('.EUR.group_1', $result2);
        $this->assertNotEquals($result1, $result2);
    }

    public function testGetPriceKeyDoesNotCacheAcrossDifferentGroups(): void
    {
        $storeId = 1;
        $customerGroupId1 = 1;
        $customerGroupId2 = 2;
        $currencyCode = 'USD';

        $storeMock1 = $this->createMock(Store::class);
        $storeMock1->expects($this->once())->method('getCurrentCurrencyCode')->willReturn($currencyCode);

        $storeMock2 = $this->createMock(Store::class);
        $storeMock2->expects($this->once())->method('getCurrentCurrencyCode')->willReturn($currencyCode);

        $configHelper = $this->createMock(ConfigHelper::class);
        $configHelper->expects($this->exactly(2))
            ->method('isCustomerGroupsEnabled')
            ->with($storeId)
            ->willReturn(true);

        $httpContext = $this->createMock(HttpContext::class);
        $httpContext->expects($this->exactly(2))
            ->method('getValue')
            ->with(CustomerContext::CONTEXT_GROUP)
            ->willReturnOnConsecutiveCalls($customerGroupId1, $customerGroupId2);

        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->expects($this->exactly(2))
            ->method('getStore')
            ->with($storeId)
            ->willReturnOnConsecutiveCalls($storeMock1, $storeMock2);

        $priceKeyResolver = $this->createObjectToTest($configHelper, $storeManager, $httpContext);

        $result1 = $priceKeyResolver->getPriceKey($storeId);
        $result2 = $priceKeyResolver->getPriceKey($storeId);

        $this->assertEquals('.USD.group_1', $result1);
        $this->assertEquals('.USD.group_2', $result2);
        $this->assertNotEquals($result1, $result2);
    }

    public function testGetPriceKeyThrowsExceptionWhenStoreNotFound(): void
    {
        $storeId = 999;
        $customerGroupId = 1;

        $configHelper = $this->createMock(ConfigHelper::class);
        $configHelper->expects($this->once())
            ->method('isCustomerGroupsEnabled')
            ->with($storeId)
            ->willReturn(true);

        // getGroupId() runs (and calls the http context) before getStore() is reached and throws.
        $httpContext = $this->createMock(HttpContext::class);
        $httpContext->expects($this->once())
            ->method('getValue')
            ->with(CustomerContext::CONTEXT_GROUP)
            ->willReturn($customerGroupId);

        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->expects($this->once())
            ->method('getStore')
            ->with($storeId)
            ->willThrowException(new NoSuchEntityException(__('Store not found')));

        $priceKeyResolver = $this->createObjectToTest($configHelper, $storeManager, $httpContext);

        $this->expectException(NoSuchEntityException::class);
        $this->expectExceptionMessage('Store not found');

        $priceKeyResolver->getPriceKey($storeId);
    }

    public function testGetGroupIdReturnsDefaultWhenCustomerGroupsDisabled(): void
    {
        $storeId = 1;

        $configHelper = $this->createMock(ConfigHelper::class);
        $configHelper->expects($this->once())
            ->method('isCustomerGroupsEnabled')
            ->with($storeId)
            ->willReturn(false);

        $httpContext = $this->createMock(HttpContext::class);
        $httpContext->expects($this->never())->method('getValue');

        $priceKeyResolver = $this->createObjectToTest($configHelper, httpContext: $httpContext);

        $result = $this->invokeMethod($priceKeyResolver, 'getGroupId', [$storeId]);

        $this->assertEquals('default', $result);
    }

    public function testGetGroupIdReturnsGroupIdWhenCustomerGroupsEnabled(): void
    {
        $storeId = 1;
        $customerGroupId = 5;

        $configHelper = $this->createMock(ConfigHelper::class);
        $configHelper->expects($this->once())
            ->method('isCustomerGroupsEnabled')
            ->with($storeId)
            ->willReturn(true);

        $httpContext = $this->createMock(HttpContext::class);
        $httpContext->expects($this->once())
            ->method('getValue')
            ->with(CustomerContext::CONTEXT_GROUP)
            ->willReturn($customerGroupId);

        $priceKeyResolver = $this->createObjectToTest($configHelper, httpContext: $httpContext);

        $result = $this->invokeMethod($priceKeyResolver, 'getGroupId', [$storeId]);

        $this->assertEquals('group_5', $result);
    }

    public function testGetGroupIdHandlesStringCustomerGroupId(): void
    {
        $storeId = 1;
        $customerGroupId = '3';

        $configHelper = $this->createMock(ConfigHelper::class);
        $configHelper->expects($this->once())
            ->method('isCustomerGroupsEnabled')
            ->with($storeId)
            ->willReturn(true);

        $httpContext = $this->createMock(HttpContext::class);
        $httpContext->expects($this->once())
            ->method('getValue')
            ->with(CustomerContext::CONTEXT_GROUP)
            ->willReturn($customerGroupId);

        $priceKeyResolver = $this->createObjectToTest($configHelper, httpContext: $httpContext);

        $result = $this->invokeMethod($priceKeyResolver, 'getGroupId', [$storeId]);

        $this->assertEquals('group_3', $result);
    }
}
