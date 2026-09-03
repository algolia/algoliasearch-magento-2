<?php

declare(strict_types=1);

namespace Algolia\AlgoliaSearch\Test\Unit\Plugin;

use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Plugin\RemovePdpProductsBlock;
use Algolia\AlgoliaSearch\Test\TestCase;
use Magento\Framework\View\Element\AbstractBlock;

class RemovePdpProductsBlockTest extends TestCase
{
    protected function createObjectToTest(?ConfigHelper $configHelper = null): RemovePdpProductsBlock
    {
        return new RemovePdpProductsBlock($configHelper ?? $this->createStub(ConfigHelper::class));
    }

    public function testReturnsEmptyStringForRelatedBlockWhenAllConditionsMet(): void
    {
        $subject = $this->createStub(AbstractBlock::class);
        $subject->method('getNameInLayout')->willReturn(RemovePdpProductsBlock::RELATED_BLOCK_NAME);

        $configHelper = $this->createStub(ConfigHelper::class);
        $configHelper->method('isRecommendRelatedProductsEnabled')->willReturn(true);
        $configHelper->method('isRemoveCoreRelatedProductsBlock')->willReturn(true);

        $plugin = $this->createObjectToTest($configHelper);

        $this->assertSame('', $plugin->afterToHtml($subject, '<div>related</div>'));
    }

    public function testReturnsOriginalResultForRelatedBlockWhenRelatedProductsNotEnabled(): void
    {
        $subject = $this->createStub(AbstractBlock::class);
        $subject->method('getNameInLayout')->willReturn(RemovePdpProductsBlock::RELATED_BLOCK_NAME);

        $configHelper = $this->createStub(ConfigHelper::class);
        $configHelper->method('isRecommendRelatedProductsEnabled')->willReturn(false);

        $plugin = $this->createObjectToTest($configHelper);

        $this->assertSame('<div>related</div>', $plugin->afterToHtml($subject, '<div>related</div>'));
    }

    public function testReturnsOriginalResultForRelatedBlockWhenCoreBlockRemovalNotEnabled(): void
    {
        $subject = $this->createStub(AbstractBlock::class);
        $subject->method('getNameInLayout')->willReturn(RemovePdpProductsBlock::RELATED_BLOCK_NAME);

        $configHelper = $this->createStub(ConfigHelper::class);
        $configHelper->method('isRecommendRelatedProductsEnabled')->willReturn(true);
        $configHelper->method('isRemoveCoreRelatedProductsBlock')->willReturn(false);

        $plugin = $this->createObjectToTest($configHelper);

        $this->assertSame('<div>related</div>', $plugin->afterToHtml($subject, '<div>related</div>'));
    }

    public function testReturnsEmptyStringForUpsellBlockWhenAllConditionsMet(): void
    {
        $subject = $this->createStub(AbstractBlock::class);
        $subject->method('getNameInLayout')->willReturn(RemovePdpProductsBlock::UPSELL_BLOCK_NAME);

        $configHelper = $this->createStub(ConfigHelper::class);
        $configHelper->method('isRecommendFrequentlyBroughtTogetherEnabled')->willReturn(true);
        $configHelper->method('isRemoveUpsellProductsBlock')->willReturn(true);

        $plugin = $this->createObjectToTest($configHelper);

        $this->assertSame('', $plugin->afterToHtml($subject, '<div>upsell</div>'));
    }

    public function testReturnsOriginalResultForUpsellBlockWhenFbtNotEnabled(): void
    {
        $subject = $this->createStub(AbstractBlock::class);
        $subject->method('getNameInLayout')->willReturn(RemovePdpProductsBlock::UPSELL_BLOCK_NAME);

        $configHelper = $this->createStub(ConfigHelper::class);
        $configHelper->method('isRecommendFrequentlyBroughtTogetherEnabled')->willReturn(false);

        $plugin = $this->createObjectToTest($configHelper);

        $this->assertSame('<div>upsell</div>', $plugin->afterToHtml($subject, '<div>upsell</div>'));
    }

    public function testReturnsOriginalResultForUnknownBlock(): void
    {
        $subject = $this->createStub(AbstractBlock::class);
        $subject->method('getNameInLayout')->willReturn('some.other.block');

        $plugin = $this->createObjectToTest();

        $this->assertSame('<div>content</div>', $plugin->afterToHtml($subject, '<div>content</div>'));
    }
}
