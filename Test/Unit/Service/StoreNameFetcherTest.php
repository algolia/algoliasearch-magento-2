<?php

declare(strict_types=1);

namespace Algolia\AlgoliaSearch\Test\Unit\Service;

use Algolia\AlgoliaSearch\Service\StoreNameFetcher;
use Algolia\AlgoliaSearch\Test\TestCase;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;

class StoreNameFetcherTest extends TestCase
{
    protected function createObjectToTest(?StoreManagerInterface $storeManager = null): StoreNameFetcher
    {
        return new StoreNameFetcher($storeManager ?? $this->createStub(StoreManagerInterface::class));
    }

    protected function createStoreStub(string $name): StoreInterface
    {
        $store = $this->createStub(StoreInterface::class);
        $store->method('getName')->willReturn($name);

        return $store;
    }

    public function testGetStoreNameReturnsStoreNameAndCachesIt(): void
    {
        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->expects($this->once())
            ->method('getStore')
            ->with(2)
            ->willReturn($this->createStoreStub('Second Store'));

        $fetcher = $this->createObjectToTest($storeManager);

        $this->assertSame('Second Store', $fetcher->getStoreName(2));
        $this->assertSame('Second Store', $fetcher->getStoreName(2));
    }

    public function testGetStoreNamesReturnsNamesInGivenOrder(): void
    {
        $storeManager = $this->createStub(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturnCallback(
            fn(int $storeId) => $this->createStoreStub('Store ' . $storeId)
        );

        $this->assertSame(
            ['Store 3', 'Store 2'],
            $this->createObjectToTest($storeManager)->getStoreNames([3, 2])
        );
    }

    public function testResolveStoreNamesReturnsNamesKeyedByStoreId(): void
    {
        $storeManager = $this->createStub(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturnCallback(
            fn(int $storeId) => $this->createStoreStub('Store ' . $storeId)
        );

        $this->assertSame(
            [2 => 'Store 2', 3 => 'Store 3'],
            $this->createObjectToTest($storeManager)->resolveStoreNames([2, 3])
        );
    }

    public function testResolveStoreNamesFallsBackToIdLabelsByDefault(): void
    {
        $storeManager = $this->createStub(StoreManagerInterface::class);
        $storeManager->method('getStore')
            ->willThrowException(NoSuchEntityException::singleField('storeId', 42));

        $this->assertSame(
            [42 => 'Store ID 42'],
            $this->createObjectToTest($storeManager)->resolveStoreNames([42])
        );
    }

    public function testResolveStoreNamesThrowsWhenFailOnMissingStoreIsSet(): void
    {
        $storeManager = $this->createStub(StoreManagerInterface::class);
        $storeManager->method('getStore')
            ->willThrowException(NoSuchEntityException::singleField('storeId', 42));

        $this->expectException(NoSuchEntityException::class);

        $this->createObjectToTest($storeManager)->resolveStoreNames([42], true);
    }
}
