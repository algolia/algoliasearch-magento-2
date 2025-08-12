<?php

namespace Algolia\AlgoliaSearch\Test\Unit\Plugin;

use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Helper\Configuration\InstantSearchHelper;
use Algolia\AlgoliaSearch\Plugin\RenderingCacheContextPlugin;
use Magento\Framework\App\Http\Context as HttpContext;
use Magento\Framework\App\Request\Http;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\UrlRewrite\Model\UrlFinderInterface;
use PHPUnit\Framework\TestCase;
class RenderingCacheContextPluginTest extends TestCase
{
    protected ?RenderingCacheContextPlugin $plugin;
    protected ?ConfigHelper $configHelper;

    protected ?InstantSearchHelper $instantSearchHelper;
    protected ?StoreManagerInterface $storeManager;
    protected ?Http $request;
    protected ?UrlFinderInterface $urlFinder;
    protected function setUp(): void
    {
        $this->configHelper = $this->createMock(ConfigHelper::class);
        $this->instantSearchHelper = $this->createMock(InstantSearchHelper::class);
        $this->storeManager = $this->createMock(StoreManagerInterface::class);
        $this->request = $this->createMock(Http::class);
        $this->urlFinder = $this->createMock(UrlFinderInterface::class);

        $this->plugin = new RenderingCacheContextPluginTestable(
            $this->configHelper,
            $this->instantSearchHelper,
            $this->storeManager,
            $this->request,
            $this->urlFinder
        );
    }

    protected function getStoreMock(): StoreInterface
    {
        $store = $this->createMock(StoreInterface::class);
        $store->method('getId')->willReturn(1);
        return $store;
    }

    public function testAfterGetDataAddsRenderingContextNoBackendRender(): void
    {
        $this->configHelper->method('preventBackendRendering')->willReturn(true);
        $this->storeManager->method('getStore')->willReturn($this->getStoreMock());

        $this->request->method('getControllerName')->willReturn('category');
        $this->instantSearchHelper->method('shouldReplaceCategories')->willReturn(true);

        $result = $this->plugin->afterGetData(
            $this->createMock(HttpContext::class),
            []
        );

        $this->assertArrayHasKey(RenderingCacheContextPlugin::RENDERING_CONTEXT, $result);
        $this->assertEquals(RenderingCacheContextPlugin::RENDERING_WITHOUT_BACKEND, $result[RenderingCacheContextPlugin::RENDERING_CONTEXT]);
    }

    public function testAfterGetDataAddsRenderingContextWithBackendRender(): void
    {
        $subject = $this->createMock(HttpContext::class);

        $this->configHelper->method('preventBackendRendering')->willReturn(false);
        $this->storeManager->method('getStore')->willReturn($this->getStoreMock());

        $this->request->method('getControllerName')->willReturn('category');
        $this->instantSearchHelper->method('shouldReplaceCategories')->willReturn(true);

        $result = $this->plugin->afterGetData(
            $this->createMock(HttpContext::class),
            []
        );

        $this->assertArrayHasKey(RenderingCacheContextPlugin::RENDERING_CONTEXT, $result);
        $this->assertEquals(RenderingCacheContextPlugin::RENDERING_WITH_BACKEND, $result[RenderingCacheContextPlugin::RENDERING_CONTEXT]);
    }

    public function testAfterGetDataDoesNotModifyDataIfNotApplicable(): void
    {
        $subject = $this->createMock(HttpContext::class);

        $this->configHelper->method('preventBackendRendering')->willReturn(false);
        $this->storeManager->method('getStore')->willReturn($this->getStoreMock());

        $this->request->method('getControllerName')->willReturn('product');
        $this->request->method('getRequestUri')->willReturn('some-product.html');
        $this->instantSearchHelper->method('shouldReplaceCategories')->willReturn(false);

        $data = ['existing_key' => 'existing_value'];
        $result = $this->plugin->afterGetData($subject, $data);

        $this->assertEquals($data, $result);
    }

    public function testIsCategoryRoute(): void
    {
        $this->assertTrue($this->plugin->isCategoryRoute('catalog/category/view'));
        $this->assertFalse($this->plugin->isCategoryRoute('some/other/route'));
    }

    public function testGetOriginalRoute(): void
    {
        $storeId = 1;
        $requestUri = '/some-path';
        $targetPath = 'catalog/category/view/id/42';

        $this->request->method('getRequestUri')->willReturn($requestUri);

        $urlRewrite = $this->createMock(\Magento\UrlRewrite\Service\V1\Data\UrlRewrite::class);
        $urlRewrite->method('getTargetPath')->willReturn($targetPath);

        $this->urlFinder->method('findOneByData')->willReturn($urlRewrite);

        $this->assertEquals($targetPath, $this->plugin->getOriginalRoute($storeId));
    }

    public function testShouldApplyCacheContext(): void
    {
        $this->storeManager->method('getStore')->willReturn($this->getStoreMock());

        $this->request->method('getControllerName')->willReturn('category');
        $this->instantSearchHelper->method('shouldReplaceCategories')->willReturn(true);

        $this->assertTrue($this->plugin->shouldApplyCacheContext());
    }
}
