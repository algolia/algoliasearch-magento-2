<?php

declare(strict_types=1);

namespace Algolia\AlgoliaSearch\Test\Unit\Block\Navigation\Renderer;

use Algolia\AlgoliaSearch\Block\Navigation\Renderer\SliderRenderer;
use Algolia\AlgoliaSearch\Test\TestCase;
use Magento\Catalog\Model\Layer\Filter\FilterInterface;
use PHPUnit\Framework\MockObject\MockObject;

class SliderRendererTest extends TestCase
{
    protected null|(SliderRenderer&MockObject) $block = null;

    protected function setUp(): void
    {
        $this->block = $this->getMockBuilder(SliderRenderer::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();
    }

    public function testGetDataRoleConcatenatesRoleWithFilterRequestVar(): void
    {
        $filter = $this->createMock(FilterInterface::class);
        $filter->method('getRequestVar')->willReturn('price');
        $this->setPrivateProperty($this->block, 'filter', $filter);

        $this->assertSame('range-slider-price', $this->block->getDataRole());
    }

    public function testGetFilterReturnsStoredFilter(): void
    {
        $filter = $this->createMock(FilterInterface::class);
        $this->setPrivateProperty($this->block, 'filter', $filter);

        $this->assertSame($filter, $this->block->getFilter());
    }
}
