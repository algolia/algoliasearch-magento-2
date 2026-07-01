<?php

namespace Algolia\AlgoliaSearch\Test\Unit\Service;

use Algolia\AlgoliaSearch\Api\SendStrategyInterface;
use Algolia\AlgoliaSearch\Service\SendStrategyResolver;
use Algolia\AlgoliaSearch\Test\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

class SendStrategyResolverTest extends TestCase
{
    private const STORE_ID = 1;

    private null|(SendStrategyInterface&MockObject) $defaultStrategy = null;

    protected function setUp(): void
    {
        $this->defaultStrategy = $this->createMock(SendStrategyInterface::class);
    }

    public function testResolveReturnsDefaultStrategyWhenNoStrategiesConfigured(): void
    {
        $resolver = new SendStrategyResolver($this->defaultStrategy);

        $this->assertSame($this->defaultStrategy, $resolver->resolve(self::STORE_ID));
    }

    public function testResolveReturnsDefaultStrategyWhenNoStrategyIsApplicable(): void
    {
        $inapplicable = $this->createMock(SendStrategyInterface::class);
        $inapplicable->method('isApplicable')->willReturn(false);

        $resolver = new SendStrategyResolver($this->defaultStrategy, [$inapplicable]);

        $this->assertSame($this->defaultStrategy, $resolver->resolve(self::STORE_ID));
    }

    public function testResolveReturnsFirstApplicableStrategy(): void
    {
        $applicable = $this->createMock(SendStrategyInterface::class);
        $applicable->method('isApplicable')->with(self::STORE_ID)->willReturn(true);

        $resolver = new SendStrategyResolver($this->defaultStrategy, [$applicable]);

        $this->assertSame($applicable, $resolver->resolve(self::STORE_ID));
    }

    public function testResolveReturnsFirstApplicableWhenMultipleMatch(): void
    {
        $first = $this->createMock(SendStrategyInterface::class);
        $first->method('isApplicable')->willReturn(true);

        $second = $this->createMock(SendStrategyInterface::class);
        $second->method('isApplicable')->willReturn(true);

        $resolver = new SendStrategyResolver($this->defaultStrategy, [$first, $second]);

        $this->assertSame($first, $resolver->resolve(self::STORE_ID));
    }

    public function testResolveSkipsInapplicableAndReturnsFirstApplicable(): void
    {
        $inapplicable = $this->createMock(SendStrategyInterface::class);
        $inapplicable->method('isApplicable')->willReturn(false);

        $applicable = $this->createMock(SendStrategyInterface::class);
        $applicable->method('isApplicable')->willReturn(true);

        $resolver = new SendStrategyResolver($this->defaultStrategy, [$inapplicable, $applicable]);

        $this->assertSame($applicable, $resolver->resolve(self::STORE_ID));
    }

    public function testResolvePassesStoreIdToIsApplicable(): void
    {
        $storeId = 42;

        $strategy = $this->createMock(SendStrategyInterface::class);
        $strategy->expects($this->once())
            ->method('isApplicable')
            ->with($storeId)
            ->willReturn(false);

        $resolver = new SendStrategyResolver($this->defaultStrategy, [$strategy]);
        $resolver->resolve($storeId);
    }

    public function testResolveDoesNotCallIsApplicableOnDefaultStrategy(): void
    {
        $this->defaultStrategy->expects($this->never())->method('isApplicable');

        $resolver = new SendStrategyResolver($this->defaultStrategy);
        $resolver->resolve(self::STORE_ID);
    }
}
