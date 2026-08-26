<?php

declare(strict_types=1);

namespace Algolia\AlgoliaSearch\Test\Unit\Plugin\Cache;

use Algolia\AlgoliaSearch\Helper\Entity\Product\CacheHelper;
use Algolia\AlgoliaSearch\Plugin\Cache\CacheCleanBulkAttributePlugin;
use Algolia\AlgoliaSearch\Test\TestCase;
use Magento\Catalog\Controller\Adminhtml\Product\Action\Attribute\Save;
use Magento\Catalog\Helper\Product\Edit\Action\Attribute;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Redirect;

class CacheCleanBulkAttributePluginTest extends TestCase
{
    protected function createObjectToTest(
        ?Attribute $attributeHelper = null,
        ?CacheHelper $cacheHelper = null,
    ): CacheCleanBulkAttributePlugin {
        return new CacheCleanBulkAttributePlugin(
            $attributeHelper ?? $this->createStub(Attribute::class),
            $cacheHelper ?? $this->createStub(CacheHelper::class),
        );
    }

    public function testAfterExecuteCallsHandleBulkAttributeChangeWithArgsFromHelperAndRequest(): void
    {
        $productIds = [1, 2, 3];
        $attributes = ['status' => '1'];
        $storeId = 2;

        $attributeHelper = $this->createStub(Attribute::class);
        $attributeHelper->method('getProductIds')->willReturn($productIds);
        $attributeHelper->method('getSelectedStoreId')->willReturn($storeId);

        $request = $this->createMock(RequestInterface::class);
        $request->method('getParam')->with('attributes', [])->willReturn($attributes);

        // getRequest() is unconditionally called exactly once by afterExecute().
        $subject = $this->getMockBuilder(Save::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getRequest'])
            ->getMock();
        $subject->expects($this->once())->method('getRequest')->willReturn($request);

        $redirect = $this->createStub(Redirect::class);

        $cacheHelper = $this->createMock(CacheHelper::class);
        $cacheHelper->expects($this->once())
            ->method('handleBulkAttributeChange')
            ->with($productIds, $attributes, $storeId);

        $plugin = $this->createObjectToTest($attributeHelper, $cacheHelper);

        $result = $plugin->afterExecute($subject, $redirect);

        $this->assertSame($redirect, $result);
    }
}
