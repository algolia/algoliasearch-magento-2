<?php

declare(strict_types=1);

namespace Algolia\AlgoliaSearch\Test\Unit\Block\Widget;

use Algolia\AlgoliaSearch\Block\Widget\LookingSimilar;
use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Test\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;

class LookingSimilarTest extends TestCase
{
    protected null|(LookingSimilar&MockObject) $block = null;
    protected null|(ConfigHelper&MockObject) $configHelper = null;

    protected function setUp(): void
    {
        $this->configHelper = $this->createMock(ConfigHelper::class);

        $this->block = $this->getMockBuilder(LookingSimilar::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();

        $this->setPrivateProperty($this->block, 'configHelper', $this->configHelper);
    }

    #[DataProvider('productIdsDataProvider')]
    public function testGetProductIdsReturnsJsonEncodedArray(string $input, string $expected): void
    {
        $this->block->setData('productIds', $input);

        $this->assertSame($expected, $this->block->getProductIds());
    }

    public static function productIdsDataProvider(): array
    {
        return [
            'single id'    => ['42',    '["42"]'],
            'multiple ids' => ['1,2,3', '["1","2","3"]'],
            'empty string' => ['',      '[""]'],
        ];
    }
}
