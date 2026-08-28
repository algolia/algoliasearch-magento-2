<?php

declare(strict_types=1);

namespace Algolia\AlgoliaSearch\Test\Unit\Block\Navigation\Renderer;

use Algolia\AlgoliaSearch\Block\Navigation\Renderer\SliderRenderer;
use Algolia\AlgoliaSearch\Test\TestCase;
use Magento\Catalog\Model\Layer\Filter\FilterInterface;
use Magento\Framework\Json\EncoderInterface;
use Magento\Framework\Locale\FormatInterface;
use Magento\Framework\View\Element\Template\Context;

class SliderRendererTest extends TestCase
{
    protected function createObjectToTest(
        ?EncoderInterface $jsonEncoder = null,
        ?FormatInterface $localeFormat = null,
    ): SliderRenderer {
        $context = $this->createStub(Context::class);

        return new SliderRenderer(
            $context,
            $jsonEncoder ?? $this->createStub(EncoderInterface::class),
            $localeFormat ?? $this->createStub(FormatInterface::class),
        );
    }

    public function testGetDataRoleConcatenatesRoleWithFilterRequestVar(): void
    {
        $filter = $this->createStub(FilterInterface::class);
        $filter->method('getRequestVar')->willReturn('price');

        $block = $this->createObjectToTest();
        $this->setPrivateProperty($block, 'filter', $filter);

        $this->assertSame('range-slider-price', $block->getDataRole());
    }

    public function testGetFilterReturnsStoredFilter(): void
    {
        $filter = $this->createStub(FilterInterface::class);

        $block = $this->createObjectToTest();
        $this->setPrivateProperty($block, 'filter', $filter);

        $this->assertSame($filter, $block->getFilter());
    }
}
