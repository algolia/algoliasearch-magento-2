<?php

namespace Algolia\AlgoliaSearch\Test\Integration\Category;

use Magento\Framework\App\Cache\Manager as CacheManager;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Response\Http as ResponseHttp;
use Magento\Framework\App\ResponseInterface;
use Magento\Store\Model\ScopeInterface;

class CategoryCacheTest extends \Magento\TestFramework\TestCase\AbstractController
{
    protected $cacheManager;

    protected $url = '/catalog/category/view/id/';

    public static function getCategoryProvider(): array
    {
        return [
            ['categoryId' => 20, 'name' => 'Women'],
            ['categoryId' => 21, 'name' => 'Women > Tops'],
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->cacheManager = $this->_objectManager->get(CacheManager::class);
    }

    /**
     * @dataProvider getCategoryProvider
     * @depends      testFullPageCacheAvailable
     * @magentoConfigFixture current_store system/full_page_cache/caching_application 1
     * @magentoConfigFixture current_store algoliasearch_advanced/advanced/prevent_backend_rendering 0
     * @magentoConfigFixture current_store algoliasearch_instant/instant/replace_categories 1
     * @magentoCache full_page enabled
     * @param int $categoryId
     * @param string $name
     * @return void
     */
    public function testCategoryPlpMiss(int $categoryId, string $name): void
    {
        $config = $this->_objectManager->get(ScopeConfigInterface::class);
        $replace = $config->getValue('algoliasearch_instant/instant/replace_categories', ScopeInterface::SCOPE_STORE);
        $this->assertEquals(1, $replace,"Replace categories must be enabled for this test.");

        $this->cacheManager->clean(['full_page']);
        $this->dispatch($this->url . $categoryId);
        $response = $this->getResponse();
        $this->assertEquals(200, $response->getHttpResponseCode(), 'Request failed');
        $this->assertEquals(
            'MISS',
            $response->getHeader('X-Magento-Cache-Debug')->getFieldValue(),
            "expected MISS on category {$name} id {$categoryId}"
        );
        $this->assertContains(
            'FPC',
            explode(',', $response->getHeader('X-Magento-Tags')->getFieldValue()),
            "expected FPC tag on category {$name} id {$categoryId}"
        );
    }

    protected function resetResponse(): void
    {
        $this->_objectManager->removeSharedInstance(ResponseInterface::class);
        $this->_response = null;
    }

    /**
     * The response object is modified differently by the BuiltinPlugin which prevents anything useful
     * being returned by AbstractController::getResponse
     *
     * Therefore we apply a "spy" on the plugin via a mock to ensure that the proper header is added
     * when the cache has been warmed (by the first MISS)
     *
     * @dataProvider getCategoryProvider
     * @depends      testCategoryPlpMiss
     * @magentoConfigFixture current_store system/full_page_cache/caching_application 1
     * @magentoConfigFixture current_store algoliasearch_advanced/advanced/prevent_backend_rendering 0
     * @magentoConfigFixture current_store algoliasearch_instant/instant/replace_categories 1
     * @magentoCache full_page enabled
     * @param int $categoryId
     * @param string $name
     * @return void
     */
    public function testCategoryPlpHit(int $categoryId, string $name): void
    {
        $mockedPluginClass = \Magento\PageCache\Model\App\FrontController\BuiltinPlugin::class;
        $mockedPluginMethod = 'addDebugHeader';
        $cachePluginMock = $this->getMockBuilder($mockedPluginClass)
            ->setConstructorArgs([
                $this->_objectManager->get(\Magento\PageCache\Model\Config::class),
                $this->_objectManager->get(\Magento\Framework\App\PageCache\Version::class),
                $this->_objectManager->get(\Magento\Framework\App\PageCache\Kernel::class),
                $this->_objectManager->get(\Magento\Framework\App\State::class)
            ])
            ->onlyMethods([$mockedPluginMethod])
            ->getMock();
        $cachePluginMock
            ->expects($this->once())
            ->method($mockedPluginMethod)
            ->with(
                $this->isInstanceOf(ResponseHttp::class),
                $this->equalTo("X-Magento-Cache-Debug"),
                $this->equalTo("HIT"),
                $this->isType('boolean')
            )
            ->willReturnCallback(
                function (ResponseHttp $response, $name, $value, $replace)
                    use ($mockedPluginClass, $mockedPluginMethod, $cachePluginMock)
                {
                    $originalMethod = new \ReflectionMethod($mockedPluginClass, $mockedPluginMethod);
                    return $originalMethod->invoke($cachePluginMock, $response, $name, $value, $replace);
                }
            );
        $this->_objectManager->addSharedInstance(
            $cachePluginMock,
            $mockedPluginClass
        );

        $this->dispatch("catalog/category/view/id/{$categoryId}");
        $response = $this->getResponse();
        $this->assertEquals(200, $response->getHttpResponseCode(), 'Request failed');
    }

    public function testFullPageCacheAvailable(): void
    {
        $types = $this->cacheManager->getAvailableTypes();
        $this->assertContains('full_page', $types);
    }

    protected function dispatchNew($uri)
    {
        $request = $this->_objectManager->get(\Magento\Framework\App\RequestInterface::class);

        $request->setDispatched(false);
        $request->setRequestUri($uri);
        $this->_getBootstrap()->runApp();
    }

}
