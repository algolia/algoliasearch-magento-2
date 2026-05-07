<?php

declare(strict_types=1);

namespace Algolia\AlgoliaSearch\Test\Unit\Plugin;

use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Plugin\RemovePdpProductsBlock;
use Algolia\AlgoliaSearch\Test\TestCase;
use Magento\Framework\View\Element\AbstractBlock;
use PHPUnit\Framework\MockObject\MockObject;

class RemovePdpProductsBlockTest extends TestCase
{
    protected null|(ConfigHelper&MockObject) $configHelper = null;
    protected null|(AbstractBlock&MockObject) $subject = null;
    protected ?RemovePdpProductsBlock $plugin = null;

    protected function setUp(): void
    {
        $this->configHelper = $this->createMock(ConfigHelper::class);
        $this->subject = $this->getMockBuilder(AbstractBlock::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getNameInLayout'])
            ->getMockForAbstractClass();
        $this->plugin = new RemovePdpProductsBlock($this->configHelper);
    }

    public function testReturnsEmptyStringForRelatedBlockWhenAllConditionsMet(): void
    {
        $this->subject->method('getNameInLayout')->willReturn(RemovePdpProductsBlock::RELATED_BLOCK_NAME);
        $this->configHelper->method('isRecommendRelatedProductsEnabled')->willReturn(true);
        $this->configHelper->method('isRemoveCoreRelatedProductsBlock')->willReturn(true);

        $this->assertSame('', $this->plugin->afterToHtml($this->subject, '<div>related</div>'));
    }

    public function testReturnsOriginalResultForRelatedBlockWhenRelatedProductsNotEnabled(): void
    {
        $this->subject->method('getNameInLayout')->willReturn(RemovePdpProductsBlock::RELATED_BLOCK_NAME);
        $this->configHelper->method('isRecommendRelatedProductsEnabled')->willReturn(false);

        $this->assertSame('<div>related</div>', $this->plugin->afterToHtml($this->subject, '<div>related</div>'));
    }

    public function testReturnsOriginalResultForRelatedBlockWhenCoreBlockRemovalNotEnabled(): void
    {
        $this->subject->method('getNameInLayout')->willReturn(RemovePdpProductsBlock::RELATED_BLOCK_NAME);
        $this->configHelper->method('isRecommendRelatedProductsEnabled')->willReturn(true);
        $this->configHelper->method('isRemoveCoreRelatedProductsBlock')->willReturn(false);

        $this->assertSame('<div>related</div>', $this->plugin->afterToHtml($this->subject, '<div>related</div>'));
    }

    public function testReturnsEmptyStringForUpsellBlockWhenAllConditionsMet(): void
    {
        $this->subject->method('getNameInLayout')->willReturn(RemovePdpProductsBlock::UPSELL_BLOCK_NAME);
        $this->configHelper->method('isRecommendFrequentlyBroughtTogetherEnabled')->willReturn(true);
        $this->configHelper->method('isRemoveUpsellProductsBlock')->willReturn(true);

        $this->assertSame('', $this->plugin->afterToHtml($this->subject, '<div>upsell</div>'));
    }

    public function testReturnsOriginalResultForUpsellBlockWhenFbtNotEnabled(): void
    {
        $this->subject->method('getNameInLayout')->willReturn(RemovePdpProductsBlock::UPSELL_BLOCK_NAME);
        $this->configHelper->method('isRecommendFrequentlyBroughtTogetherEnabled')->willReturn(false);

        $this->assertSame('<div>upsell</div>', $this->plugin->afterToHtml($this->subject, '<div>upsell</div>'));
    }

    public function testReturnsOriginalResultForUnknownBlock(): void
    {
        $this->subject->method('getNameInLayout')->willReturn('some.other.block');

        $this->assertSame('<div>content</div>', $this->plugin->afterToHtml($this->subject, '<div>content</div>'));
    }
}
