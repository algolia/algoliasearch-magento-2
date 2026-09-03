<?php

declare(strict_types=1);

namespace Algolia\AlgoliaSearch\Test\Unit\Plugin;

use Algolia\AlgoliaSearch\Plugin\CategoryUrlPlugin;
use Algolia\AlgoliaSearch\Test\TestCase;
use Magento\Catalog\Model\Category;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\Url;

class CategoryUrlPluginTest extends TestCase
{
    protected function createObjectToTest(?ObjectManagerInterface $objectManager = null): CategoryUrlPlugin
    {
        return new CategoryUrlPlugin($objectManager ?? $this->createStub(ObjectManagerInterface::class));
    }

    public function testCallsProceedWhenStoreIdIsZero(): void
    {
        $category = $this->createStub(Category::class);
        $category->method('getStoreId')->willReturn(0);

        $expected = new \stdClass();
        $proceed = fn() => $expected;

        $plugin = $this->createObjectToTest();

        $result = $plugin->aroundGetUrlInstance($category, $proceed);

        $this->assertSame($expected, $result);
    }

    public function testCreatesUrlWithStoreIdWhenStoreIdIsNonZero(): void
    {
        $category = $this->createStub(Category::class);
        $category->method('getStoreId')->willReturn(3);

        $urlInstance = $this->getMockBuilder(Url::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['__call'])
            ->getMock();
        $urlInstance->expects($this->once())
            ->method('__call')
            ->with('setStoreId', [3])
            ->willReturnSelf();

        $objectManager = $this->createMock(ObjectManagerInterface::class);
        $objectManager->expects($this->once())
            ->method('create')
            ->with(Url::class)
            ->willReturn($urlInstance);

        $plugin = $this->createObjectToTest($objectManager);

        $proceed = function () { $this->fail('proceed should not be called'); };

        $result = $plugin->aroundGetUrlInstance($category, $proceed);

        $this->assertSame($urlInstance, $result);
    }
}
