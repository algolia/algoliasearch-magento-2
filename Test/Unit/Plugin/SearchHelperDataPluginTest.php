<?php

declare(strict_types=1);

namespace Algolia\AlgoliaSearch\Test\Unit\Plugin;

use Algolia\AlgoliaSearch\Plugin\SearchHelperDataPlugin;
use Algolia\AlgoliaSearch\Test\TestCase;
use Magento\Search\Helper\Data;
use PHPUnit\Framework\MockObject\MockObject;

class SearchHelperDataPluginTest extends TestCase
{
    protected ?SearchHelperDataPlugin $plugin = null;

    protected function setUp(): void
    {
        $this->plugin = new SearchHelperDataPlugin();
    }

    public function testReturnsEmptyStringWhenResultIsEmptyPlaceholder(): void
    {
        $subject = $this->createMock(Data::class);

        $this->assertSame('', $this->plugin->afterGetEscapedQueryText($subject, '__empty__'));
    }

    public function testReturnsOriginalResultForNormalQuery(): void
    {
        $subject = $this->createMock(Data::class);

        $this->assertSame('running shoes', $this->plugin->afterGetEscapedQueryText($subject, 'running shoes'));
    }

    public function testReturnsOriginalResultForEmptyString(): void
    {
        $subject = $this->createMock(Data::class);

        $this->assertSame('', $this->plugin->afterGetEscapedQueryText($subject, ''));
    }
}
