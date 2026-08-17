<?php

declare(strict_types=1);

namespace Algolia\AlgoliaSearch\Test\Unit\Block\Instant;

use Algolia\AlgoliaSearch\Block\Instant\Wrapper;
use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Test\TestCase;
use Magento\Framework\View\Element\Template\Context;

class WrapperTest extends TestCase
{
    protected function createObjectToTest(?ConfigHelper $config = null): Wrapper
    {
        $context = $this->createStub(Context::class);

        return new Wrapper(
            $context,
            $config ?? $this->createStub(ConfigHelper::class),
        );
    }

    public function testHasFacetsReturnsTrueWhenFacetsExist(): void
    {
        $config = $this->createStub(ConfigHelper::class);
        $config->method('getFacets')->willReturn([['attribute' => 'color'], ['attribute' => 'size']]);

        $block = $this->createObjectToTest($config);

        $this->assertTrue($block->hasFacets());
    }

    public function testHasFacetsReturnsFalseWhenFacetsArrayIsEmpty(): void
    {
        $config = $this->createStub(ConfigHelper::class);
        $config->method('getFacets')->willReturn([]);

        $block = $this->createObjectToTest($config);

        $this->assertFalse($block->hasFacets());
    }
}
