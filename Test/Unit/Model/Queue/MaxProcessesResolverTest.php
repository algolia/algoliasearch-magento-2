<?php

namespace Algolia\AlgoliaSearch\Test\Unit\Model\Queue;

use Algolia\AlgoliaSearch\Helper\Configuration\QueueHelper;
use Algolia\AlgoliaSearch\Model\Queue\MaxProcessesResolver;
use Algolia\AlgoliaSearch\Test\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class MaxProcessesResolverTest extends TestCase
{
    protected function createObjectToTest(
        int $configuredMaxProcesses = 0,
        int $autoCap = 4,
        int $detectedCores = 8,
    ): MaxProcessesResolver {
        $queueHelper = $this->createStub(QueueHelper::class);
        $queueHelper->method('getMaxParallelProcesses')->willReturn($configuredMaxProcesses);
        $queueHelper->method('getAutoParallelProcessesCap')->willReturn($autoCap);

        return new class($queueHelper, $detectedCores) extends MaxProcessesResolver {
            public function __construct(
                QueueHelper $queueHelper,
                private readonly int $stubbedCores,
            ) {
                parent::__construct($queueHelper);
            }

            protected function detectCpuCores(): int
            {
                return $this->stubbedCores;
            }
        };
    }

    public static function cliOverrideProvider(): \Generator
    {
        yield 'override beats config and auto regardless of cap' => [
            'cliOverride' => 2,
            'configuredMaxProcesses' => 16,
            'autoCap' => 16,
            'detectedCores' => 64,
            'expected' => 2,
        ];

        yield 'override of 1 forces sequential mode' => [
            'cliOverride' => 1,
            'configuredMaxProcesses' => 0,
            'autoCap' => 8,
            'detectedCores' => 64,
            'expected' => 1,
        ];

        yield 'override is not capped by auto cap' => [
            'cliOverride' => 100,
            'configuredMaxProcesses' => 0,
            'autoCap' => 4,
            'detectedCores' => 4,
            'expected' => 100,
        ];

        yield 'zero override falls through to config' => [
            'cliOverride' => 0,
            'configuredMaxProcesses' => 3,
            'autoCap' => 4,
            'detectedCores' => 16,
            'expected' => 3,
        ];
    }

    #[DataProvider('cliOverrideProvider')]
    public function testResolvePrefersCliOverride(
        ?int $cliOverride,
        int $configuredMaxProcesses,
        int $autoCap,
        int $detectedCores,
        int $expected
    ): void {
        $resolver = $this->createObjectToTest($configuredMaxProcesses, $autoCap, $detectedCores);

        $this->assertSame($expected, $resolver->resolve($cliOverride));
    }

    public static function configuredValueProvider(): \Generator
    {
        yield 'configured value wins over auto' => [
            'configuredMaxProcesses' => 6,
            'autoCap' => 2,
            'detectedCores' => 64,
            'expected' => 6,
        ];

        yield 'configured value is not capped by auto cap' => [
            'configuredMaxProcesses' => 12,
            'autoCap' => 4,
            'detectedCores' => 4,
            'expected' => 12,
        ];

        yield 'configured 1 forces sequential mode' => [
            'configuredMaxProcesses' => 1,
            'autoCap' => 8,
            'detectedCores' => 64,
            'expected' => 1,
        ];
    }

    #[DataProvider('configuredValueProvider')]
    public function testResolveUsesConfiguredValueWhenPositive(
        int $configuredMaxProcesses,
        int $autoCap,
        int $detectedCores,
        int $expected
    ): void {
        $resolver = $this->createObjectToTest($configuredMaxProcesses, $autoCap, $detectedCores);

        $this->assertSame($expected, $resolver->resolve());
    }

    public static function autoModeProvider(): \Generator
    {
        yield 'auto is cores minus one' => [
            'autoCap' => 16,
            'detectedCores' => 8,
            'expected' => 7,
        ];

        yield 'auto is capped by configured cap' => [
            'autoCap' => 4,
            'detectedCores' => 32,
            'expected' => 4,
        ];

        yield 'single core machine resolves to one worker' => [
            'autoCap' => 4,
            'detectedCores' => 1,
            'expected' => 1,
        ];

        yield 'cap below one falls back to default cap' => [
            'autoCap' => 0,
            'detectedCores' => 32,
            'expected' => MaxProcessesResolver::DEFAULT_AUTO_CAP,
        ];

        yield 'negative cap falls back to default cap' => [
            'autoCap' => -3,
            'detectedCores' => 32,
            'expected' => MaxProcessesResolver::DEFAULT_AUTO_CAP,
        ];

        yield 'core detection failure falls back to one worker' => [
            'autoCap' => 4,
            'detectedCores' => 0,
            'expected' => 1,
        ];
    }

    #[DataProvider('autoModeProvider')]
    public function testResolveAppliesAutoFormulaWhenConfiguredValueIsZero(
        int $autoCap,
        int $detectedCores,
        int $expected
    ): void {
        $resolver = $this->createObjectToTest(0, $autoCap, $detectedCores);

        $this->assertSame($expected, $resolver->resolve());
    }

    public static function pendingStoresProvider(): \Generator
    {
        yield 'pending stores below limit reduce the result' => [
            'cliOverride' => null,
            'configuredMaxProcesses' => 4,
            'pendingStores' => 2,
            'expected' => 2,
        ];

        yield 'pending stores above limit leave the result untouched' => [
            'cliOverride' => null,
            'configuredMaxProcesses' => 4,
            'pendingStores' => 9,
            'expected' => 4,
        ];

        yield 'zero pending stores resolve to zero' => [
            'cliOverride' => null,
            'configuredMaxProcesses' => 4,
            'pendingStores' => 0,
            'expected' => 0,
        ];

        yield 'limit untouched without pending stores' => [
            'cliOverride' => null,
            'configuredMaxProcesses' => 4,
            'pendingStores' => null,
            'expected' => 4,
        ];

        yield 'pending stores also clamp a cli override' => [
            'cliOverride' => 8,
            'configuredMaxProcesses' => 1,
            'pendingStores' => 3,
            'expected' => 3,
        ];
    }

    #[DataProvider('pendingStoresProvider')]
    public function testResolveClampsToPendingStores(
        ?int $cliOverride,
        int $configuredMaxProcesses,
        ?int $pendingStores,
        int $expected
    ): void {
        $resolver = $this->createObjectToTest($configuredMaxProcesses);

        $this->assertSame($expected, $resolver->resolve($cliOverride, $pendingStores));
    }

    public function testDetectCpuCoresFallsBackWhenDetectionFails(): void
    {
        $queueHelper = $this->createStub(QueueHelper::class);
        $queueHelper->method('getMaxParallelProcesses')->willReturn(0);
        $queueHelper->method('getAutoParallelProcessesCap')->willReturn(4);

        $resolver = new class($queueHelper) extends MaxProcessesResolver {
            protected function detectCpuCores(): int
            {
                throw new \RuntimeException('proc_open unavailable');
            }
        };

        $this->assertSame(1, $resolver->resolve());
    }

    public function testDetectCpuCoresReturnsPositiveCountOnHost(): void
    {
        $queueHelper = $this->createStub(QueueHelper::class);
        $queueHelper->method('getMaxParallelProcesses')->willReturn(0);
        $queueHelper->method('getAutoParallelProcessesCap')->willReturn(64);

        $resolver = new MaxProcessesResolver($queueHelper);

        $this->assertGreaterThan(0, $resolver->resolve());
    }
}
