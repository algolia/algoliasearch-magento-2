<?php

namespace Algolia\AlgoliaSearch\Test\Unit\Model\Queue\Output;

use Algolia\AlgoliaSearch\Model\Queue\Output\WorkerLineBuffer;
use Algolia\AlgoliaSearch\Test\TestCase;

class WorkerLineBufferTest extends TestCase
{
    private function createObjectToTest(): WorkerLineBuffer
    {
        return new WorkerLineBuffer();
    }

    public function testReturnsCompleteLinesFromASingleChunk(): void
    {
        $this->assertSame(
            ['first line', 'second line'],
            $this->createObjectToTest()->push("first line\nsecond line\n")
        );
    }

    public function testBuffersPartialChunksUntilTheLineCompletes(): void
    {
        $buffer = $this->createObjectToTest();

        $this->assertSame([], $buffer->push('hel'));
        $this->assertSame([], $buffer->push('lo wor'));

        $this->assertSame(['hello world'], $buffer->push("ld\n"));
    }

    public function testKeepsTheBufferAcrossManyPartialChunks(): void
    {
        $buffer = $this->createObjectToTest();

        foreach (str_split('a progressive line without a break yet') as $chunk) {
            $this->assertSame([], $buffer->push($chunk));
        }

        $this->assertSame(['a progressive line without a break yet'], $buffer->push("\n"));
    }

    public function testStripsCarriageReturnsFromCrlfLineEndings(): void
    {
        $this->assertSame(
            ['first', 'second'],
            $this->createObjectToTest()->push("first\r\nsecond\r\n")
        );
    }

    public function testHandlesCrlfSplitAcrossChunks(): void
    {
        $buffer = $this->createObjectToTest();

        $this->assertSame([], $buffer->push("first\r"));

        $this->assertSame(['first'], $buffer->push("\nsecond"));
    }

    public function testEmitsMultipleLinesFromASingleChunk(): void
    {
        $this->assertSame(
            ['one', 'two', 'three'],
            $this->createObjectToTest()->push("one\ntwo\nthree\n")
        );
    }

    public function testFlushReturnsThePartialTailWhenTheWorkerExitedWithoutATrailingNewline(): void
    {
        $buffer = $this->createObjectToTest();

        $this->assertSame([], $buffer->push('dying words'));

        $this->assertSame('dying words', $buffer->flush());
    }

    public function testFlushReturnsNullWhenTheBufferIsEmpty(): void
    {
        $this->assertNull($this->createObjectToTest()->flush());
    }

    public function testFlushReturnsNullAfterAllLinesWereComplete(): void
    {
        $buffer = $this->createObjectToTest();

        $this->assertSame(['complete line'], $buffer->push("complete line\n"));

        $this->assertNull($buffer->flush());
    }

    public function testFlushReturnsOnlyTheRemainingTail(): void
    {
        $buffer = $this->createObjectToTest();

        $this->assertSame(['complete'], $buffer->push("complete\npart"));

        $this->assertSame('part', $buffer->flush());
    }

    public function testFlushClearsTheBuffer(): void
    {
        $buffer = $this->createObjectToTest();
        $buffer->push('tail without newline');

        $this->assertSame('tail without newline', $buffer->flush());
        $this->assertNull($buffer->flush());
    }
}
