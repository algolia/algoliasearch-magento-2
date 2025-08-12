<?php

namespace Algolia\AlgoliaSearch\Test\Unit\Helper;

use Algolia\AlgoliaSearch\Helper\ArrayDeduplicator;
use PHPUnit\Framework\TestCase;

class ArrayDeduplicatorTest extends TestCase
{
    protected ?ArrayDeduplicator $deduplicator = null;

    protected function setUp(): void
    {
        $this->deduplicator = new ArrayDeduplicator();
    }

    public function testDedupeArrayOfArraysRemovesExactDuplicates(): void
    {
        $data = [
            ['a' => 1, 'b' => 2],
            ['a' => 1, 'b' => 2], // duplicate
            ['a' => 2, 'b' => 3],
        ];

        $result = $this->deduplicator->dedupeArrayOfArrays($data);

        $this->assertCount(2, $result);
        $this->assertContains(['a' => 1, 'b' => 2], $result);
        $this->assertContains(['a' => 2, 'b' => 3], $result);
    }

    public function testDedupeArrayOfArraysKeepsOrderOfFirstOccurrences(): void
    {
        $data = [
            ['id' => 1],
            ['id' => 2],
            ['id' => 1], // duplicate
        ];

        $result = $this->deduplicator->dedupeArrayOfArrays($data);

        $this->assertSame([['id' => 1], ['id' => 2]], $result);
    }

    public function testDedupeSpecificSettingsOnlyProcessesRequestedSettings(): void
    {
        $settings = [
            'synonyms' => [
                ['word' => 'foo'],
                ['word' => 'foo'],
            ],
            'altCorrections' => [
                ['word' => 'bar'],
            ],
            'placeholders' => [
                ['word' => 'baz'],
            ],
        ];

        $result = $this->deduplicator->dedupeSpecificSettings(
            ['synonyms', 'altCorrections'],
            $settings
        );

        $this->assertArrayHasKey('synonyms', $result);
        $this->assertCount(1, $result['synonyms']); // deduped
        $this->assertArrayHasKey('altCorrections', $result);
        $this->assertCount(1, $result['altCorrections']);
        $this->assertArrayNotHasKey('placeholders', $result);
    }

    public function testDedupeSpecificSettingsHandlesMissingKeys(): void
    {
        $settings = [
            'synonyms' => [['w' => 'x']],
        ];

        $result = $this->deduplicator->dedupeSpecificSettings(
            ['synonyms', 'altCorrections'],
            $settings
        );

        $this->assertArrayHasKey('synonyms', $result);
        $this->assertArrayNotHasKey('altCorrections', $result);
    }
}
