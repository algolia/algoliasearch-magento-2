<?php

declare(strict_types=1);

namespace Algolia\AlgoliaSearch\Test\Unit\Block\Widget;

use Algolia\AlgoliaSearch\Block\Widget\LookingSimilar;
use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Test\TestCase;
use Magento\Framework\Math\Random;
use Magento\Framework\View\Element\Template\Context;
use PHPUnit\Framework\Attributes\DataProvider;

class LookingSimilarTest extends TestCase
{
    protected function createObjectToTest(
        ?ConfigHelper $configHelper = null,
        ?Random $mathRandom = null,
    ): LookingSimilar {
        $context = $this->createStub(Context::class);

        return new LookingSimilar(
            $context,
            $configHelper ?? $this->createStub(ConfigHelper::class),
            $mathRandom ?? $this->createStub(Random::class),
        );
    }

    #[DataProvider('productIdsDataProvider')]
    public function testGetProductIdsReturnsJsonEncodedArray(string $input, string $expected): void
    {
        $block = $this->createObjectToTest();
        $block->setData('productIds', $input);

        $this->assertSame($expected, $block->getProductIds());
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
