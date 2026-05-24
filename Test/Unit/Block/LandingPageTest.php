<?php

declare(strict_types=1);

namespace Algolia\AlgoliaSearch\Test\Unit\Block;

use Algolia\AlgoliaSearch\Block\LandingPage;
use Algolia\AlgoliaSearch\Model\LandingPage as LandingPageModel;
use Algolia\AlgoliaSearch\Test\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

class LandingPageTest extends TestCase
{
    protected null|(LandingPage&MockObject) $block = null;
    protected null|(LandingPageModel&MockObject) $landingPage = null;

    protected function setUp(): void
    {
        $this->landingPage = $this->createMock(LandingPageModel::class);

        $this->block = $this->getMockBuilder(LandingPage::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();

        $this->setPrivateProperty($this->block, 'landingPage', $this->landingPage);
    }

    public function testGetPageReturnsCachedLandingPageWhenNoPageId(): void
    {
        // No page_id data set, so getPageId() returns null → falls back to $this->landingPage
        $result = $this->block->getPage();

        $this->assertSame($this->landingPage, $result);
    }

    public function testGetPageReturnsSameCachedInstanceOnSecondCall(): void
    {
        $first = $this->block->getPage();
        $second = $this->block->getPage();

        $this->assertSame($first, $second);
    }

    public function testGetLandingCustomJsReturnsEmptyStringWhenNoCustomJs(): void
    {
        $this->landingPage->method('getCustomJs')->willReturn('');

        $result = $this->invokeMethod($this->block, 'getLandingCustomJs');

        $this->assertSame('', $result);
    }

    public function testGetLandingCustomJsWrapsJsInScriptTag(): void
    {
        $this->landingPage->method('getCustomJs')->willReturn('console.log("hello");');

        $result = $this->invokeMethod($this->block, 'getLandingCustomJs');

        $this->assertStringContainsString('<script type="text/javascript">', $result);
        $this->assertStringContainsString('console.log("hello");', $result);
    }

    public function testGetLandingCustomCssReturnsEmptyStringWhenNoCustomCss(): void
    {
        $this->landingPage->method('getCustomCss')->willReturn('');

        $result = $this->invokeMethod($this->block, 'getLandingCustomCss');

        $this->assertSame('', $result);
    }

    public function testGetLandingCustomCssWrapsContentInStyleTag(): void
    {
        $this->landingPage->method('getCustomCss')->willReturn('body { color: red; }');

        $result = $this->invokeMethod($this->block, 'getLandingCustomCss');

        $this->assertStringContainsString('<style type="text/css">', $result);
        $this->assertStringContainsString('body { color: red; }', $result);
    }
}
