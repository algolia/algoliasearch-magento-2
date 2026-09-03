<?php

namespace Algolia\AlgoliaSearch\Test\Unit\Service;

use Algolia\AlgoliaSearch\Api\SendStrategyInterface;
use Algolia\AlgoliaSearch\Service\SendStrategyResolver;
use Algolia\AlgoliaSearch\Test\TestCase;

class SendStrategyResolverTest extends TestCase
{
    private const STORE_ID = 1;

    public function testResolveReturnsDefaultStrategyWhenNoStrategiesConfigured(): void
    {
        $defaultStrategy = $this->createStub(SendStrategyInterface::class);
        $resolver = new SendStrategyResolver($defaultStrategy);

        $this->assertSame($defaultStrategy, $resolver->resolve(self::STORE_ID));
    }

    public function testResolveReturnsDefaultStrategyWhenNoStrategyIsApplicable(): void
    {
        $defaultStrategy = $this->createStub(SendStrategyInterface::class);

        $inapplicable = $this->createStub(SendStrategyInterface::class);
        $inapplicable->method('isApplicable')->willReturn(false);

        $resolver = new SendStrategyResolver($defaultStrategy, [$inapplicable]);

        $this->assertSame($defaultStrategy, $resolver->resolve(self::STORE_ID));
    }

    public function testResolveReturnsFirstApplicableStrategy(): void
    {
        $applicable = $this->createMock(SendStrategyInterface::class);
        $applicable->method('isApplicable')->with(self::STORE_ID)->willReturn(true);

        $resolver = new SendStrategyResolver($this->createStub(SendStrategyInterface::class), [$applicable]);

        $this->assertSame($applicable, $resolver->resolve(self::STORE_ID));
    }

    public function testResolveReturnsFirstApplicableWhenMultipleMatch(): void
    {
        $first = $this->createStub(SendStrategyInterface::class);
        $first->method('isApplicable')->willReturn(true);

        $second = $this->createStub(SendStrategyInterface::class);
        $second->method('isApplicable')->willReturn(true);

        $resolver = new SendStrategyResolver($this->createStub(SendStrategyInterface::class), [$first, $second]);

        $this->assertSame($first, $resolver->resolve(self::STORE_ID));
    }

    public function testResolveSkipsInapplicableAndReturnsFirstApplicable(): void
    {
        $inapplicable = $this->createStub(SendStrategyInterface::class);
        $inapplicable->method('isApplicable')->willReturn(false);

        $applicable = $this->createStub(SendStrategyInterface::class);
        $applicable->method('isApplicable')->willReturn(true);

        $resolver = new SendStrategyResolver($this->createStub(SendStrategyInterface::class), [$inapplicable, $applicable]);

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

        $resolver = new SendStrategyResolver($this->createStub(SendStrategyInterface::class), [$strategy]);
        $resolver->resolve($storeId);
    }

    public function testResolveDoesNotCallIsApplicableOnDefaultStrategy(): void
    {
        $defaultStrategy = $this->createMock(SendStrategyInterface::class);
        $defaultStrategy->expects($this->never())->method('isApplicable');

        $resolver = new SendStrategyResolver($defaultStrategy);
        $resolver->resolve(self::STORE_ID);
    }

    public function testResolveIsPerStoreForTheSameStrategy(): void
    {
        $defaultStrategy = $this->createStub(SendStrategyInterface::class);

        $storeSpecific = $this->createStub(SendStrategyInterface::class);
        $storeSpecific->method('isApplicable')->willReturnMap([
            [self::STORE_ID, true],
            [self::STORE_ID + 1, false],
        ]);

        $resolver = new SendStrategyResolver($defaultStrategy, [$storeSpecific]);

        $this->assertSame($storeSpecific, $resolver->resolve(self::STORE_ID));
        $this->assertSame($defaultStrategy, $resolver->resolve(self::STORE_ID + 1));
    }
}
