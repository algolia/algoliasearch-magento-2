<?php

namespace Algolia\AlgoliaSearch\Test\Integration\Category;

use Magento\Framework\App\Cache\Manager as CacheManager;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Response\Http as ResponseHttp;
use Magento\Framework\Indexer\IndexerRegistry;
use Magento\Store\Model\ScopeInterface;
use Magento\TestFramework\ObjectManager;
use Magento\TestFramework\TestCase\AbstractController;

class CategoryCacheTest extends AbstractController
{
    protected ?CacheManager $cacheManager;
    protected ?ScopeConfigInterface $config;

    protected static $cacheResets = [];

    protected $url = '/catalog/category/view/id/';

    public static function getCategoryProvider(): array
    {
        return [
//            ['categoryId' => 20, 'name' => 'Women'],
            ['categoryId' => 21, 'name' => 'Women > Tops'],
            ['categoryId' => 22, 'name' => 'Women > Bottoms'],
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->cacheManager = $this->_objectManager->get(CacheManager::class);
        $this->config = $this->_objectManager->get(ScopeConfigInterface::class);

        // Default user agent
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/134.0.0.0 Safari/537.36';
    }

    protected function resetCache($testMethod): void
    {
        if (!in_array($testMethod, self::$cacheResets)) {
            $this->cacheManager->clean(['full_page']);
            self::$cacheResets[] = $testMethod;
        }
    }

    public static function setUpBeforeClass(): void
    {
        // self::reindexAll();
    }

    /** You must index to OpenSearch to get the default backend render  */
    protected static function reindexAll(): void
    {
        $objectManager = ObjectManager::getInstance();
        $indexerRegistry = $objectManager->get(IndexerRegistry::class);

        $indexerCodes = [
            'catalog_category_product',
            'catalog_product_category',
            'catalog_product_price',
            'cataloginventory_stock',
            'catalogsearch_fulltext'
        ];

        foreach ($indexerCodes as $indexerCode) {
            $indexerRegistry->get($indexerCode)->reindexAll();
        }
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
    public function testCategoryPlpMissBackendRenderOn(int $categoryId, string $name): void
    {
        $replace = $this->config->getValue('algoliasearch_instant/instant/replace_categories', ScopeInterface::SCOPE_STORE);
        $this->assertEquals(1, $replace,"Replace categories must be enabled for this test.");

        $this->resetCache(__METHOD__);
        $this->dispatchCategoryPlpRequest($categoryId);
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
        $this->assertMatchesRegularExpression('/<div.*class=.*products-grid.*>/', $response->getContent(), $response->getContent(), 'Backend content was not rendered.');
    }

    /**
     * The \Magento\TestFramework\TestCase\AbstractController::dispatch is flawed for this use case as it does not
     * populate the URI which is used to build the cache key in \Magento\Framework\App\PageCache\Identifier::getValue
     *
     * This provides a workaround
     *
     * @param string $uri
     * @return void
     */
    protected function dispatchHttpRequest(string $uri): void
    {
        $request = $this->_objectManager->get(\Magento\Framework\App\Request\Http::class);
        $request->setDispatched(false);
        $request->setUri($uri);
        $request->setRequestUri($uri);
        $this->_getBootstrap()->runApp();
    }

    /**
     * It is imperative to always use the same URL format between MISS and HIT to ensure
     * that the cache key is generated consistently
     * @param int $categoryId
     * @return string
     */
    protected function getCategoryUrl(int $categoryId): string
    {
        return $this->url . $categoryId;
    }

    protected function dispatchCategoryPlpRequest(int $categoryId): void
    {
        $this->dispatchHttpRequest($this->getCategoryUrl($categoryId));
    }

    /**
     * @dataProvider getCategoryProvider
     * @depends      testCategoryPlpMissBackendRenderOn
     * @magentoConfigFixture current_store system/full_page_cache/caching_application 1
     * @magentoConfigFixture current_store algoliasearch_advanced/advanced/prevent_backend_rendering 0
     * @magentoConfigFixture current_store algoliasearch_instant/instant/replace_categories 1
     * @magentoCache full_page enabled
     * @param int $categoryId
     * @param string $name
     * @return void
     */
    public function testCategoryPlpHitBackendRenderOn(int $categoryId, string $name): void
    {
        $replace = $this->config->getValue('algoliasearch_instant/instant/replace_categories', ScopeInterface::SCOPE_STORE);
        $this->assertEquals(1, $replace,"Replace categories must be enabled for this test.");

        $this->registerPageHitSpy();

        $this->dispatchCategoryPlpRequest($categoryId);
        $response = $this->getResponse();
        $this->assertEquals(200, $response->getHttpResponseCode(), 'Request failed');
    }

    /**
     * @dataProvider getCategoryProvider
     * @depends      testFullPageCacheAvailable
     * @magentoConfigFixture current_store system/full_page_cache/caching_application 1
     * @magentoConfigFixture current_store algoliasearch_advanced/advanced/prevent_backend_rendering 1
     * @magentoConfigFixture current_store algoliasearch_instant/instant/replace_categories 1
     * @magentoCache full_page enabled
     * @param int $categoryId
     * @param string $name
     * @return void
     */
    public function testCategoryPlpMissBackendRenderOff(int $categoryId, string $name): void
    {
        $preventBackend = $this->config->getValue('algoliasearch_advanced/advanced/prevent_backend_rendering', ScopeInterface::SCOPE_STORE);
        $this->assertEquals(1, $preventBackend,"Prevent backend rendering must be enabled for this test.");

        $this->resetCache(__METHOD__);
        $this->dispatchCategoryPlpRequest($categoryId);
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
        $this->assertDoesNotMatchRegularExpression('/<div.*class=.*products-grid.*>/', $response->getContent(), $response->getContent(), 'Backend content was not rendered.');
    }

    /**
     * @dataProvider getCategoryProvider
     * @depends      testCategoryPlpMissBackendRenderOff
     * @magentoConfigFixture current_store system/full_page_cache/caching_application 1
     * @magentoConfigFixture current_store algoliasearch_advanced/advanced/prevent_backend_rendering 1
     * @magentoConfigFixture current_store algoliasearch_instant/instant/replace_categories 1
     * @magentoCache full_page enabled
     * @param int $categoryId
     * @param string $name
     * @return void
     */
    public function testCategoryPlpHitBackendRenderOff(int $categoryId, string $name): void
    {
        $preventBackend = $this->config->getValue('algoliasearch_advanced/advanced/prevent_backend_rendering', ScopeInterface::SCOPE_STORE);
        $this->assertEquals(1, $preventBackend,"Prevent backend rendering must be enabled for this test.");

        $this->registerPageHitSpy();

        $this->dispatchCategoryPlpRequest($categoryId);
        $response = $this->getResponse();

        $this->assertEquals(200, $response->getHttpResponseCode(), 'Request failed');
    }

    /**
     * @dataProvider getCategoryProvider
     * @depends      testCategoryPlpHitBackendRenderOff
     * @magentoConfigFixture current_store system/full_page_cache/caching_application 1
     * @magentoConfigFixture current_store algoliasearch_advanced/advanced/prevent_backend_rendering 1
     * @magentoConfigFixture current_store algoliasearch_instant/instant/replace_categories 1
     * @magentoDataFixture Algolia_AlgoliaSearch::Test/Integration/_files/backend_render_user_agents.php
     * @magentoCache full_page enabled
     * @param int $categoryId
     * @param string $name
     * @return void
     */
    public function testCategoryPlpMissBackendRenderWhiteList(int $categoryId, string $name): void
    {
        $preventBackend = $this->config->getValue('algoliasearch_advanced/advanced/prevent_backend_rendering', ScopeInterface::SCOPE_STORE);
        $this->assertEquals(1, $preventBackend,"Prevent backend rendering must be enabled for this test.");

        $testUserAgent = "Foobot";
        $whitelist = $this->config->getValue('algoliasearch_advanced/advanced/backend_rendering_allowed_user_agents', ScopeInterface::SCOPE_STORE);
        $this->assertStringContainsString($testUserAgent, $whitelist, "Allowed user agents for backend render must include $testUserAgent");

        $_SERVER['HTTP_USER_AGENT'] = $testUserAgent;

        $this->dispatchCategoryPlpRequest($categoryId);

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
        $this->assertMatchesRegularExpression('/<div.*class=.*products-grid.*>/', $response->getContent(), $response->getContent(), 'Backend content was not rendered.');
    }

    /**
     *  The response object is modified differently by the BuiltinPlugin which prevents anything useful
     *  being returned by AbstractController::getResponse when a HIT is encountered
     *
     *  Therefore we apply a "spy" on the plugin via a mock to ensure that the proper header is added
     *  when the cache has been warmed (by the first MISS)
     *
     * @return void
     */
    protected function registerPageHitSpy(): void
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
    }

    public function testFullPageCacheAvailable(): void
    {
        $types = $this->cacheManager->getAvailableTypes();
        $this->assertContains('full_page', $types);
    }

}
