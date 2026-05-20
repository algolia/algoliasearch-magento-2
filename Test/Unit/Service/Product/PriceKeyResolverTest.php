<?php

namespace Algolia\AlgoliaSearch\Test\Unit\Service\Product;

use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Service\Product\PriceKeyResolver;
use Algolia\AlgoliaSearch\Test\TestCase;
use Magento\Customer\Model\Context as CustomerContext;
use Magento\Framework\App\Http\Context as HttpContext;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;

class PriceKeyResolverTest extends TestCase
{
    private PriceKeyResolver $priceKeyResolver;
    private ConfigHelper|MockObject $configHelper;
    private StoreManagerInterface|MockObject $storeManager;
    private HttpContext|MockObject $httpContext;

    protected function setUp(): void
    {
        $this->configHelper = $this->createMock(ConfigHelper::class);
        $this->storeManager = $this->createMock(StoreManagerInterface::class);
        $this->httpContext = $this->createMock(HttpContext::class);

        $this->priceKeyResolver = new PriceKeyResolver(
            $this->configHelper,
            $this->storeManager,
            $this->httpContext
        );
    }

    /**
     * @dataProvider priceKeyDataProvider
     */
    public function testGetPriceKeyWithVariousConfigurations(
        int $storeId,
        int $customerGroupId,
        bool $isCustomerGroupsEnabled,
        string $currencyCode,
        string $expectedPriceKey
    ): void {
        $storeMock = $this->getMockBuilder(StoreInterface::class)
            ->addMethods(['getCurrentCurrencyCode'])
            ->getMockForAbstractClass();

        $this->configHelper
            ->expects($this->once())
            ->method('isCustomerGroupsEnabled')
            ->with($storeId)
            ->willReturn($isCustomerGroupsEnabled);

        $this->httpContext
            ->method('getValue')
            ->with(CustomerContext::CONTEXT_GROUP)
            ->willReturn($customerGroupId);

        $this->storeManager
            ->expects($this->once())
            ->method('getStore')
            ->with($storeId)
            ->willReturn($storeMock);

        $storeMock
            ->expects($this->once())
            ->method('getCurrentCurrencyCode')
            ->willReturn($currencyCode);

        $result = $this->priceKeyResolver->getPriceKey($storeId);

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
                'expectedPriceKey' => '.USD.default'
            ],
            [
                'storeId' => 1,
                'customerGroupId' => 1,
                'isCustomerGroupsEnabled' => true,
                'currencyCode' => 'USD',
                'expectedPriceKey' => '.USD.group_1'
            ],
            [
                'storeId' => 1,
                'customerGroupId' => 2,
                'isCustomerGroupsEnabled' => true,
                'currencyCode' => 'USD',
                'expectedPriceKey' => '.USD.group_2'
            ],
            [
                'storeId' => 2,
                'customerGroupId' => 0,
                'isCustomerGroupsEnabled' => false,
                'currencyCode' => 'EUR',
                'expectedPriceKey' => '.EUR.default'
            ],
            [
                'storeId' => 2,
                'customerGroupId' => 3,
                'isCustomerGroupsEnabled' => true,
                'currencyCode' => 'EUR',
                'expectedPriceKey' => '.EUR.group_3'
            ],
            [
                'storeId' => 3,
                'customerGroupId' => 1,
                'isCustomerGroupsEnabled' => false,
                'currencyCode' => 'GBP',
                'expectedPriceKey' => '.GBP.default'
            ],
            [
                'storeId' => 3,
                'customerGroupId' => 4,
                'isCustomerGroupsEnabled' => true,
                'currencyCode' => 'GBP',
                'expectedPriceKey' => '.GBP.group_4'
            ],
            [
                'storeId' => 5,
                'customerGroupId' => 0,
                'isCustomerGroupsEnabled' => true,
                'currencyCode' => 'USD',
                'expectedPriceKey' => '.USD.group_0'
            ],
            [
                'storeId' => 10,
                'customerGroupId' => 10,
                'isCustomerGroupsEnabled' => true,
                'currencyCode' => 'JPY',
                'expectedPriceKey' => '.JPY.group_10'
            ],
            [
                'storeId' => 7,
                'customerGroupId' => 5,
                'isCustomerGroupsEnabled' => false,
                'currencyCode' => 'CAD',
                'expectedPriceKey' => '.CAD.default'
            ],
        ];
    }

    public function testGetPriceKeyCachesResultForSameStoreAndGroup(): void
    {
        $storeId = 1;
        $customerGroupId = 2;
        $currencyCode = 'USD';

        $storeMock = $this->getMockBuilder(StoreInterface::class)
            ->addMethods(['getCurrentCurrencyCode'])
            ->getMockForAbstractClass();

        // getGroupId() is called twice (once per getPriceKey call) to determine the cache key
        $this->configHelper
            ->expects($this->exactly(2))
            ->method('isCustomerGroupsEnabled')
            ->with($storeId)
            ->willReturn(true);

        $this->httpContext
            ->expects($this->exactly(2))
            ->method('getValue')
            ->with(CustomerContext::CONTEXT_GROUP)
            ->willReturn($customerGroupId);

        // Store and currency are only fetched once due to caching
        $this->storeManager
            ->expects($this->once())
            ->method('getStore')
            ->with($storeId)
            ->willReturn($storeMock);

        $storeMock
            ->expects($this->once())
            ->method('getCurrentCurrencyCode')
            ->willReturn($currencyCode);

        $result1 = $this->priceKeyResolver->getPriceKey($storeId);
        $result2 = $this->priceKeyResolver->getPriceKey($storeId);

        $this->assertEquals('.USD.group_2', $result1);
        $this->assertEquals('.USD.group_2', $result2);
        $this->assertSame($result1, $result2);
    }

    public function testGetPriceKeyDoesNotCacheAcrossDifferentStores(): void
    {
        $storeId1 = 1;
        $storeId2 = 2;
        $customerGroupId = 1;

        $storeMock1 = $this->getMockBuilder(StoreInterface::class)
            ->addMethods(['getCurrentCurrencyCode'])
            ->getMockForAbstractClass();

        $storeMock2 = $this->getMockBuilder(StoreInterface::class)
            ->addMethods(['getCurrentCurrencyCode'])
            ->getMockForAbstractClass();

        $this->configHelper
            ->method('isCustomerGroupsEnabled')
            ->willReturn(true);

        $this->httpContext
            ->method('getValue')
            ->with(CustomerContext::CONTEXT_GROUP)
            ->willReturn($customerGroupId);

        $this->storeManager
            ->method('getStore')
            ->willReturnMap([
                [$storeId1, $storeMock1],
                [$storeId2, $storeMock2],
            ]);

        $storeMock1
            ->expects($this->once())
            ->method('getCurrentCurrencyCode')
            ->willReturn('USD');

        $storeMock2
            ->expects($this->once())
            ->method('getCurrentCurrencyCode')
            ->willReturn('EUR');

        $result1 = $this->priceKeyResolver->getPriceKey($storeId1);
        $result2 = $this->priceKeyResolver->getPriceKey($storeId2);

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

        $storeMock1 = $this->getMockBuilder(StoreInterface::class)
            ->addMethods(['getCurrentCurrencyCode'])
            ->getMockForAbstractClass();

        $storeMock2 = $this->getMockBuilder(StoreInterface::class)
            ->addMethods(['getCurrentCurrencyCode'])
            ->getMockForAbstractClass();

        $this->configHelper
            ->method('isCustomerGroupsEnabled')
            ->with($storeId)
            ->willReturn(true);

        $this->httpContext
            ->method('getValue')
            ->with(CustomerContext::CONTEXT_GROUP)
            ->willReturnOnConsecutiveCalls($customerGroupId1, $customerGroupId2);

        $this->storeManager
            ->expects($this->exactly(2))
            ->method('getStore')
            ->with($storeId)
            ->willReturnOnConsecutiveCalls($storeMock1, $storeMock2);

        $storeMock1
            ->expects($this->once())
            ->method('getCurrentCurrencyCode')
            ->willReturn($currencyCode);

        $storeMock2
            ->expects($this->once())
            ->method('getCurrentCurrencyCode')
            ->willReturn($currencyCode);

        $result1 = $this->priceKeyResolver->getPriceKey($storeId);
        $result2 = $this->priceKeyResolver->getPriceKey($storeId);

        $this->assertEquals('.USD.group_1', $result1);
        $this->assertEquals('.USD.group_2', $result2);
        $this->assertNotEquals($result1, $result2);
    }

    public function testGetPriceKeyThrowsExceptionWhenStoreNotFound(): void
    {
        $storeId = 999;
        $customerGroupId = 1;

        $this->configHelper
            ->expects($this->once())
            ->method('isCustomerGroupsEnabled')
            ->with($storeId)
            ->willReturn(true);

        $this->httpContext
            ->method('getValue')
            ->with(CustomerContext::CONTEXT_GROUP)
            ->willReturn($customerGroupId);

        $this->storeManager
            ->expects($this->once())
            ->method('getStore')
            ->with($storeId)
            ->willThrowException(new NoSuchEntityException(__('Store not found')));

        $this->expectException(NoSuchEntityException::class);
        $this->expectExceptionMessage('Store not found');

        $this->priceKeyResolver->getPriceKey($storeId);
    }

    public function testGetGroupIdReturnsDefaultWhenCustomerGroupsDisabled(): void
    {
        $storeId = 1;

        $this->configHelper
            ->expects($this->once())
            ->method('isCustomerGroupsEnabled')
            ->with($storeId)
            ->willReturn(false);

        $this->httpContext
            ->expects($this->never())
            ->method('getValue');

        $result = $this->invokeMethod($this->priceKeyResolver, 'getGroupId', [$storeId]);

        $this->assertEquals('default', $result);
    }

    public function testGetGroupIdReturnsGroupIdWhenCustomerGroupsEnabled(): void
    {
        $storeId = 1;
        $customerGroupId = 5;

        $this->configHelper
            ->expects($this->once())
            ->method('isCustomerGroupsEnabled')
            ->with($storeId)
            ->willReturn(true);

        $this->httpContext
            ->expects($this->once())
            ->method('getValue')
            ->with(CustomerContext::CONTEXT_GROUP)
            ->willReturn($customerGroupId);

        $result = $this->invokeMethod($this->priceKeyResolver, 'getGroupId', [$storeId]);

        $this->assertEquals('group_5', $result);
    }

    public function testGetGroupIdHandlesStringCustomerGroupId(): void
    {
        $storeId = 1;
        $customerGroupId = '3';

        $this->configHelper
            ->expects($this->once())
            ->method('isCustomerGroupsEnabled')
            ->with($storeId)
            ->willReturn(true);

        $this->httpContext
            ->expects($this->once())
            ->method('getValue')
            ->with(CustomerContext::CONTEXT_GROUP)
            ->willReturn($customerGroupId);

        $result = $this->invokeMethod($this->priceKeyResolver, 'getGroupId', [$storeId]);

        $this->assertEquals('group_3', $result);
    }
}
