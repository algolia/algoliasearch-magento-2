<?php

declare(strict_types=1);

namespace Algolia\AlgoliaSearch\Test\Unit\Block;

use Algolia\AlgoliaSearch\Block\LandingPage;
use Algolia\AlgoliaSearch\Model\LandingPage as LandingPageModel;
use Algolia\AlgoliaSearch\Model\LandingPageFactory;
use Algolia\AlgoliaSearch\Test\TestCase;
use Magento\Catalog\Model\Layer;
use Magento\Catalog\Model\Layer\Resolver as LayerResolver;
use Magento\CatalogSearch\Helper\Data;
use Magento\Cms\Model\Template\FilterProvider;
use Magento\Framework\View\Element\Template\Context;
use Magento\Search\Model\QueryFactory;

class LandingPageTest extends TestCase
{
    protected function createObjectToTest(
        ?LandingPageModel $landingPage = null,
        ?FilterProvider $filterProvider = null,
        ?LandingPageFactory $landingPageFactory = null,
    ): LandingPage {
        $context = $this->createStub(Context::class);

        $layerResolver = $this->createStub(LayerResolver::class);
        $layerResolver->method('get')->willReturn($this->createStub(Layer::class));

        return new LandingPage(
            $context,
            $layerResolver,
            $this->createStub(Data::class),
            $this->createStub(QueryFactory::class),
            $filterProvider ?? $this->createStub(FilterProvider::class),
            $landingPage ?? $this->createStub(LandingPageModel::class),
            $landingPageFactory ?? $this->createStub(LandingPageFactory::class),
        );
    }

    public function testGetPageReturnsCachedLandingPageWhenNoPageId(): void
    {
        // No page_id data set, so the real getPageId() magic getter returns null → falls back
        // to the injected landingPage.
        $landingPage = $this->createStub(LandingPageModel::class);
        $block = $this->createObjectToTest($landingPage);

        $this->assertSame($landingPage, $block->getPage());
    }

    public function testGetPageReturnsSameCachedInstanceOnSecondCall(): void
    {
        $block = $this->createObjectToTest();

        $first = $block->getPage();
        $second = $block->getPage();

        $this->assertSame($first, $second);
    }

    public function testGetLandingCustomJsReturnsEmptyStringWhenNoCustomJs(): void
    {
        $landingPage = $this->createStub(LandingPageModel::class);
        $landingPage->method('getCustomJs')->willReturn('');

        $block = $this->createObjectToTest($landingPage);

        $result = $this->invokeMethod($block, 'getLandingCustomJs');

        $this->assertSame('', $result);
    }

    public function testGetLandingCustomJsWrapsJsInScriptTag(): void
    {
        $landingPage = $this->createStub(LandingPageModel::class);
        $landingPage->method('getCustomJs')->willReturn('console.log("hello");');

        $block = $this->createObjectToTest($landingPage);

        $result = $this->invokeMethod($block, 'getLandingCustomJs');

        $this->assertStringContainsString('<script type="text/javascript">', $result);
        $this->assertStringContainsString('console.log("hello");', $result);
    }

    public function testGetLandingCustomCssReturnsEmptyStringWhenNoCustomCss(): void
    {
        $landingPage = $this->createStub(LandingPageModel::class);
        $landingPage->method('getCustomCss')->willReturn('');

        $block = $this->createObjectToTest($landingPage);

        $result = $this->invokeMethod($block, 'getLandingCustomCss');

        $this->assertSame('', $result);
    }

    public function testGetLandingCustomCssWrapsContentInStyleTag(): void
    {
        $landingPage = $this->createStub(LandingPageModel::class);
        $landingPage->method('getCustomCss')->willReturn('body { color: red; }');

        $block = $this->createObjectToTest($landingPage);

        $result = $this->invokeMethod($block, 'getLandingCustomCss');

        $this->assertStringContainsString('<style type="text/css">', $result);
        $this->assertStringContainsString('body { color: red; }', $result);
    }
}
