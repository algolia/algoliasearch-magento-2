<?php

declare(strict_types=1);

namespace Algolia\AlgoliaSearch\Test\Unit\Plugin;

use Algolia\AlgoliaSearch\Plugin\SearchHelperDataPlugin;
use Algolia\AlgoliaSearch\Test\TestCase;
use Magento\Search\Helper\Data;

class SearchHelperDataPluginTest extends TestCase
{
    public function testReturnsEmptyStringWhenResultIsEmptyPlaceholder(): void
    {
        $plugin = new SearchHelperDataPlugin();
        $subject = $this->createStub(Data::class);

        $this->assertSame('', $plugin->afterGetEscapedQueryText($subject, '__empty__'));
    }

    public function testReturnsOriginalResultForNormalQuery(): void
    {
        $plugin = new SearchHelperDataPlugin();
        $subject = $this->createStub(Data::class);

        $this->assertSame('running shoes', $plugin->afterGetEscapedQueryText($subject, 'running shoes'));
    }

    public function testReturnsOriginalResultForEmptyString(): void
    {
        $plugin = new SearchHelperDataPlugin();
        $subject = $this->createStub(Data::class);

        $this->assertSame('', $plugin->afterGetEscapedQueryText($subject, ''));
    }
}
