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
use PHPUnit\Framework\MockObject\MockObject;

class CacheCleanBulkAttributePluginTest extends TestCase
{
    protected null|(Attribute&MockObject) $attributeHelper = null;
    protected null|(CacheHelper&MockObject) $cacheHelper = null;
    protected ?CacheCleanBulkAttributePlugin $plugin = null;

    protected function setUp(): void
    {
        $this->attributeHelper = $this->createMock(Attribute::class);
        $this->cacheHelper = $this->createMock(CacheHelper::class);
        $this->plugin = new CacheCleanBulkAttributePlugin($this->attributeHelper, $this->cacheHelper);
    }

    public function testAfterExecuteCallsHandleBulkAttributeChangeWithArgsFromHelperAndRequest(): void
    {
        $productIds = [1, 2, 3];
        $attributes = ['status' => '1'];
        $storeId = 2;

        $this->attributeHelper->method('getProductIds')->willReturn($productIds);
        $this->attributeHelper->method('getSelectedStoreId')->willReturn($storeId);

        $request = $this->createMock(RequestInterface::class);
        $request->method('getParam')->with('attributes', [])->willReturn($attributes);

        $subject = $this->getMockBuilder(Save::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getRequest'])
            ->getMock();
        $subject->method('getRequest')->willReturn($request);

        $redirect = $this->createMock(Redirect::class);

        $this->cacheHelper->expects($this->once())
            ->method('handleBulkAttributeChange')
            ->with($productIds, $attributes, $storeId);

        $result = $this->plugin->afterExecute($subject, $redirect);

        $this->assertSame($redirect, $result);
    }
}
