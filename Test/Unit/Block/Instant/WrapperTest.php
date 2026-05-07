<?php

declare(strict_types=1);

namespace Algolia\AlgoliaSearch\Test\Unit\Block\Instant;

use Algolia\AlgoliaSearch\Block\Instant\Wrapper;
use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Test\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

class WrapperTest extends TestCase
{
    protected null|(Wrapper&MockObject) $block = null;
    protected null|(ConfigHelper&MockObject) $config = null;

    protected function setUp(): void
    {
        $this->config = $this->createMock(ConfigHelper::class);

        $this->block = $this->getMockBuilder(Wrapper::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();

        $this->setPrivateProperty($this->block, 'config', $this->config);
    }

    public function testHasFacetsReturnsTrueWhenFacetsExist(): void
    {
        $this->config->method('getFacets')->willReturn([['attribute' => 'color'], ['attribute' => 'size']]);
        $this->assertTrue($this->block->hasFacets());
    }

    public function testHasFacetsReturnsFalseWhenFacetsArrayIsEmpty(): void
    {
        $this->config->method('getFacets')->willReturn([]);
        $this->assertFalse($this->block->hasFacets());
    }
}
