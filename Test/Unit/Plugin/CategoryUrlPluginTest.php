<?php

declare(strict_types=1);

namespace Algolia\AlgoliaSearch\Test\Unit\Plugin;

use Algolia\AlgoliaSearch\Plugin\CategoryUrlPlugin;
use Algolia\AlgoliaSearch\Test\TestCase;
use Magento\Catalog\Model\Category;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\Url;
use PHPUnit\Framework\MockObject\MockObject;

class CategoryUrlPluginTest extends TestCase
{
    protected null|(ObjectManagerInterface&MockObject) $objectManager = null;
    protected ?CategoryUrlPlugin $plugin = null;

    protected function setUp(): void
    {
        $this->objectManager = $this->createMock(ObjectManagerInterface::class);
        $this->plugin = new CategoryUrlPlugin($this->objectManager);
    }

    public function testCallsProceedWhenStoreIdIsZero(): void
    {
        $category = $this->createMock(Category::class);
        $category->method('getStoreId')->willReturn(0);

        $expected = new \stdClass();
        $proceed = fn() => $expected;

        $result = $this->plugin->aroundGetUrlInstance($category, $proceed);

        $this->assertSame($expected, $result);
    }

    public function testCreatesUrlWithStoreIdWhenStoreIdIsNonZero(): void
    {
        $category = $this->createMock(Category::class);
        $category->method('getStoreId')->willReturn(3);

        $urlInstance = $this->getMockBuilder(Url::class)
            ->disableOriginalConstructor()
            ->addMethods(['setStoreId'])
            ->getMock();
        $urlInstance->expects($this->once())
            ->method('setStoreId')
            ->with(3)
            ->willReturnSelf();

        $this->objectManager->expects($this->once())
            ->method('create')
            ->with(Url::class)
            ->willReturn($urlInstance);

        $proceed = function () { $this->fail('proceed should not be called'); };

        $result = $this->plugin->aroundGetUrlInstance($category, $proceed);

        $this->assertSame($urlInstance, $result);
    }
}
